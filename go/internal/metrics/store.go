package metrics

import (
	"fmt"

	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promauto"
)

type Store struct {
	tasksCreated   *prometheus.CounterVec
	tasksCompleted *prometheus.CounterVec
	tasksFailed    *prometheus.CounterVec
	tasksRetried   *prometheus.CounterVec
}

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

func (s *Store) IncTasksCreated(count int, maxConcurrent int, mode string) {
	s.tasksCreated.With(prometheus.Labels{
		"max_concurrent": fmt.Sprintf("%03d", maxConcurrent),
		"mode":           mode,
	}).Add(float64(count))
}

func (s *Store) IncTasksCompleted(maxConcurrent int) {
	s.tasksCompleted.With(prometheus.Labels{
		"max_concurrent": fmt.Sprintf("%03d", maxConcurrent),
	}).Inc()
}

func (s *Store) IncTasksFailed(maxConcurrent int) {
	s.tasksFailed.With(prometheus.Labels{
		"max_concurrent": fmt.Sprintf("%03d", maxConcurrent),
	}).Inc()
}

func (s *Store) IncTasksRetried(maxConcurrent int) {
	s.tasksRetried.With(prometheus.Labels{
		"max_concurrent": fmt.Sprintf("%03d", maxConcurrent),
	}).Inc()
}
