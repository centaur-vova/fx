package protocol

import (
	"fast-atomic-flow/go/internal/logger"
	"flag"
	"net/url"
	"os"
	"regexp"
	"runtime"
	"strconv"
	"strings"
)

type BaseConfig struct {
	LogLevel string
}

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

var natsChannelRegex = regexp.MustCompile(`^[a-zA-Z0-9\._]+$`)

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

func (c *WSConfig) Validate() {
	if !natsChannelRegex.MatchString(c.BroadcastCh) {
		logger.Emergency("💥 Invalid NATS channel name", "channel", c.BroadcastCh)
		os.Exit(1)
	}
	if !natsChannelRegex.MatchString(c.StreamCh) {
		logger.Emergency("💥 Invalid NATS channel name", "channel", c.StreamCh)
		os.Exit(1)
	}
}

// === BALANCER CONFIG ===
type BalancerConfig struct {
	BaseConfig

	APIToken     string
	BalancerPort string
}

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

		APIToken:     os.Getenv("API_TOKEN"),
		BalancerPort: balancerPort,
	}
}

// === API INSTANCE CONFIG ===
type APIConfig struct {
	BaseConfig

	APIToken    string
	BalancerURL string
	APIPort     string
	RedisURL    string

	NatsURL     string
	NatsToken   string
	BroadcastCh string
}

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

		APIToken:    os.Getenv("API_TOKEN"),
		BalancerURL: balancerURL,
		APIPort:     port,
		RedisURL:    redisURL,

		NatsURL:     getEnv("NATS_HOST", "localhost") + ":" + getEnv("NATS_PORT", "4222"),
		NatsToken:   getEnv("NATS_TOKEN", ""),
		BroadcastCh: getEnv("NATS_SUBJECT_BROADCAST", "v1.ws.broadcast"),
	}
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}
