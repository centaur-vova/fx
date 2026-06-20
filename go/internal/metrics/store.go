// Package metrics provides Prometheus metrics for task processing.
package metrics

import (
	"fmt"

	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promauto"
)

// Store holds Prometheus metrics for task processing.
type Store struct {
	tasksCreated   *prometheus.CounterVec // total created tasks partitioned by max_concurrent and mode
	tasksCompleted *prometheus.CounterVec // total completed tasks partitioned by max_concurrent
	tasksFailed    *prometheus.CounterVec // total failed tasks partitioned by max_concurrent
	tasksRetried   *prometheus.CounterVec // total retried tasks partitioned by max_concurrent
}

// NewStore creates and registers Prometheus metrics for Fast AF.
func NewStore() *Store {
	s := &Store{
		tasksCreated: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Name: "fast_af_tasks_created_total",
				Help: "Total number of tasks created, partitioned by max_concurrent and stress_mode.",
			},
			[]string{"max_concurrent", "mode"},
		),
		tasksCompleted: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Name: "fast_af_tasks_completed_total",
				Help: "Total number of tasks completed, partitioned by max_concurrent",
			},
			[]string{"max_concurrent"},
		),
		tasksFailed: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Name: "fast_af_tasks_failed_total",
				Help: "Total number of tasks failed, partitioned by max_concurrent",
			},
			[]string{"max_concurrent"},
		),
		tasksRetried: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Name: "fast_af_tasks_retried_total",
				Help: "Total number of tasks retries, partitioned by max_concurrent",
			},
			[]string{"max_concurrent"},
		),
	}
	return s
}

// IncTasksCreated increments the counter for created tasks.
func (s *Store) IncTasksCreated(count int, maxConcurrent int, mode string) {
	s.tasksCreated.With(prometheus.Labels{
		"max_concurrent": labelMaxConcurrent(maxConcurrent),
		"mode":           mode,
	}).Add(float64(count))
}

// IncTasksCompleted increments the counter for completed tasks.
func (s *Store) IncTasksCompleted(maxConcurrent int) {
	s.tasksCompleted.With(prometheus.Labels{
		"max_concurrent": labelMaxConcurrent(maxConcurrent),
	}).Inc()
}

// IncTasksFailed increments the counter for failed tasks.
func (s *Store) IncTasksFailed(maxConcurrent int) {
	s.tasksFailed.With(prometheus.Labels{
		"max_concurrent": labelMaxConcurrent(maxConcurrent),
	}).Inc()
}

// IncTasksRetried increments the counter for retried tasks.
func (s *Store) IncTasksRetried(maxConcurrent int) {
	s.tasksRetried.With(prometheus.Labels{
		"max_concurrent": labelMaxConcurrent(maxConcurrent),
	}).Inc()
}

// labelMaxConcurrent formats max_concurrent as a 3-digit zero-padded string.
func labelMaxConcurrent(mc int) string {
	return fmt.Sprintf("%03d", mc)
}
