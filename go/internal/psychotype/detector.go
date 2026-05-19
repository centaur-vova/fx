package psychotype

import (
	"bufio"
	"fast-atomic-flow/go/internal/logger"
	"hash/fnv"
	"net"
	"os"
	"strings"
)

type Detector struct {
	workers []*net.IPNet
	wankers []*net.IPNet
}

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
	h.Write([]byte(ip))
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

	file, err := os.Open(filepath)
	if err != nil {
		// no wankers found
		return nil, nil
	}
	defer file.Close()

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
