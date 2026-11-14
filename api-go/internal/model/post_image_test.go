package model

import (
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestResolveImageURLUsesMediaBaseURL(t *testing.T) {
	old := MediaBaseURL
	t.Cleanup(func() { MediaBaseURL = old })

	name := "a.webp"
	p := &Post{Image: &name}

	MediaBaseURL = "/storage"
	p.ResolveImageURL()
	assert.Equal(t, "/storage/uploads/a.webp", p.ImageURL)

	MediaBaseURL = "https://cdn.example.test/"
	p.ResolveImageURL()
	assert.Equal(t, "https://cdn.example.test/uploads/a.webp", p.ImageURL)

	p.Image = nil
	p.ResolveImageURL()
	assert.Equal(t, "https://cdn.example.test/blog_images/no-image.png", p.ImageURL)
}
