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

**OCLC API Credentials** (from [OCLC Developer Network](https://www.oclc.org/developer/)):
- **Client ID** — OAuth2 Client ID (select M2M/Machine-to-Machine flow)
- **Client Secret** — OAuth2 Client Secret
- **Institution ID** — Your OCLC institution symbol (e.g., "ILU", "CUY")

**Library Settings**:
- **Institution Name** — Your library name as it appears in WorldCat
- **State** — Two-letter state code (e.g., "IL")
- **Port** — Server port (optional, defaults to 8080)

These values are saved to `data/config.json` with restricted file permissions (0600).

### 2. Environment Variables

For container deployments or automation, set these environment variables:

**OCLC API Credentials**:
- `OCLC_CLIENT_ID` — OAuth2 Client ID
- `OCLC_CLIENT_SECRET` — OAuth2 Client Secret
- `OCLC_INSTITUTION_ID` — OCLC institution symbol

**Library Settings**:
- `STATE` — Two-letter state code
- `INSTITUTION` — Your library name
- `PORT` — Server port (default: 8080)

**Priority:** Environment variables override values in the config file. This allows you to keep sensitive credentials in environment variables.

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

## Production Deployment

### Configuration Path

The application stores configuration in `data/config.json` relative to the **working directory** where the binary is executed. To control where configuration is stored, use one of these approaches:

**Option 1: Set working directory (recommended for systemd)**
```bash
cd /var/lib/lastcopy && ./lastcopy
# Config will be written to /var/lib/lastcopy/data/config.json
```

**Option 2: Use CONFIG_PATH environment variable**
```bash
CONFIG_PATH=/etc/lastcopy/config.json ./lastcopy
# Config will be written to /etc/lastcopy/config.json
```

### Systemd Service Example

Create `/etc/systemd/system/lastcopy.service`:

```ini
[Unit]
Description=Last Copy State Checker
After=network.target

[Service]
Type=simple
User=lastcopy
Group=lastcopy
WorkingDirectory=/var/lib/lastcopy
Environment="CONFIG_PATH=/var/lib/lastcopy/data/config.json"
Environment="GIN_MODE=release"
ExecStart=/usr/local/bin/lastcopy
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

**Setup steps:**
```bash
# Create user and directories
sudo useradd -r -s /bin/false lastcopy
sudo mkdir -p /var/lib/lastcopy /usr/local/bin

# Install binary
sudo cp lastcopy /usr/local/bin/lastcopy

# Set permissions
sudo chown -R lastcopy:lastcopy /var/lib/lastcopy
sudo chmod 750 /var/lib/lastcopy

# Enable and start service
sudo systemctl daemon-reload
sudo systemctl enable lastcopy
sudo systemctl start lastcopy

# View logs
sudo journalctl -u lastcopy -f
```

### File Permissions

- Config file: `0600` (owner read/write only) — contains API key
- Config directory: `0750` (owner rwx, group rx)
- Binary: `0755` (standard executable)

### Reverse Proxy (nginx)

If running behind nginx:

```nginx
server {
    listen 80;
    server_name lastcopy.yourlibrary.org;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### Reverse Proxy (Apache)

If running behind Apache with `mod_proxy` enabled:

```apache
<VirtualHost *:80>
    ServerName lastcopy.yourlibrary.org

    ProxyPreserveHost On
    ProxyPass / http://localhost:8080/
    ProxyPassReverse / http://localhost:8080/

    ErrorLog ${APACHE_LOG_DIR}/lastcopy-error.log
    CustomLog ${APACHE_LOG_DIR}/lastcopy-access.log combined
</VirtualHost>
```

Enable required modules:
```bash
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo systemctl restart apache2
```

### Security Considerations

- Run as non-root user (`lastcopy` user in example above)
- Keep API key in config file with restricted permissions (0600)
- Use HTTPS in production (via reverse proxy)
- Consider firewall rules if binding to non-localhost interface
