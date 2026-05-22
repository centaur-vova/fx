package cb

import (
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestCircuitBreaker_InitialState(t *testing.T) {
	cb := &CircuitBreaker{}
	assert.Equal(t, StateClosed, cb.GetState())
}

// ========== CanRequest ==========

func TestCircuitBreaker_CanRequest_Closed(t *testing.T) {
	cb := &CircuitBreaker{}
	allowed, halfOpen := cb.CanRequest()
	assert.True(t, allowed)
	assert.False(t, halfOpen)
}

func TestCircuitBreaker_CanRequest_Open(t *testing.T) {
	cb := &CircuitBreaker{}
	// Force open
	for range maxConsecutiveFailures {
		cb.RecordFailure()
	}
	assert.Equal(t, StateOpen, cb.GetState())

	// Should be blocked
	allowed, _ := cb.CanRequest()
	assert.False(t, allowed)
}

func TestCircuitBreaker_CanRequest_HalfOpen(t *testing.T) {
	cb := &CircuitBreaker{}
	// Force open
	for range maxConsecutiveFailures {
		cb.RecordFailure()
	}
	// Fast-forward past cooldown
	cb.lastStateChange.Store(time.Now().Add(-coolDownDuration - time.Second).UnixNano())

	allowed, halfOpen := cb.CanRequest()
	assert.True(t, allowed)
	assert.True(t, halfOpen)
	assert.Equal(t, StateHalfOpen, cb.GetState())
}

// ========== RecordSuccess ==========

func TestCircuitBreaker_RecordSuccess_HalfOpenToClosed(t *testing.T) {
	cb := &CircuitBreaker{}
	// Force open then half-open
	for range maxConsecutiveFailures {
		cb.RecordFailure()
	}
	cb.lastStateChange.Store(time.Now().Add(-coolDownDuration - time.Second).UnixNano())
	cb.CanRequest() // switch to half-open

	closed := cb.RecordSuccess()
	assert.True(t, closed)
	assert.Equal(t, StateClosed, cb.GetState())
}

func TestCircuitBreaker_RecordSuccess_Closed(t *testing.T) {
	cb := &CircuitBreaker{}
	closed := cb.RecordSuccess()
	assert.False(t, closed) // already closed, no transition
}

// ========== RecordFailure ==========

func TestCircuitBreaker_RecordFailure_OpensAfterMax(t *testing.T) {
	cb := &CircuitBreaker{}

	// Below threshold — should not open
	for range maxConsecutiveFailures - 1 {
		opened := cb.RecordFailure()
		assert.False(t, opened)
	}
	assert.Equal(t, StateClosed, cb.GetState())

	// Last failure opens
	opened := cb.RecordFailure()
	assert.True(t, opened)
	assert.Equal(t, StateOpen, cb.GetState())
}

func TestCircuitBreaker_RecordFailure_HalfOpenOpensImmediately(t *testing.T) {
	cb := &CircuitBreaker{}
	// Force half-open
	for range maxConsecutiveFailures {
		cb.RecordFailure()
	}
	cb.lastStateChange.Store(time.Now().Add(-coolDownDuration - time.Second).UnixNano())
	cb.CanRequest()
	assert.Equal(t, StateHalfOpen, cb.GetState())

	// Single failure in half-open should open immediately
	opened := cb.RecordFailure()
	assert.True(t, opened)
	assert.Equal(t, StateOpen, cb.GetState())
}

func TestCircuitBreaker_RecordFailure_AlreadyOpen(t *testing.T) {
	cb := &CircuitBreaker{}
	for range maxConsecutiveFailures {
		cb.RecordFailure()
	}
	assert.Equal(t, StateOpen, cb.GetState())

	// Failure in open state — should return false (already open)
	opened := cb.RecordFailure()
	assert.False(t, opened)
}

// ========== ForceClose ==========

func TestCircuitBreaker_ForceClose(t *testing.T) {
	cb := &CircuitBreaker{}
	for range maxConsecutiveFailures {
		cb.RecordFailure()
	}
	assert.Equal(t, StateOpen, cb.GetState())

	cb.ForceClose()
	assert.Equal(t, StateClosed, cb.GetState())
	allowed, _ := cb.CanRequest()
	assert.True(t, allowed)
}
