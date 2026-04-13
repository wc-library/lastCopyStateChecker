package main

// package main is special - it produces an executable, not a library.
// The main() function is the entry point when you run the program.

import (
	"fmt"
	"log"
	"net/http"
	"os"
	"sync"

	"github.com/gin-gonic/gin"
	"github.com/wc-library/lastCopyStateChecker/internal/config"
)

// App holds our application state and dependencies.
// Using a struct allows us to pass the config to handlers cleanly.
type App struct {
	Config     *config.Config
	ConfigPath string
	mu         sync.RWMutex // protects config and isSetup for thread-safe access
	isSetup    bool         // true if config is complete
}

// isConfigured returns thread-safe read of setup state.
func (app *App) isConfigured() bool {
	app.mu.RLock()
	defer app.mu.RUnlock()
	return app.isSetup
}

// reloadConfig reloads configuration from disk and updates setup state.
// Call after saving new configuration to switch modes without restart.
func (app *App) reloadConfig() error {
	app.mu.Lock()
	defer app.mu.Unlock()

	cfg, err := config.Load(app.ConfigPath)
	if err != nil {
		return err
	}

	app.Config = cfg
	app.isSetup = cfg.IsComplete()
	return nil
}

func main() {
	// Configure default logger format (timestamp + message)
	log.SetFlags(log.LstdFlags)

	// Determine config file path.
	// If CONFIG_PATH env var is set, use it; otherwise use default.
	configPath := os.Getenv("CONFIG_PATH")
	if configPath == "" {
		configPath = config.DefaultConfigPath()
	}

	log.Printf("Loading configuration from: %s", configPath)

	// Load configuration from file and/or environment variables.
	// This returns (*Config, error) - Go's error handling pattern.
	cfg, err := config.Load(configPath)
	if err != nil {
		// Log the error but don't exit - we might just need setup.
		// Errors here are usually file read/parse issues, not missing config values.
		log.Printf("Warning: %v", err)
	}

	// Create application state.
	app := &App{
		Config:     cfg,
		ConfigPath: configPath,
		isSetup:    cfg.IsComplete(),
	}

	if app.isSetup {
		log.Println("Configuration complete - starting application")
	} else {
		log.Println("Configuration incomplete - setup mode enabled")
	}

	// Initialize Gin router.
	// gin.Default() gives us a router with Logger and Recovery middleware.
	// Logger: logs HTTP requests (method, path, status, latency)
	// Recovery: catches panics in handlers, returns 500, keeps server running
	router := gin.Default()

	// Trust X-Forwarded-* headers if behind a reverse proxy.
	// Set to nil to trust all proxies, or provide a list of trusted IPs.
	// Empty string slice means "trust none" - safe default.
	router.SetTrustedProxies([]string{})

	// Configure template and static file serving.
	// LoadHTMLGlob: finds all .html files in web/templates/ and parses them.
	// Static: serves files from web/static/ at the URL path /static/.
	router.LoadHTMLGlob("web/templates/*")
	router.Static("/static", "web/static")

	// Register all route handlers (both setup and app routes).
	// Middleware will control access based on configuration state.
	app.registerRoutes(router)

	// Start the HTTP server.
	// fmt.Sprintf creates the address string (e.g., ":8080").
	// ListenAndServe blocks until the server stops or errors.
	addr := fmt.Sprintf(":%s", app.Config.Port)
	log.Printf("Server starting on http://localhost%s", addr)

	if err := router.Run(addr); err != nil {
		// Fatal error - can't start server (e.g., port already in use).
		log.Fatalf("Server failed to start: %v", err)
	}
}

// registerRoutes sets up all URL routes.
// Routes check configuration state internally and route to appropriate handler.
func (app *App) registerRoutes(r *gin.Engine) {
	// Root path - serves setup or main app based on configuration state
	r.GET("/", app.handleRoot)

	// Setup submission - only valid during setup mode
	r.POST("/setup", app.handleSetup)

	// Check endpoint - only valid when configured
	r.POST("/check", app.handleCheck)
}

// handleRoot serves either the setup page or the main app depending on config state.
func (app *App) handleRoot(c *gin.Context) {
	if app.isConfigured() {
		// Config complete - show main application
		c.HTML(http.StatusOK, "index.html", gin.H{
			"Title":       "Last Copy State Checker",
			"Institution": app.Config.Institution,
			"State":       app.Config.State,
		})
	} else {
		// Config incomplete - show setup page
		c.HTML(http.StatusOK, "setup.html", gin.H{
			"Title": "Initial Setup",
		})
	}
}

// handleSetup processes the setup form submission.
// Rejects requests if configuration is already complete.
func (app *App) handleSetup(c *gin.Context) {
	// Reject if already configured
	if app.isConfigured() {
		c.JSON(http.StatusConflict, gin.H{
			"error": "Configuration already complete. Restart server to reconfigure.",
		})
		return
	}

	// c.PostForm retrieves form data from the request body.
	// Returns empty string if the field is missing.
	oclcKey := c.PostForm("oclc_api_key")
	state := c.PostForm("state")
	institution := c.PostForm("institution")
	port := c.PostForm("port")

	// Validate required fields.
	// gin.H creates JSON response: {"error": "..."}
	if oclcKey == "" || state == "" || institution == "" {
		c.JSON(http.StatusBadRequest, gin.H{
			"error": "OCLC API key, state, and institution are required",
		})
		return
	}

	// Update application config.
	app.Config.OCLCAPIKey = oclcKey
	app.Config.State = state
	app.Config.Institution = institution
	if port != "" {
		app.Config.Port = port
	}

	// Validate the complete configuration.
	if err := app.Config.Validate(); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
		return
	}

	// Save to disk.
	if err := app.Config.Save(app.ConfigPath); err != nil {
		log.Printf("Failed to save config: %v", err)
		c.JSON(http.StatusInternalServerError, gin.H{
			"error": "Failed to save configuration",
		})
		return
	}

	// Reload configuration without restarting server.
	if err := app.reloadConfig(); err != nil {
		log.Printf("Failed to reload config: %v", err)
		c.JSON(http.StatusInternalServerError, gin.H{
			"error": "Configuration saved but failed to reload",
		})
		return
	}

	log.Println("Configuration updated - switching to application mode")
	c.JSON(http.StatusOK, gin.H{
		"message": "Configuration saved successfully. Redirecting to application...",
	})
}

// handleCheck processes OCLC number checking requests.
// Rejects requests if configuration is incomplete.
func (app *App) handleCheck(c *gin.Context) {
	// Reject if not configured
	if !app.isConfigured() {
		c.JSON(http.StatusServiceUnavailable, gin.H{
			"error": "Configuration incomplete. Complete setup first.",
		})
		return
	}

	// Stub implementation - we'll add real logic after building the OCLC client.
	c.JSON(http.StatusOK, gin.H{
		"message": "Check endpoint - implementation pending",
	})
}
