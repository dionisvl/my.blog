package config

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestLoadRejectsEmptyAPIKey(t *testing.T) {
	dir := t.TempDir()
	writeConfigFile(t, dir, "app:\n  env: prod\n")
	chdir(t, dir)
	t.Setenv("API_KEY", "")
	t.Setenv("MAIN_HOST", "example.com")

	cfg, err := Load()

	require.Error(t, err)
	assert.Nil(t, cfg)
	assert.EqualError(t, err, "API_KEY must not be empty")
}

func TestLoadAcceptsNonEmptyAPIKey(t *testing.T) {
	dir := t.TempDir()
	writeConfigFile(t, dir, "app:\n  env: prod\n")
	chdir(t, dir)
	t.Setenv("API_KEY", "secret")
	t.Setenv("MAIN_HOST", "example.com")

	cfg, err := Load()

	require.NoError(t, err)
	require.NotNil(t, cfg)
	assert.Equal(t, "secret", cfg.Auth.APIKey)
	assert.Equal(t, "prod", cfg.App.Env)
	assert.Equal(t, "example.com", cfg.App.MainHost)
}

func TestLoadRejectsEmptyMainHost(t *testing.T) {
	dir := t.TempDir()
	writeConfigFile(t, dir, "app:\n  env: prod\n")
	chdir(t, dir)
	t.Setenv("API_KEY", "secret")
	t.Setenv("MAIN_HOST", "")

	cfg, err := Load()

	require.Error(t, err)
	assert.Nil(t, cfg)
	assert.EqualError(t, err, "MAIN_HOST must not be empty")
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
