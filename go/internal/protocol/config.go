// Package protocol provides configuration loading and data structures
// for WebSocket, NATS, Balancer, and API services.
package protocol

import (
	"flag"
	"log"
	"net/url"
	"os"
	"regexp"
	"runtime"
	"strconv"
	"strings"
)

// BaseConfig contains common configuration fields for all services.
type BaseConfig struct {
	LogLevel string
}

// WSConfig holds configuration for the WebSocket gateway service.
type WSConfig struct {
	BaseConfig

	// WS
	WSPort string

	// NATS infrastructure
	NatsURL     string
	NatsToken   string
	StreamCh    string
	BroadcastCh string

	// Public data
	WorkerNum     int
	CPUCores      int
	QueueCapacity int
	AppVersion    string
	BuildDate     string

	// Misc
	MetricsUpdateIntervalMs int
}

// natsChannelRegex validates NATS channel names.
var natsChannelRegex = regexp.MustCompile(`^[a-zA-Z0-9\._]+$`)

// getVersion reads version info from /version.txt file.
func getVersion() (string, string) {
	data, err := os.ReadFile("/version.txt")
	if err != nil {
		return "dev", "n/a"
	}

	versionInfo := strings.Split(string(data), ";")
	if len(versionInfo) < 2 {
		return "dev", "n/a"
	}

	return strings.TrimSpace(versionInfo[0]), strings.TrimSpace(versionInfo[1])
}

// LoadWSConfig loads WebSocket gateway configuration from environment variables.
func LoadWSConfig() *WSConfig {
	queueCap, _ := strconv.Atoi(os.Getenv("QUEUE_CAPACITY"))
	workerNum, _ := strconv.Atoi(os.Getenv("SERVER_WORKER_NUM"))
	version, buildDate := getVersion()
	ms, _ := strconv.Atoi(getEnv("METRICS_UPDATE_INTERVAL_MS", "1000"))

	return &WSConfig{
		BaseConfig: BaseConfig{
			LogLevel: getEnv("LOG_LEVEL", "info"),
		},

		WSPort:                  getEnv("WS_PORT", "8080"),
		NatsURL:                 getEnv("NATS_HOST", "localhost") + ":" + getEnv("NATS_PORT", "4222"),
		NatsToken:               getEnv("NATS_TOKEN", ""),
		StreamCh:                getEnv("NATS_STREAM_TASKS", "tasks"),
		BroadcastCh:             getEnv("NATS_SUBJECT_BROADCAST", "v1.ws.broadcast"),
		WorkerNum:               workerNum,
		CPUCores:                runtime.NumCPU(),
		QueueCapacity:           queueCap,
		AppVersion:              version,
		BuildDate:               buildDate,
		MetricsUpdateIntervalMs: ms,
	}
}

// Validate checks that NATS channel names are valid.
func (c *WSConfig) Validate() {
	if !natsChannelRegex.MatchString(c.BroadcastCh) {
		log.Fatalf("Invalid NATS broadcast channel: %s", c.BroadcastCh)
	}
	if !natsChannelRegex.MatchString(c.StreamCh) {
		log.Fatalf("Invalid NATS stream channel: %s", c.StreamCh)
	}
}

// === BALANCER CONFIG ===

// BalancerConfig holds configuration for the load balancer service.
type BalancerConfig struct {
	BaseConfig

	APIAuthKey     string
	BalancerAPIKey string
	BalancerPort   string
}

// LoadBalancerConfig loads balancer configuration from environment.
func LoadBalancerConfig() *BalancerConfig {
	apiURL := getEnv("API_URL", "http://localhost:8090")

	balancerPort := "8090"
	if parsed, err := url.Parse(apiURL); err == nil {
		if p := parsed.Port(); p != "" {
			balancerPort = p
		}
	}

	return &BalancerConfig{
		BaseConfig: BaseConfig{
			LogLevel: getEnv("LOG_LEVEL", "info"),
		},

		BalancerAPIKey: os.Getenv("BALANCER_API_KEY"),
		APIAuthKey:     os.Getenv("API_AUTH_KEY"),
		BalancerPort:   balancerPort,
	}
}

// === API INSTANCE CONFIG ===

// APIConfig holds configuration for the API service.
type APIConfig struct {
	BaseConfig

	APIPort        string
	APIAuthKey     string
	BalancerAPIKey string
	BalancerURL    string

	JWTSecret string

	RedisURL string

	NatsURL     string
	NatsToken   string
	BroadcastCh string
}

// LoadAPIConfig loads API service configuration from environment and CLI flags.
func LoadAPIConfig() *APIConfig {
	apiPortFlag := flag.String("port", getEnv("API_PORT", "8081"), "API service port")
	flag.Parse()

	port := *apiPortFlag

	balancerURL := getEnv("API_URL", "http://localhost:8090")
	redisURL := getEnv("REDIS_URL", "redis:6379")

	return &APIConfig{
		BaseConfig: BaseConfig{
			LogLevel: getEnv("LOG_LEVEL", "info"),
		},

		APIPort:        port,
		APIAuthKey:     os.Getenv("API_AUTH_KEY"),
		BalancerAPIKey: os.Getenv("BALANCER_API_KEY"),
		BalancerURL:    balancerURL,

		JWTSecret: os.Getenv("JWT_SECRET"),

		RedisURL: redisURL,

		NatsURL:     getEnv("NATS_HOST", "localhost") + ":" + getEnv("NATS_PORT", "4222"),
		NatsToken:   getEnv("NATS_TOKEN", ""),
		BroadcastCh: getEnv("NATS_SUBJECT_BROADCAST", "v1.ws.broadcast"),
	}
}

// getEnv returns environment variable value or default if not set.
func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}
