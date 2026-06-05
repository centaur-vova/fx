// Package psychotype provides IP-based client classification for WebSocket connections.
// It categorizes clients as "Workers", "Wankers", or "Wonkers" based on configurable
// allow/block lists and deterministic hash fallback.
package psychotype

import (
	"bufio"
	"fast-atomic-flow/go/internal/logger"
	"hash/fnv"
	"net"
	"os"
	"strings"
)

// Detector determines client psychotype based on IP address.
// It classifies clients into "Workers", "Wankers", or "Wonkers".
type Detector struct {
	workers []*net.IPNet
	wankers []*net.IPNet
}

// NewDetector creates a Detector and loads IP lists from config files.
// It logs warnings if config files are missing but does not fail.
func NewDetector() *Detector {
	workers, err := loadConfig("config/.workers.conf")
	if err != nil {
		logger.Warn("Workers config not loaded", "error", err)
	}

	wankers, err := loadConfig("config/.wankers.conf")
	if err != nil {
		logger.Warn("Wankers config not loaded", "error", err)
	}

	return &Detector{
		workers: workers,
		wankers: wankers,
	}
}

// Type returns the psychotype of the client based on its IP address.
// It first checks against known troublemakers (Wankers) and allowed ones (Workers).
// If not found, it assigns a deterministic random type based on IP hash:
// "Wankers", "Wonkers", or "Workers".
func (d *Detector) Type(remoteAddr string) string {
	ip, _, err := net.SplitHostPort(remoteAddr)
	if err != nil {
		ip = remoteAddr
	}

	// Wankers first
	if inList(ip, d.wankers) {
		return "Wankers"
	}
	if inList(ip, d.workers) {
		return "Workers"
	}

	// IP based psychotype
	h := fnv.New32a()
	if _, err = h.Write([]byte(ip)); err != nil {
		logger.Warn("Failed to write to hash", "pkg", "psychotype", "func", "Type", "error", err)
	}

	switch h.Sum32() % 3 {
	case 0:
		return "Wankers"
	case 1:
		return "Wonkers"
	default:
		return "Workers"
	}
}

func loadConfig(filepath string) ([]*net.IPNet, error) {
	subnets := []*net.IPNet{}

	file, err := os.Open(filepath) //nolint:gosec
	if err != nil {
		// no wankers found
		return nil, nil
	}
	defer func() {
		if err := file.Close(); err != nil {
			logger.Warn("Error closing file", "filepath", filepath, "error", err)
		}
	}()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		// Skip comments & empty lines
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}

		// Subnet mask if not provided
		if !strings.Contains(line, "/") {
			line += "/32"
		}

		if _, subnet, err := net.ParseCIDR(line); err == nil {
			subnets = append(subnets, subnet)
		}
	}

	return subnets, scanner.Err()
}

func inList(ipStr string, subnets []*net.IPNet) bool {
	clientIP := net.ParseIP(ipStr)
	if clientIP == nil {
		return false
	}
	for _, subnet := range subnets {
		if subnet.Contains(clientIP) {
			return true
		}
	}
	return false
}
