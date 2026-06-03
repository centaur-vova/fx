package semaphore

import (
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestSlotUID_Roundtrip(t *testing.T) {
	uid := NewSlotUID(5, 3)
	mc, idx := uid.Parse()
	assert.Equal(t, 5, mc)
	assert.Equal(t, 3, idx)
	assert.Equal(t, "5:3", uid.String())
}
