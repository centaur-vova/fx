package semaphore

import (
	"testing"
)

func TestNewSemaphore(t *testing.T) {
	c := 5

	s := NewSemaphore(c)

	if s == nil {
		t.Fatal("expected non-nil Semaphore")
	}

	if cap(s.slots) != c {
		t.Errorf("capacity = %d, want %d", cap(s.slots), c)
	}
}
