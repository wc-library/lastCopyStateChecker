# Last Copy State Checker - Go Version

## Project Structure

```
.
├── cmd/
│   └── server/
│       └── main.go          # Application entry point (the HTTP server)
├── internal/
│   ├── api/
│   │   └── oclc.go          # WorldCat API client
│   ├── config/
│   │   └── config.go        # Configuration loading/saving
│   └── checker/
│       └── checker.go       # Core business logic
├── web/
│   ├── static/              # CSS, JS assets
│   └── templates/           # HTML templates
├── data/                    # Created on first run - stores config.json
├── go.mod                   # Module definition (dependencies)
├── go.sum                   # Dependency checksums
└── config.example.json      # Example configuration
```

## Configuration

The application can be configured in two ways:

### 1. Web Setup (Recommended for first run)

On first start, if no configuration exists, the application will redirect to a setup page where you can enter:
- OCLC API Key
- State (two-letter code, e.g., "IL")
- Institution name (as it appears in WorldCat)
- Server port (optional, defaults to 8080)

These values are saved to `data/config.json` with restricted file permissions (0600).

### 2. Environment Variables

For container deployments or automation, set these environment variables:
- `OCLC_API_KEY` — Your WorldCat API key
- `STATE` — Two-letter state code
- `INSTITUTION` — Your library name
- `PORT` — Server port (default: 8080)

**Priority:** Environment variables override values in the config file. This allows you to store settings in the file while keeping the API key in an environment variable.

## Key Go Concepts We'll Use

1. **Packages**: Every `.go` file starts with `package xyz`. `main` is special—it's the executable.
2. **Modules**: `go.mod` defines your module path and dependencies.
3. **Interfaces**: Define behavior, then implement. Great for testing.
4. **Structs**: Your data types with typed fields.
5. **Methods**: Functions attached to types (e.g., `cfg.Save()`).
6. **Error wrapping**: `%w` verb in `fmt.Errorf()` preserves error chains for debugging.

## Building

```bash
# Download dependencies
go mod tidy

# Development (auto-reloads on file change with 'air' or similar)
go run cmd/server/main.go

# Production build - creates single binary
go build -o lastcopy ./cmd/server

# Cross-compile for different platforms
GOOS=linux GOARCH=amd64 go build -o lastcopy-linux ./cmd/server
GOOS=windows GOARCH=amd64 go build -o lastcopy.exe ./cmd/server
```
