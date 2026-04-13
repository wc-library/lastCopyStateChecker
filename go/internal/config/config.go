package config

import (
	"fmt"
	"os"
)

// Config holds all application settings.
// The struct tags (e.g., `env:"OCLC_API_KEY"`) aren't used by Go itself,
// but document what environment variable maps to each field.
type Config struct {
	OCLCAPIKey   string `env:"OCLC_API_KEY"`     // WorldCat API key
	State        string `env:"STATE"`              // Two-letter state code (e.g., "IL")
	Institution  string `env:"INSTITUTION"`        // Your library name in WorldCat
	Port         string `env:"PORT"`               // Server port (default 8080)
}

// Load reads configuration from environment variables.
// In Go, functions that can fail typically return (result, error).
func Load() (*Config, error) {
	cfg := &Config{
		Port: getEnvOrDefault("PORT", "8080"),
	}

	// Required values - fail fast if missing
	cfg.OCLCAPIKey = os.Getenv("OCLC_API_KEY")
	if cfg.OCLCAPIKey == "" {
		return nil, fmt.Errorf("OCLC_API_KEY environment variable is required")
	}

	cfg.State = os.Getenv("STATE")
	if cfg.State == "" {
		return nil, fmt.Errorf("STATE environment variable is required")
	}

	cfg.Institution = os.Getenv("INSTITUTION")
	if cfg.Institution == "" {
		return nil, fmt.Errorf("INSTITUTION environment variable is required")
	}

	return cfg, nil
}

// getEnvOrDefault returns the environment variable value or a default.
// The "string" before the function name is the return type.
func getEnvOrDefault(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}
