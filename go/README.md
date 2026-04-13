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
│   │   └── config.go        # Configuration loading
│   └── checker/
│       └── checker.go       # Core business logic
├── web/
│   ├── static/              # CSS, JS assets
│   └── templates/           # HTML templates
├── go.mod                   # Module definition (dependencies)
├── go.sum                   # Dependency checksums
└── config.example.yaml      # Example configuration
```

## Key Go Concepts We'll Use

1. **Packages**: Every `.go` file starts with `package xyz`. `main` is special—it's the executable.
2. **Modules**: `go.mod` defines your module path and dependencies.
3. **Interfaces**: Define behavior, then implement. Great for testing.
4. **Structs**: Your data types with typed fields.

## Building

```bash
# Development
go run cmd/server/main.go

# Production build
go build -o lastcopy ./cmd/server
```
