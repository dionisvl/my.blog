package handler

import (
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestBuildPagination(t *testing.T) {
	tests := []struct {
		name                 string
		page, perPage, total int
		want                 Pagination
	}{
		{"empty result is one page", 1, 10, 0, Pagination{CurrentPage: 1, TotalPages: 1, Total: 0, PerPage: 10, PrevPage: 1, NextPage: 1}},
		{"exact fit", 1, 10, 10, Pagination{CurrentPage: 1, TotalPages: 1, Total: 10, PerPage: 10, PrevPage: 1, NextPage: 1}},
		{"first of several", 1, 10, 25, Pagination{CurrentPage: 1, TotalPages: 3, Total: 25, PerPage: 10, HasNext: true, PrevPage: 1, NextPage: 2}},
		{"middle", 2, 10, 25, Pagination{CurrentPage: 2, TotalPages: 3, Total: 25, PerPage: 10, HasPrev: true, HasNext: true, PrevPage: 1, NextPage: 3}},
		{"last", 3, 10, 25, Pagination{CurrentPage: 3, TotalPages: 3, Total: 25, PerPage: 10, HasPrev: true, PrevPage: 2, NextPage: 3}},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			assert.Equal(t, tt.want, BuildPagination(tt.page, tt.perPage, tt.total))
		})
	}
}

func TestPageFromQuery(t *testing.T) {
	tests := []struct {
		name  string
		query string
		want  int
	}{
		{"missing", "", 1},
		{"valid", "?page=4", 4},
		{"zero", "?page=0", 1},
		{"negative", "?page=-2", 1},
		{"not a number", "?page=abc", 1},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			req := httptest.NewRequest(http.MethodGet, "/api/posts"+tt.query, nil)
			assert.Equal(t, tt.want, PageFromQuery(req, 1))
		})
	}
}
