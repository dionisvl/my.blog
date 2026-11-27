package repository

import (
	"testing"
	"time"
)

func TestQuoteSlot(t *testing.T) {
	const n = 995
	before := time.Date(2026, time.November, 27, 23, 59, 59, 0, time.UTC)
	after := before.Add(time.Second)
	start := quoteSlot(before, 42, n)
	for _, c := range []struct {
		name     string
		at       time.Time
		id, want int64
	}{
		{"same day", before.Add(-time.Hour), 42, start},
		{"next article", before, 43, (start + 1) % n},
		{"midnight UTC", after, 42, (start + 1) % n},
		{"end of rotation", before.AddDate(0, 0, n), 42, start},
	} {
		t.Run(c.name, func(t *testing.T) {
			if got := quoteSlot(c.at, int(c.id), n); got != c.want {
				t.Fatalf("slot %d, want %d", got, c.want)
			}
		})
	}
}
