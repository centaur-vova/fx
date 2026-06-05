// Package logger provides structured logging with slog and custom severity levels.
package logger

import (
	"context"
	"log/slog"
	"os"
	"strings"
)

// Log levels with severity: LevelTrace, LevelNotice, LevelCritical, LevelAlert, LevelEmergency.
const (
	LevelTrace     = slog.LevelDebug - 2 // -6
	LevelNotice    = slog.LevelInfo + 2  // 2
	LevelCritical  = slog.LevelError + 2 // 10
	LevelAlert     = slog.LevelError + 4 // 12
	LevelEmergency = slog.LevelError + 8 // 16
)

// LevelMap maps log level names to slog.Level values.
var LevelMap = map[string]slog.Level{
	"trace":     LevelTrace,
	"debug":     slog.LevelDebug,
	"info":      slog.LevelInfo,
	"notice":    LevelNotice,
	"warning":   slog.LevelWarn,
	"error":     slog.LevelError,
	"critical":  LevelCritical,
	"alert":     LevelAlert,
	"emergency": LevelEmergency,
}

// Init initializes the global slog logger with the specified level.
func Init(levelName string) {
	level, ok := LevelMap[strings.ToLower(levelName)]
	if !ok {
		level = slog.LevelInfo
	}

	handler := slog.NewTextHandler(os.Stdout, &slog.HandlerOptions{
		Level: level,
		ReplaceAttr: func(_ []string, a slog.Attr) slog.Attr {
			if a.Key == slog.LevelKey {
				level := a.Value.Any().(slog.Level)
				switch level {
				case LevelTrace:
					a.Value = slog.StringValue("TRACE")
				case slog.LevelDebug:
					a.Value = slog.StringValue("DEBUG")
				case slog.LevelInfo:
					a.Value = slog.StringValue("INFO")
				case LevelNotice:
					a.Value = slog.StringValue("NOTICE")
				case slog.LevelWarn:
					a.Value = slog.StringValue("WARN")
				case slog.LevelError:
					a.Value = slog.StringValue("ERROR")
				case LevelCritical:
					a.Value = slog.StringValue("CRITICAL")
				case LevelAlert:
					a.Value = slog.StringValue("ALERT")
				case LevelEmergency:
					a.Value = slog.StringValue("EMERGENCY")
				}
			}
			return a
		},
	})
	slog.SetDefault(slog.New(handler))
}

// Debug logs a message at debug level.
func Debug(msg string, args ...any) {
	slog.Debug(msg, args...)
}

// Info logs a message at info level.
func Info(msg string, args ...any) {
	slog.Info(msg, args...)
}

// Warn logs a message at warn level.
func Warn(msg string, args ...any) {
	slog.Warn(msg, args...)
}

// Error logs a message at error level.
func Error(msg string, args ...any) {
	slog.Error(msg, args...)
}

// === CUSTOM LEVELS HELPERS ===

// Trace logs a message at trace level (below debug).
func Trace(msg string, args ...any) {
	slog.Log(context.Background(), LevelTrace, msg, args...)
}

// Notice logs a message at notice level (between info and warn).
func Notice(msg string, args ...any) {
	slog.Log(context.Background(), LevelNotice, msg, args...)
}

// Critical logs a message at critical level (between error and alert).
func Critical(msg string, args ...any) {
	slog.Log(context.Background(), LevelCritical, msg, args...)
}

// Alert logs a message at alert level (above error, below emergency).
func Alert(msg string, args ...any) {
	slog.Log(context.Background(), LevelAlert, msg, args...)
}

// Emergency logs a message at emergency level (highest severity).
func Emergency(msg string, args ...any) {
	slog.Log(context.Background(), LevelEmergency, msg, args...)
}
