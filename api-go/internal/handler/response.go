package handler

import (
	"encoding/json"
	"net/http"
	"strconv"
)

type Pagination struct {
	CurrentPage int  `json:"current_page"`
	TotalPages  int  `json:"total_pages"`
	Total       int  `json:"total"`
	PerPage     int  `json:"per_page"`
	HasPrev     bool `json:"has_prev"`
	HasNext     bool `json:"has_next"`
	PrevPage    int  `json:"prev_page"`
	NextPage    int  `json:"next_page"`
}

func RespondJSON(w http.ResponseWriter, status int, data any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

func RespondError(w http.ResponseWriter, status int, message string) {
	RespondJSON(w, status, map[string]string{"error": message})
}

func PageFromQuery(r *http.Request, defaultPage int) int {
	pageStr := r.URL.Query().Get("page")
	if pageStr == "" {
		return defaultPage
	}
	page, err := strconv.Atoi(pageStr)
	if err != nil || page < 1 {
		return defaultPage
	}
	return page
}

func BuildPagination(page, perPage, total int) Pagination {
	totalPages := max((total+perPage-1)/perPage, 1)

	prevPage := max(page-1, 1)

	nextPage := min(page+1, totalPages)

	return Pagination{
		CurrentPage: page,
		TotalPages:  totalPages,
		Total:       total,
		PerPage:     perPage,
		HasPrev:     page > 1,
		HasNext:     page < totalPages,
		PrevPage:    prevPage,
		NextPage:    nextPage,
	}
}

// EmptyIfNil keeps JSON collections marshalling as [] rather than null.
func EmptyIfNil[T any](s []T) []T {
	if s == nil {
		return []T{}
	}

	return s
}
