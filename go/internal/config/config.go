package config

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
)

// Config holds all application settings.
// JSON struct tags define how fields are serialized to JSON.
type Config struct {
	OCLCAPIKey  string `json:"oclc_api_key"`   // WorldCat API key
	State       string `json:"state"`          // Two-letter state code (e.g., "IL")
	Institution string `json:"institution"`    // Your library name in WorldCat
	Port        string `json:"port"`           // Server port (default 8080)
}

// DefaultConfigPath returns the default location for the config file.
// Uses filepath.Join for cross-platform path construction (works on Windows, Linux, Mac).
func DefaultConfigPath() string {
	// Store config in a "data" directory next to the binary
	return filepath.Join("data", "config.json")
}

// Load reads configuration from file, falling back to environment variables.
// Priority: 1) Config file, 2) Environment variables, 3) Defaults (for Port only)
func Load(path string) (*Config, error) {
	cfg := &Config{
		Port: "8080", // default value
	}

	// Try to load from file first
	if _, err := os.Stat(path); err == nil {
		// File exists, read it
		data, err := os.ReadFile(path)
		if err != nil {
			return nil, fmt.Errorf("failed to read config file: %w", err)
		}
		// json.Unmarshal converts JSON bytes into our struct
		if err := json.Unmarshal(data, cfg); err != nil {
			return nil, fmt.Errorf("failed to parse config file: %w", err)
		}
	}

	// Environment variables override file values (allows container use)
	if key := os.Getenv("OCLC_API_KEY"); key != "" {
		cfg.OCLCAPIKey = key
	}
	if state := os.Getenv("STATE"); state != "" {
		cfg.State = state
	}
	if inst := os.Getenv("INSTITUTION"); inst != "" {
		cfg.Institution = inst
	}
	if port := os.Getenv("PORT"); port != "" {
		cfg.Port = port
	}

	return cfg, nil
}

// IsComplete returns true if all required fields are set.
// This determines whether to show the setup UI or the main app.
func (c *Config) IsComplete() bool {
	return c.OCLCAPIKey != "" && c.State != "" && c.Institution != ""
}

// Validate returns an error if any required field is missing.
func (c *Config) Validate() error {
	if c.OCLCAPIKey == "" {
		return fmt.Errorf("OCLC API key is required")
	}
	if c.State == "" {
		return fmt.Errorf("state is required")
	}
	if c.Institution == "" {
		return fmt.Errorf("institution name is required")
	}
	return nil
}

// Save writes the configuration to a JSON file.
// Creates the directory if it doesn't exist.
func (c *Config) Save(path string) error {
	// Ensure the directory exists
	dir := filepath.Dir(path)
	if err := os.MkdirAll(dir, 0750); err != nil {
		return fmt.Errorf("failed to create config directory: %w", err)
	}

	// json.MarshalIndent creates pretty-printed JSON
	data, err := json.MarshalIndent(c, "", "    ")
	if err != nil {
		return fmt.Errorf("failed to serialize config: %w", err)
	}

	// Write with restricted permissions (owner read/write only)
	// 0600 means: owner can read/write, group/others have no permissions
	if err := os.WriteFile(path, data, 0600); err != nil {
		return fmt.Errorf("failed to write config file: %w", err)
	}

	return nil
}
