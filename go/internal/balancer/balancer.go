// Package balancer provides a dynamic HTTP load balancer with health checks.
package balancer

import (
	"context"
	"encoding/hex"
	"fast-atomic-flow/go/internal/cb"
	"fast-atomic-flow/go/internal/clock"
	"fast-atomic-flow/go/internal/logger"
	"hash/fnv"
	"net"
	"net/http"
	"net/http/httputil"
	"net/url"
	"strings"
	"sync"
	"sync/atomic"
	"time"
)

// ========== TYPES ==========

// APIInstance represents a single upstream API instance.
type APIInstance struct {
	URL           *url.URL
	Alive         atomic.Bool
	forceUnalived atomic.Bool // set manually
	ExpiresAt     atomic.Int64
	Proxy         *httputil.ReverseProxy
	CB            cb.CircuitBreaker
	Hash          string
	Requests      atomic.Uint64
	Errors        atomic.Uint64
	clock         clock.Clock

	instanceTTL time.Duration
}

// Upstream manages a pool of API instances with round-robin load balancing
type Upstream struct {
	mu           sync.RWMutex
	APIInstances []*APIInstance
	current      atomic.Uint64
	checkWg      sync.WaitGroup
	cfg          Config
}

// Config holds balancer configuration.
type Config struct {
	InstanceTTL     time.Duration
	CleanupInterval time.Duration
	HealthCheck     HealthCheckConfig
}

// HealthCheckConfig holds health check parameters.
type HealthCheckConfig struct {
	Timeout         time.Duration
	Interval        time.Duration
	KeepAlive       time.Duration
	IdleConnTimeout time.Duration
	MaxIdleConns    int
	Path            string
}

// ========== API INSTANCE METHODS ==========

// SetAlive makes the instance as alive.
func (h *APIInstance) SetAlive() {
	h.Alive.Store(true)
	h.forceUnalived.Store(false)
}

// SetUnalive marks the instance as dead, optionally forcing it to stay dead
// until manually revived or re-registered.
//
// If force is true, the instance won't be automatically revived by health checks
// or successful proxy responses. Only explicit Revive() or re-registration will bring it back.
func (h *APIInstance) SetUnalive(force bool) {
	h.Alive.Store(false)
	if force {
		h.forceUnalived.Store(force)
	}
}

// IsForcedUnalived returns true if the instance was forcefully marked as dead
// and should not be automatically revived by health checks or proxy handlers.
func (h *APIInstance) IsForcedUnalived() bool {
	return h.forceUnalived.Load()
}

// IsAlive returns whether the instance is considered alive
func (h *APIInstance) IsAlive() bool {
	return h.Alive.Load()
}

// IsExpired returns whether the instance has exceeded its TTL.
func (h *APIInstance) IsExpired() bool {
	now := h.clock.Now().UnixNano()
	return h.ExpiresAt.Load() <= now
}

// Touch updates the expiration timestamp of the instance
// Extends the instance's life by instanceTTL
func (h *APIInstance) Touch() {
	h.ExpiresAt.Store(h.clock.Now().Add(h.instanceTTL).UnixNano())
}

// ========== UPSTREAM METHODS ==========

// NewUpstream creates a new Upstream with the given configuration.
func NewUpstream(cfg Config) *Upstream {
	return &Upstream{
		cfg: cfg,
	}
}

// Counts returns the number of alive and dead instances.
func (u *Upstream) Counts() (up, down uint64) {
	u.mu.RLock()
	defer u.mu.RUnlock()

	for _, i := range u.APIInstances {
		if i.Alive.Load() {
			up++
		} else {
			down++
		}
	}

	return
}

// RegisterInstance adds or updates an API instance in the pool
func (u *Upstream) RegisterInstance(targetURL string) {
	// Ignore empty URLs
	if targetURL == "" {
		return
	}

	origin, err := url.Parse(targetURL)
	if err != nil {
		logger.Error("Invalid URL", "url", targetURL, "error", err)
		return
	}

	u.mu.Lock()
	defer u.mu.Unlock()

	// Check if instance already exists (case insensitive)
	for _, inst := range u.APIInstances {
		if strings.EqualFold(inst.URL.String(), targetURL) {
			// Update existing instance
			inst.Touch()
			if !inst.IsAlive() && !inst.IsForcedUnalived() {
				inst.SetAlive()
				logger.Info("Instance re-registered and revived", "instance", targetURL)
			} else {
				logger.Debug("Heartbeat received", "instance", targetURL)
			}
			return
		}
	}

	// New instance - create and add
	proxy := httputil.NewSingleHostReverseProxy(origin)
	proxy.ErrorHandler = func(w http.ResponseWriter, _ *http.Request, e error) {
		logger.Error("Proxy error", "host", origin.Host, "error", e)
		w.WriteHeader(http.StatusBadGateway)
	}

	instance := &APIInstance{
		URL:         origin,
		Proxy:       proxy,
		Hash:        shortHash(targetURL),
		instanceTTL: u.cfg.InstanceTTL,
		clock:       clock.RealClock{},
		CB:          cb.NewCircuitBreaker(),
	}
	instance.SetAlive()
	instance.Touch()

	u.APIInstances = append(u.APIInstances, instance)
	logger.Info("New API instance registered", "instance", targetURL)
}

