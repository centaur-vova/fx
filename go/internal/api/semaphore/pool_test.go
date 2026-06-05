package semaphore

import (
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestSlotUID_Roundtrip(t *testing.T) {
	uid := NewSlotUID(5, 3)
	mc, idx, err := uid.Parse()
	assert.Equal(t, 5, mc)
	assert.Equal(t, 3, idx)
	assert.Equal(t, nil, err)
	assert.Equal(t, "5:3", uid.String())
}

func TestSlotUID_ParseError(t *testing.T) {
	tests := []struct {
		name string
		uid  SlotUID
	}{
		{"invalid format no colon", SlotUID("53")},
		{"invalid format letters", SlotUID("abc:def")},
		{"invalid format missing second", SlotUID("5:")},
		{"invalid format missing first", SlotUID(":3")},
		{"empty", SlotUID("")},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			mc, idx, err := tt.uid.Parse()
			assert.Error(t, err, "should return error for invalid SlotUID")
			assert.Equal(t, 0, mc, "mc should be zero on error")
			assert.Equal(t, 0, idx, "slotIdx should be zero on error")
		})
	}
}
