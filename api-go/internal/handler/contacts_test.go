package handler

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestContactsHandlerUsesMainHost(t *testing.T) {
	rec := httptest.NewRecorder()

	NewContactsHandler("example.com").ServeHTTP(rec, httptest.NewRequest(http.MethodGet, "/api/contacts", nil))

	require.Equal(t, http.StatusOK, rec.Code)
	var body map[string]string
	require.NoError(t, json.Unmarshal(rec.Body.Bytes(), &body))
	assert.Equal(t, "https://example.com", body["website"])
}
