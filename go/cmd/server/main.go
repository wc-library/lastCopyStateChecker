package main

// package main is special - it produces an executable, not a library.
// The main() function is the entry point when you run the program.

import (
	"embed"
	"fmt"
	"html/template"
	"log"
	"net/http"
	"os"
	"strings"
	"sync"

	"github.com/gin-gonic/gin"
	"github.com/wc-library/lastCopyStateChecker/internal/api"
	"github.com/wc-library/lastCopyStateChecker/internal/checker"
	"github.com/wc-library/lastCopyStateChecker/internal/config"
)

//go:embed all:web/templates
var templatesFS embed.FS

// App holds our application state and dependencies.
// Using a struct allows us to pass the config to handlers cleanly.
type App struct {
	Config     *config.Config
	ConfigPath string
	checker    *checker.Checker
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

	// Initialize API client and checker if configuration is complete.
	// These are initialized after loading config so we have the OAuth2 credentials.
	if app.isSetup {
		client := api.NewClient(cfg.OCLCClientID, cfg.OCLCClientSecret, cfg.OCLCInstitutionID)
		app.checker = checker.New(client, cfg.Institution, cfg.State, cfg.Debug)
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

	// Configure template serving.
	// Templates are embedded in the binary for easy deployment.
	// Parse templates from embedded filesystem and set on router.
	templ := template.Must(template.New("").ParseFS(templatesFS, "web/templates/*.html"))
	router.SetHTMLTemplate(templ)

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
	clientID := c.PostForm("oclc_client_id")
	clientSecret := c.PostForm("oclc_client_secret")
	institutionID := c.PostForm("oclc_institution_id")
	state := c.PostForm("state")
	institution := c.PostForm("institution")
	port := c.PostForm("port")

	// Validate required fields.
	// gin.H creates JSON response: {"error": "..."}
	if clientID == "" || clientSecret == "" || institutionID == "" || state == "" || institution == "" {
		c.JSON(http.StatusBadRequest, gin.H{
			"error": "OCLC Client ID, Client Secret, Institution ID, state, and institution name are required",
		})
		return
	}

	// Update application config.
	app.Config.OCLCClientID = clientID
	app.Config.OCLCClientSecret = clientSecret
	app.Config.OCLCInstitutionID = institutionID
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

	// Initialize API client and checker with new configuration
	client := api.NewClient(app.Config.OCLCClientID, app.Config.OCLCClientSecret, app.Config.OCLCInstitutionID)
	app.checker = checker.New(client, app.Config.Institution, app.Config.State, app.Config.Debug)

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

	// Get OCLC numbers from form data
	oclcList := c.PostForm("oclc-list")
	if oclcList == "" {
		c.JSON(http.StatusBadRequest, gin.H{
			"error": "No OCLC numbers provided",
		})
		return
	}

	// Parse the input - comma-separated or one per line
	// Split on commas and newlines, then clean up whitespace
	oclcNumbers := parseOCLCInput(oclcList)

	if len(oclcNumbers) == 0 {
		c.JSON(http.StatusBadRequest, gin.H{
			"error": "No valid OCLC numbers found",
		})
		return
	}

	// Check each OCLC number
	results := app.checker.CheckMany(oclcNumbers)

	// Filter to only last copy candidates
	candidates := checker.LastCopyCandidates(results)

	// Return both full results and candidates
	c.JSON(http.StatusOK, gin.H{
		"results":    results,
		"candidates": candidates,
		"total":      len(results),
		"candidates_count": len(candidates),
	})
}

// parseOCLCInput splits a string on commas and newlines to extract OCLC numbers.
func parseOCLCInput(input string) []string {
	// Replace commas with newlines so we can split on both uniformly
	input = strings.ReplaceAll(input, ",", "\n")

	lines := strings.Split(input, "\n")
	var numbers []string

	for _, line := range lines {
		trimmed := strings.TrimSpace(line)
		if trimmed != "" {
			numbers = append(numbers, trimmed)
		}
	}

	return numbers
}
