package config

import (
	"errors"
	"strings"
	"time"

	"github.com/spf13/viper"
)

type Config struct {
	Server   ServerConfig   `mapstructure:"server"`
	Database DatabaseConfig `mapstructure:"database"`
	Auth     AuthConfig     `mapstructure:"auth"`
	App      AppConfig      `mapstructure:"app"`
	Files    FilesConfig    `mapstructure:"files"`
}

type FilesConfig struct {
	SeoDir     string `mapstructure:"seo_dir"`
	StorageDir string `mapstructure:"storage_dir"`
}

type ServerConfig struct {
	Port            int           `mapstructure:"port"`
	ReadTimeout     time.Duration `mapstructure:"read_timeout"`
	WriteTimeout    time.Duration `mapstructure:"write_timeout"`
	ShutdownTimeout time.Duration `mapstructure:"shutdown_timeout"`
	CORSOrigins     []string      `mapstructure:"cors_origins"`
}

type DatabaseConfig struct {
	URL             string        `mapstructure:"url"`
	MaxOpenConns    int           `mapstructure:"max_open_conns"`
	MaxIdleConns    int           `mapstructure:"max_idle_conns"`
	ConnMaxLifetime time.Duration `mapstructure:"conn_max_lifetime"`
	ConnMaxIdleTime time.Duration `mapstructure:"conn_max_idle_time"`
}

type AuthConfig struct {
	APIKey string `mapstructure:"api_key"`
}

type AppConfig struct {
	Env      string `mapstructure:"env"`
	MainHost string `mapstructure:"main_host"`
}

func Load() (*Config, error) {
	v := viper.New()

	v.SetConfigName("config")
	v.SetConfigType("yaml")
	v.AddConfigPath(".")
	v.AddConfigPath("/etc/api-go")

	v.SetDefault("server.port", 8081)
	v.SetDefault("server.read_timeout", 5*time.Second)
	v.SetDefault("server.write_timeout", 10*time.Second)
	v.SetDefault("server.shutdown_timeout", 15*time.Second)
	v.SetDefault("database.max_open_conns", 10)
	v.SetDefault("database.max_idle_conns", 5)
	v.SetDefault("database.conn_max_lifetime", 30*time.Minute)
	v.SetDefault("database.conn_max_idle_time", 10*time.Minute)
	v.SetDefault("app.env", "dev")
	v.SetDefault("files.seo_dir", "/seo")
	v.SetDefault("files.storage_dir", "/storage")

	v.SetEnvKeyReplacer(strings.NewReplacer(".", "_"))
	v.AutomaticEnv()

	_ = v.BindEnv("database.url", "DATABASE_URL")
	_ = v.BindEnv("auth.api_key", "API_KEY")
	_ = v.BindEnv("app.env", "APP_ENV")
	_ = v.BindEnv("app.main_host", "MAIN_HOST")
	_ = v.BindEnv("server.cors_origins", "CORS_ALLOWED_ORIGINS")
	_ = v.BindEnv("files.seo_dir", "SEO_DIR")
	_ = v.BindEnv("files.storage_dir", "STORAGE_DIR")

	if err := v.ReadInConfig(); err != nil {
		if _, ok := err.(viper.ConfigFileNotFoundError); !ok {
			return nil, err
		}
	}

	var cfg Config
	if err := v.Unmarshal(&cfg); err != nil {
		return nil, err
	}
	if err := validate(&cfg); err != nil {
		return nil, err
	}

	return &cfg, nil
}

func validate(cfg *Config) error {
	if strings.TrimSpace(cfg.Auth.APIKey) == "" {
		return errors.New("API_KEY must not be empty")
	}
	if strings.TrimSpace(cfg.App.MainHost) == "" {
		return errors.New("MAIN_HOST must not be empty")
	}

	return nil
}
