package protocol

import (
	"flag"
	"log"
	"os"
	"regexp"
	"runtime"
	"strconv"
	"strings"
)

type AppConfig struct {
	// API & Balancer
	APIPort  string
	APIToken string

	BalancerPort    string
	BalancerApiURLs []string

	// WS
	WSPort string

	// Logging
	LogLevel string

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

func LoadConfig() *AppConfig {
	queueCap, _ := strconv.Atoi(os.Getenv("QUEUE_CAPACITY"))
	workerNum, _ := strconv.Atoi(os.Getenv("SERVER_WORKER_NUM"))
	version, buildDate := getVersion()
	ms, _ := strconv.Atoi(getEnv("METRICS_UPDATE_INTERVAL_MS", "1000"))

	// Try getting command line arguments to override api token/port from .env
	tokenFlag := flag.String("token", getEnv("API_TOKEN", ""), "Authentication token")
	portFlag := flag.String("port", getEnv("API_PORT", "8081"), "API port")
	// Balancer (sharing port setting)
	balancerPortFlag := flag.String("balancer-port", getEnv("BALANCER_PORT", "8090"), "Balancer ingress port")
	urlsFlag := flag.String("upstream", getEnv("BALANCER_API_URLS", ""), "Comma-separated list of backend URLs")

	flag.Parse()

	// Parse balancer's API urls
	var apiURLs []string
	for rawURL := range strings.SplitSeq(*urlsFlag, ",") {
		trimmed := strings.TrimSpace(rawURL)
		if trimmed != "" {
			apiURLs = append(apiURLs, trimmed)
		}
	}

	// Fail-Fast: if no apiURLs provided for the balancer - force quit
	if len(apiURLs) == 0 {
		log.Fatal("SNAFUBAR: upstream URL list is empty. Check configuration")
	}

	return &AppConfig{
		APIToken: *tokenFlag,
		APIPort:  *portFlag,

		BalancerPort:    *balancerPortFlag,
		BalancerApiURLs: apiURLs,

		WSPort: getEnv("WS_PORT", "8080"),

		LogLevel: getEnv("LOG_LEVEL", "info"),

		NatsURL:     getEnv("NATS_HOST", "localhost") + ":" + getEnv("NATS_PORT", "4222"),
		NatsToken:   getEnv("NATS_TOKEN", ""),
		StreamCh:    getEnv("NATS_STREAM_TASKS", "tasks"),
		BroadcastCh: getEnv("NATS_SUBJECT_BROADCAST", "v1.ws.broadcast"),

		WorkerNum:     workerNum,
		CPUCores:      runtime.NumCPU(),
		QueueCapacity: queueCap,
		AppVersion:    version,
		BuildDate:     buildDate,

		MetricsUpdateIntervalMs: ms,
	}
}

func (c *AppConfig) Validate() {
	if !natsChannelRegex.MatchString(c.BroadcastCh) {
		log.Fatalf("Invalid NATS channel name: '%s'. Only a-z, A-Z, 0-9, . and _ are allowed.", c.BroadcastCh)
	}
	if !natsChannelRegex.MatchString(c.StreamCh) {
		log.Fatalf("Invalid NATS channel name: '%s'. Only a-z, A-Z, 0-9, . and _ are allowed.", c.StreamCh)
	}
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}
