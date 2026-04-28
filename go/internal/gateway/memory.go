package gateway

import (
	"fmt"
	"os"
	"strconv"
	"strings"
)

func GetVmRSS() (float64, error) {
	data, err := os.ReadFile("/proc/self/status")
	if err != nil {
		return 0, err
	}

	lines := strings.Split(string(data), "\n")
	for _, line := range lines {
		if strings.HasPrefix(line, "VmRSS:") {
			fields := strings.Fields(line)
			if len(fields) >= 2 {
				kb, err := strconv.Atoi(fields[1])
				if err != nil {
					return 0, err
				}
				return float64(kb) / 1024, nil // KB → MB
			}
		}
	}
	return 0, fmt.Errorf("VmRSS not found")
}

func GetFreeMemory() (float64, error) {
	data, err := os.ReadFile("/proc/meminfo")
	if err != nil {
		return 0, err
	}

	lines := strings.Split(string(data), "\n")
	for _, line := range lines {
		if strings.HasPrefix(line, "MemAvailable:") {
			fields := strings.Fields(line)
			if len(fields) >= 2 {
				kb, err := strconv.Atoi(fields[1])
				if err != nil {
					return 0, err
				}
				return float64(kb) / 1024, nil
			}
		}
	}
	return 0, fmt.Errorf("MemAvailable not found")
}
