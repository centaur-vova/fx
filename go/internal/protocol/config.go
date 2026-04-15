package protocol

import (
	"log"
	"os"
	"regexp"
	"runtime"
	"strconv"
)

type AppConfig struct {
	WSPort string

	// NATS infrastructure
	NatsURL     string
	NatsToken   string
	BroadcastCh string

	// Public data
	WorkerNum     int
	CPUCores      int
	QueueCapacity int
	AppVersion    string
}

var natsChannelRegex = regexp.MustCompile(`^[a-zA-Z0-9\._]+$`)

func LoadConfig() *AppConfig {
	queueCap, _ := strconv.Atoi(os.Getenv("QUEUE_CAPACITY"))
	workerNum, _ := strconv.Atoi(os.Getenv("SERVER_WORKER_NUM"))

	version := os.Getenv("APP_VERSION")
	if version == "" {
		version = "dev"
	}

	return &AppConfig{
		WSPort: getEnv("WS_PORT", "8080"),

		NatsURL:     getEnv("NATS_HOST", "localhost") + ":" + getEnv("NATS_PORT", "4222"),
		NatsToken:   os.Getenv("NATS_TOKEN"),
		BroadcastCh: getEnv("NATS_SUBJECT_BROADCAST", "v1.ws.broadcast"),

		WorkerNum:     workerNum,
		CPUCores:      runtime.NumCPU(),
		QueueCapacity: queueCap,
		AppVersion:    version,
	}
}

func (c *AppConfig) Validate() {
	if !natsChannelRegex.MatchString(c.BroadcastCh) {
		log.Fatalf("Invalid NATS channel name: '%s'. Only a-z, A-Z, 0-9, . and _ are allowed.", c.BroadcastCh)
	}
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}
