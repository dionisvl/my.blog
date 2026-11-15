package handler

import (
	"context"
	"encoding/json"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"api-go/internal/model"
)

// nilSearchRepo mimics bun leaving the destination slice nil on an empty result.
type nilSearchRepo struct {
	fakePostRepo
}

func (f *nilSearchRepo) SearchPublished(ctx context.Context, query string, limit int) ([]model.Post, error) {
	return nil, nil
}

func TestSearchEmptyResultMarshalsAsArray(t *testing.T) {
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	handler := NewSearchHandler(&nilSearchRepo{}, logger)

	req := httptest.NewRequest(http.MethodGet, "/api/search?q=nomatch", nil)
	rec := httptest.NewRecorder()

	handler.ServeHTTP(rec, req)

	require.Equal(t, http.StatusOK, rec.Code)
	assert.Contains(t, rec.Body.String(), `"posts":[]`)

	var payload struct {
		Posts []model.Post `json:"posts"`
		Query string       `json:"query"`
	}
	require.NoError(t, json.Unmarshal(rec.Body.Bytes(), &payload))
	assert.NotNil(t, payload.Posts)
	assert.Empty(t, payload.Posts)
	assert.Equal(t, "nomatch", payload.Query)
}

func TestEmptyIfNil(t *testing.T) {
	tests := []struct {
		name string
		in   []int
		want []int
	}{
		{"nil becomes empty slice", nil, []int{}},
		{"empty stays empty", []int{}, []int{}},
		{"values are kept", []int{1, 2}, []int{1, 2}},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			assert.Equal(t, tt.want, EmptyIfNil(tt.in))
		})
	}
}
