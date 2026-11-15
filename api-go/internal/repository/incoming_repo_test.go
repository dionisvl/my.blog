package repository_test

import (
	"context"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"api-go/internal/repository"
	"api-go/internal/repository/testhelper"
)

func TestIncomingRepository_Create(t *testing.T) {
	tdb := testhelper.NewTestDB(t)
	repo := repository.NewIncomingRepository(tdb.DB)

	tests := []struct {
		name                          string
		author, email, phone, message string
	}{
		{"all fields", "Alice", "alice@example.com", "+79001234567", "Hello!"},
		{"empty optional fields", "Bob", "", "", "Just a message"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			tdb.Truncate(t, "incomings")

			incoming, err := repo.Create(context.Background(), tt.author, tt.email, tt.phone, tt.message)
			require.NoError(t, err)
			assert.NotZero(t, incoming.ID)
			assert.Equal(t, tt.author, incoming.Name)
			assert.Equal(t, tt.email, incoming.Email)
			assert.Equal(t, tt.phone, incoming.Phone)
			assert.Equal(t, tt.message, incoming.Message)
			assert.Equal(t, 0, incoming.Status)
			assert.False(t, incoming.CreatedAt.IsZero())
		})
	}
}
