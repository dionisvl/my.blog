package model

import (
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestResolveImageURL(t *testing.T) {
	name := "a.webp"

	tests := []struct {
		name    string
		base    string
		image   *string
		wantURL string
	}{
		{"relative base", "/storage", &name, "/storage/uploads/a.webp"},
		{"absolute base with trailing slash", "https://cdn.example.test/", &name, "https://cdn.example.test/uploads/a.webp"},
		{"no image falls back to placeholder", "https://cdn.example.test/", nil, "https://cdn.example.test/blog_images/no-image.png"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			old := MediaBaseURL
			t.Cleanup(func() { MediaBaseURL = old })
			MediaBaseURL = tt.base

			p := &Post{Image: tt.image}
			p.ResolveImageURL()

			assert.Equal(t, tt.wantURL, p.ImageURL)
		})
	}
}
