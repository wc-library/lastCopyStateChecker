# Last Copy State Checker - Go Version

## Overview

This is a Go implementation of the Last Copy State Checker application. It provides a web interface for checking OCLC numbers against the WorldCat API to identify items held only by your institution in your state.

## Project Structure

```
.
├── cmd/
│   └── server/
│       ├── main.go          # Application entry point (the HTTP server)
│       └── web/
│           ├── static/      # CSS, JS assets (embedded in binary)
│           └── templates/   # HTML templates (embedded in binary)
├── internal/
│   ├── api/
│   │   └── oclc.go          # WorldCat API client
│   ├── config/
│   │   └── config.go        # Configuration loading/saving
│   └── checker/
│       └── checker.go       # Core business logic
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
- **Debug** — Enable debug mode to see raw API responses (optional, default: false)

These values are saved to `data/config.json` with restricted file permissions (0600).

### Example `config.json`

```json
{
  "oclc_client_id": "your-client-id",
  "oclc_client_secret": "your-client-secret",
  "oclc_institution_id": "ILU",
  "state": "IL",
  "institution": "Your Library Name",
  "port": "8080",
  "debug": false
}
```

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
- `DEBUG` — Set to `true` to enable debug mode

**Priority:** Environment variables override values in the config file. This allows you to keep sensitive credentials in environment variables.

## Building

```bash
# Download dependencies
go mod tidy

# Development (auto-reloads on file change with 'air' or similar)
go run cmd/server/main.go

# Production build - creates single binary (templates and static files are embedded)
go build -o lastcopy ./cmd/server

# Cross-compile for different platforms
GOOS=linux GOARCH=amd64 go build -o lastcopy-linux ./cmd/server
GOOS=windows GOARCH=amd64 go build -o lastcopy.exe ./cmd/server
```

**Note**: Templates and static files are embedded in the binary—no separate `web/` directory needed for deployment.

**Runtime Configuration**: Set `GIN_MODE=release` when running in production to disable debug logging:
```bash
export GIN_MODE=release
./lastcopy
```

## Using the Web Interface

### Input Methods

1. **Text Entry**: Paste OCLC numbers (one per line or comma-separated) into the textarea
2. **File Upload**: Drag and drop a file onto the upload zone, or click to browse
   - Accepts `.txt`, `.csv`, `.tsv`, `.lst`, or any text file
   - File contents are loaded into the textarea for review before checking

### Results Display

- **Summary Cards**: Shows total checked, last copy candidates, and errors at a glance
- **Results Table**: Color-coded rows showing:
  - OCLC Number
  - Whether item is at your library
  - Whether other libraries in your state hold it
  - Status (Last Copy Candidate, At Library, or Not Found)
- **Download**: Button appears when candidates are found—downloads a `.txt` file with OCLC numbers

### Debug Mode

Enable debug mode by setting `"debug": true` in `config.json` or `DEBUG=true` environment variable. This adds a collapsible "Debug" section to results showing:
- State filter requested
- Number of libraries found in API response
- Raw JSON response from OCLC API

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
- Debug mode exposes raw API responses—ensure only authorized users can access when enabled

## Needed Improvements
- Add button to download error list as CSV
- Add graceful error handling for API rate limits
- Add pagination for large result sets
- 