// NextInstance returns the next alive instance using round-robin selection
func (u *Upstream) NextInstance() *APIInstance {
	u.mu.RLock()
	defer u.mu.RUnlock()

	n := len(u.APIInstances)
	if n == 0 {
		return nil
	}

	// Find first alive instance
	for range n {
		idx := u.current.Add(1) % uint64(n)
		if u.APIInstances[idx].IsAlive() {
			return u.APIInstances[idx]
		}
	}
	return nil
}

// HealthCheck runs periodic health checks on all instances
func (u *Upstream) HealthCheck(ctx context.Context) {
	cfg := u.cfg.HealthCheck

	// Init transport
	var healthCheckTransport = &http.Transport{
		DialContext: (&net.Dialer{
			KeepAlive: cfg.KeepAlive,
		}).DialContext,
		MaxIdleConns:    cfg.MaxIdleConns,
		IdleConnTimeout: cfg.IdleConnTimeout,
	}

	client := &http.Client{Timeout: cfg.Timeout, Transport: healthCheckTransport}
	ticker := time.NewTicker(cfg.Interval)
	defer ticker.Stop()

	// Run check immediately on start
	u.checkAllInstances(client)

	for {
		select {
		case <-ctx.Done():
			logger.Info("Health Check stopped, waiting for active checks...")
			u.checkWg.Wait()
			logger.Info("All Health Check completed")
			return
		case <-ticker.C:
			u.checkAllInstances(client)
		}
	}
}

// checkAllInstances triggers health checks for all instances concurrently
func (u *Upstream) checkAllInstances(client *http.Client) {
	instances := u.getInstancesCopy()

	for _, i := range instances {
		u.checkWg.Add(1)
		go func(inst *APIInstance) {
			defer u.checkWg.Done()
			u.checkInstance(client, inst)
		}(i)
	}
}

// checkInstance performs a single health check on an instance
func (u *Upstream) checkInstance(client *http.Client, inst *APIInstance) {
	resp, err := client.Get(inst.URL.String() + u.cfg.HealthCheck.Path)
	if resp != nil {
		defer func() {
			if err := resp.Body.Close(); err != nil {
				logger.Warn("Error closing request body", "handler", "checkInstance", "error", err)
			}
		}()
	}

	isHealthy := err == nil && resp != nil && resp.StatusCode == http.StatusOK
	if isHealthy {
		inst.Touch()
		inst.CB.ForceClose()
		if !inst.IsAlive() && !inst.IsForcedUnalived() {
			inst.SetAlive()
			logger.Info("Reanimated", "instance", inst.URL.Host)
		}
	} else if inst.IsAlive() {
		inst.SetUnalive(false)
		logger.Warn("Expired", "instance", inst.URL.Host)
	}
}

// CleanupDeadInstances periodically removes expired instances
func (u *Upstream) CleanupDeadInstances(ctx context.Context) {
	ticker := time.NewTicker(u.cfg.CleanupInterval)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			u.removeDeadInstances()
		}
	}
}

// removeDeadInstances removes instances that have exceeded their TTL
func (u *Upstream) removeDeadInstances() {
	instances := u.getInstancesCopy()

	alive := make([]*APIInstance, 0, len(instances))
	for _, inst := range instances {
		if !inst.IsExpired() || inst.IsForcedUnalived() {
			alive = append(alive, inst)
		} else {
			logger.Warn("Removing expired instance", "instance", inst.URL.Host)
		}
	}

	u.mu.Lock()
	u.APIInstances = alive
	u.mu.Unlock()
}

// ReviveInstance clears the forced unalived flag and marks the instance as alive.
// This allows the instance to be used again for routing requests.
func (u *Upstream) ReviveInstance(hash string) bool {
	u.mu.Lock()
	defer u.mu.Unlock()

	for _, inst := range u.APIInstances {
		if inst.Hash == hash {
			inst.SetAlive()
			logger.Info("Instance revived", "instance", inst.URL.Host, "hash", hash)
			return true
		}
	}
	return false
}

// getInstancesCopy returns a thread-safe copy of the instances slice
func (u *Upstream) getInstancesCopy() []*APIInstance {
	u.mu.RLock()
	defer u.mu.RUnlock()
	return append([]*APIInstance(nil), u.APIInstances...)
}

// shortHash generates a short 8-character hash from a string.
func shortHash(s string) string {
	h := fnv.New64a()
	if _, err := h.Write([]byte(s)); err != nil {
		logger.Warn("Failed to write to hash", "pkg", "balancer", "func", "shortHash", "error", err)
	}
	return hex.EncodeToString(h.Sum(nil)[:4]) // 8 characters
}
