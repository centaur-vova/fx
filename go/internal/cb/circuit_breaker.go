package cb

import (
	"sync/atomic"
	"time"
)

// CB states
const (
	StateClosed uint32 = iota
	StateOpen
	StateHalfOpen
)

const (
	maxConsecutiveFailures = 5
	coolDownDuration       = 10 * time.Second
)

type CircuitBreaker struct {
	state               atomic.Uint32
	consecutiveFailures atomic.Uint64
	lastStateChange     atomic.Int64
}

// RecordSuccess resets failures counter
// Returns true if CB has been just closed
func (cb *CircuitBreaker) RecordSuccess() bool {
	cb.consecutiveFailures.Store(0)
	if cb.state.Load() == StateHalfOpen {
		if cb.state.CompareAndSwap(StateHalfOpen, StateClosed) {
			return true
		}
	}
	return false
}

// RecordFailure increments failures counter
// Returns true if CB has been just opened
func (cb *CircuitBreaker) RecordFailure() bool {
	if cb.state.Load() == StateOpen {
		return false
	}

	failures := cb.consecutiveFailures.Add(1)
	currentState := cb.state.Load()

	if failures >= maxConsecutiveFailures || currentState == StateHalfOpen {
		cb.state.Store(StateOpen)
		cb.lastStateChange.Store(time.Now().UnixNano())
		return true
	}
	return false
}

// CanRequest checks if request can be passed
// Returns true if request is allowed, and a flag if HalfOpen state
func (cb *CircuitBreaker) CanRequest() (bool, bool) {
	currentState := cb.state.Load()
	if currentState == StateClosed {
		return true, false
	}

	if currentState == StateOpen {
		lastChange := cb.lastStateChange.Load()
		if time.Now().UnixNano() > lastChange+coolDownDuration.Nanoseconds() {
			if cb.state.CompareAndSwap(StateOpen, StateHalfOpen) {
				return true, true // Enable this request, switching to Half-Open
			}
		}
		return false, false
	}

	return false, false
}

// CB ForceClose
func (cb *CircuitBreaker) ForceClose() {
	cb.consecutiveFailures.Store(0)
	cb.state.Store(StateClosed)
}

// GetState returns current state (monitoring/handlers)
func (cb *CircuitBreaker) GetState() uint32 {
	return cb.state.Load()
}
