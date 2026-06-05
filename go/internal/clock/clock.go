// Package clock provides a testable time abstraction.
package clock

import "time"

// Clock defines an interface for time operations.
type Clock interface {
	Now() time.Time
}

// RealClock returns the actual system time.
type RealClock struct{}

// Now returns the current system time.
func (RealClock) Now() time.Time { return time.Now() }
