package balancer

import (
	"fast-atomic-flow/go/internal/clock"
	"net/http"
	"net/http/httptest"
	"net/url"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

// ========== Counts ==========
func TestUpstream_Counts(t *testing.T) {
	u := &Upstream{}
	u.RegisterInstance("http://a:8081")
	u.RegisterInstance("http://b:8082")
	u.RegisterInstance("http://c:8083")

	// Unalive one instance
	u.APIInstances[1].SetUnalive(false)

	up, down := u.Counts()
	assert.Equal(t, uint64(2), up)
	assert.Equal(t, uint64(1), down)

	// Unalive another instance
	u.APIInstances[2].SetUnalive(false)
	up, down = u.Counts()
	assert.Equal(t, uint64(1), up)
	assert.Equal(t, uint64(2), down)

	// Everyone's dead :(
	u.APIInstances[0].SetUnalive(false)
	up, down = u.Counts()
	assert.Equal(t, uint64(0), up)
	assert.Equal(t, uint64(3), down)
}

// ========== RegisterInstance ==========

func TestUpstream_RegisterInstance_InvalidUrl(t *testing.T) {
	u := &Upstream{}
	u.RegisterInstance("yes://[::1")

	assert.Zero(t, len(u.APIInstances))
}

func TestUpstream_RegisterInstance_Duplicate(t *testing.T) {
	u := &Upstream{}
	u.RegisterInstance("http://a:8081")
	u.RegisterInstance("http://A:8081")
	u.RegisterInstance("http://a:8081")

	// Only one instance should be kept
	assert.Equal(t, 1, len(u.APIInstances))
	// And it should be alive
	assert.True(t, u.APIInstances[0].IsAlive())
}

// ========== NextInstance ==========
func TestUpstream_NextInstance_Empty(t *testing.T) {
	u := &Upstream{}
	assert.Nil(t, u.NextInstance())
}

func TestUpstream_NextInstance_OnlyDead(t *testing.T) {
	u := &Upstream{}
	u.RegisterInstance("http://a:8081")
	u.APIInstances[0].SetUnalive(false)
	assert.Nil(t, u.NextInstance())
}

func TestUpstream_NextInstance_RoundRobin(t *testing.T) {
	u := &Upstream{}
	u.RegisterInstance("http://a:8081")
	u.RegisterInstance("http://b:8082")

	first := u.NextInstance()
	second := u.NextInstance()
	third := u.NextInstance()

	assert.NotNil(t, first)
	assert.NotNil(t, second)
	assert.NotSame(t, first, second)
	assert.Same(t, first, third) // cycled back
}

func TestUpstream_NextInstance_SkipsDead(t *testing.T) {
	u := &Upstream{}
	u.RegisterInstance("http://a:8081")
	u.RegisterInstance("http://b:8082")
	u.RegisterInstance("http://c:8083")

	// Kill two
	u.APIInstances[0].SetUnalive(false)
	u.APIInstances[2].SetUnalive(false)

	// All calls should return the only alive one
	for range 5 {
		inst := u.NextInstance()
		assert.NotNil(t, inst)
		assert.Equal(t, "http://b:8082", inst.URL.String())
	}
}

// ========== CheckInstance ==========
func TestUpstream_CheckInstance_Healthy(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/health" {
			w.WriteHeader(http.StatusOK)
		}
	}))
	defer server.Close()

	u := NewUpstream(Config{
		HealthCheck: HealthCheckConfig{Path: "/health"},
	})
	inst := &APIInstance{URL: parseURL(t, server.URL), clock: clock.RealClock{}}
	inst.SetAlive()

	client := server.Client()
	u.checkInstance(client, inst)

	assert.True(t, inst.IsAlive())
}

func TestUpstream_CheckInstance_Unhealthy(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusInternalServerError)
	}))
	defer server.Close()

	u := NewUpstream(Config{
		HealthCheck: HealthCheckConfig{Path: "/health"},
	})
	inst := &APIInstance{URL: parseURL(t, server.URL), clock: clock.RealClock{}}
	inst.SetAlive()

	client := server.Client()
	u.checkInstance(client, inst)

	assert.False(t, inst.IsAlive())
}

// ========== CheckAllInstances ==========

func TestUpstream_CheckAllInstances(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))
	defer server.Close()

	u := NewUpstream(Config{
		HealthCheck: HealthCheckConfig{Path: "/health"},
	})
	u.RegisterInstance(server.URL)
	u.RegisterInstance(server.URL + "/other")

	client := server.Client()
	u.checkAllInstances(client)

	up, down := u.Counts()
	assert.Equal(t, uint64(2), up)
	assert.Equal(t, uint64(0), down)
}

// ========== RemoveDeadInstances ==========
func TestUpstream_RemoveDeadInstances(t *testing.T) {
	u := NewUpstream(Config{
		InstanceTTL:     100 * time.Millisecond,
		CleanupInterval: time.Hour,
	})

	u.RegisterInstance("http://a:8081")
	u.RegisterInstance("http://b:8082")

	// Manually expire first instance
	u.APIInstances[0].ExpiresAt.Store(time.Now().Add(-1 * time.Second).UnixNano())

	u.removeDeadInstances()

	assert.Equal(t, 1, len(u.APIInstances))
	assert.Equal(t, "http://b:8082", u.APIInstances[0].URL.String())
}

func TestUpstream_RemoveDeadInstances_AllAlive(t *testing.T) {
	u := NewUpstream(Config{
		InstanceTTL: time.Hour,
	})

	u.RegisterInstance("http://a:8081")
	u.RegisterInstance("http://b:8082")

	u.removeDeadInstances()

	assert.Equal(t, 2, len(u.APIInstances))
}

// Helper func
func parseURL(t *testing.T, raw string) *url.URL {
	t.Helper()
	parsed, err := url.Parse(raw)
	assert.NoError(t, err)
	return parsed
}
