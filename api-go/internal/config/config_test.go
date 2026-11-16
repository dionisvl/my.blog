package config

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestLoad(t *testing.T) {
	tests := []struct {
		name     string
		apiKey   string
		mainHost string
		wantErr  string
	}{
		{name: "valid", apiKey: "secret", mainHost: "example.com"},
		{name: "empty API key", apiKey: "", mainHost: "example.com", wantErr: "API_KEY must not be empty"},
		{name: "empty main host", apiKey: "secret", mainHost: "", wantErr: "MAIN_HOST must not be empty"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			dir := t.TempDir()
			writeConfigFile(t, dir, "app:\n  env: prod\n")
			chdir(t, dir)
			t.Setenv("API_KEY", tt.apiKey)
			t.Setenv("MAIN_HOST", tt.mainHost)

			cfg, err := Load()

			if tt.wantErr != "" {
				assert.EqualError(t, err, tt.wantErr)
				assert.Nil(t, cfg)
				return
			}
			require.NoError(t, err)
			require.NotNil(t, cfg)
			assert.Equal(t, tt.apiKey, cfg.Auth.APIKey)
			assert.Equal(t, "prod", cfg.App.Env)
			assert.Equal(t, tt.mainHost, cfg.App.MainHost)
		})
	}
}

func TestLoadContactEmail(t *testing.T) {
	tests := []struct {
		name string
		env  string
		want string
	}{
		{name: "defaults to the main host", env: "", want: "info@example.com"},
		{name: "explicit address", env: "hello@example.org", want: "hello@example.org"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			dir := t.TempDir()
			writeConfigFile(t, dir, "app:\n  env: prod\n")
			chdir(t, dir)
			t.Setenv("API_KEY", "secret")
			t.Setenv("MAIN_HOST", "example.com")
			t.Setenv("CONTACT_EMAIL", tt.env)

			cfg, err := Load()

			require.NoError(t, err)
			assert.Equal(t, tt.want, cfg.App.ContactEmail)
		})
	}
}

func TestLoadRejectsInvalidTrustedProxy(t *testing.T) {
	dir := t.TempDir()
	writeConfigFile(t, dir, "server:\n  trusted_proxies: [\"10.0.0.0/8\", \"not-a-cidr\"]\n")
	chdir(t, dir)
	t.Setenv("API_KEY", "secret")
	t.Setenv("MAIN_HOST", "example.com")

	cfg, err := Load()

	assert.ErrorContains(t, err, `TRUSTED_PROXIES: invalid CIDR "not-a-cidr"`)
	assert.Nil(t, cfg)
}

func writeConfigFile(t *testing.T, dir, content string) {
	t.Helper()

	err := os.WriteFile(filepath.Join(dir, "config.yaml"), []byte(content), 0o600)
	require.NoError(t, err)
}

func chdir(t *testing.T, dir string) {
	t.Helper()

	oldDir, err := os.Getwd()
	require.NoError(t, err)

	err = os.Chdir(dir)
	require.NoError(t, err)

	t.Cleanup(func() {
		_ = os.Chdir(oldDir)
	})
}
