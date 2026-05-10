package logger

import (
	"log/slog"
	"os"
	"strings"
)

var LevelMap = map[string]slog.Level{
	"debug":     slog.LevelDebug,
	"info":      slog.LevelInfo,
	"notice":    slog.LevelInfo,
	"warning":   slog.LevelWarn,
	"error":     slog.LevelError,
	"critical":  slog.LevelError + 2,
	"alert":     slog.LevelError + 4,
	"emergency": slog.LevelError + 8,
}

func Init(levelName string) {
	level, ok := LevelMap[strings.ToLower(levelName)]
	if !ok {
		level = slog.LevelInfo
	}
	logger := slog.New(slog.NewTextHandler(os.Stdout, &slog.HandlerOptions{Level: level}))
	slog.SetDefault(logger)
}
