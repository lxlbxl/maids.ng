# Maids.ng CLI Tool

A professional command-line interface for internal agents to manage the Maids.ng platform. Built with Python, Typer, Pydantic, and httpx.

## Architecture

The CLI uses **Dedicated Agent Routes** (`/api/v1/cli/*`) with:
- **Single Service Token**: One `CLI_AGENT_TOKEN` for all internal agent operations
- **Audit Logging**: All actions are logged for compliance and debugging
- **User Impersonation**: Optional `X-User-ID` header to act on behalf of users

```
┌─────────────────────────────────────────────────────────────┐
│                    Maids.ng CLI Tool                         │
├─────────────────────────────────────────────────────────────┤
│  Authentication: Bearer Token (CLI_AGENT_TOKEN)             │
│  Routes: /api/v1/cli/*                                       │
│  Middleware: mcp.auth (EnsureMcpTokenIsValid)               │
│  Logging: All actions logged to audit channel               │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ Authorization: Bearer <token>
                              │ X-User-ID: <optional>
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              Laravel Backend (/api/v1/cli/*)                │
├─────────────────────────────────────────────────────────────┤
│  CliAgentController                                          │
│  ├── logAction() - Audit trail for every request           │
│  ├── getTargetUserId() - Support X-User-ID header          │
│  └── Admin-level access to all resources                    │
└─────────────────────────────────────────────────────────────┘
```

## Features

- **Internal Agent Focused**: Designed for business operations, not end-users
- **JSON Output**: All commands support `--json` flag for machine-readable output
- **Persistent Configuration**: Credentials stored in `~/.maids-ng/config.json`
- **Comprehensive API Coverage**: Access all major Maids.ng API endpoints
- **Audit Trail**: Every action is logged for compliance

## Installation

### Prerequisites

- Python 3.9 or higher
- pip (Python package manager)
- A valid `CLI_AGENT_TOKEN` from Maids.ng admin

### Install from Source

```bash
cd cli/
pip install -e .
```

### Verify Installation

```bash
maids --help
```

## Quick Start

### 1. Configure CLI Agent Token

```bash
maids config set-api-key your-cli-agent-token-here
```

The token should be the same value as `MCP_SECRET_KEY` in your `.env` file.

### 2. Check System Status

```bash
maids status check
```

### 3. Browse Available Maids

```bash
maids maid list
maids maid list --location "Lagos" --verified
```

## Command Reference

### Configuration (`maids config`)

| Command | Description |
|---------|-------------|
| `maids config set-api-key <key>` | Set the CLI agent token |
| `maids config set-api-key <key> --api-url <url>` | Set token and custom URL |

### Status (`maids status`)

| Command | Description |
|---------|-------------|
| `maids status check` | Check CLI API health and availability |

### Maid Management (`maids maid`)

| Command | Description |
|---------|-------------|
| `maids maid list` | List all maids |
| `maids maid list --location <city>` | Filter by location |
| `maids maid list --verified` | Show only verified maids |
| `maids maid list --status <status>` | Filter by availability status |
| `maids maid get <id>` | Get detailed maid profile |
| `maids maid skills` | List all available skills |
| `maids maid help-types` | List all help types |

### Booking Management (`maids booking`)

| Command | Description |
|---------|-------------|
| `maids booking list` | List all bookings |
| `maids booking list --status <status>` | Filter by status |
| `maids booking list --employer-id <id>` | Filter by employer |
| `maids booking list --maid-id <id>` | Filter by maid |
| `maids booking get --id <id>` | Get booking details |
| `maids booking cancel <id>` | Cancel a booking |

### Assignment Management (`maids assignment`)

| Command | Description |
|---------|-------------|
| `maids assignment list` | List all assignments |
| `maids assignment list --status <status>` | Filter by status |
| `maids assignment get <id>` | Get assignment details |
| `maids assignment accept <id>` | Accept an assignment |
| `maids assignment reject <id> --reason <reason>` | Reject an assignment |
| `maids assignment complete <id>` | Complete an assignment |
| `maids assignment stats` | View assignment statistics |

### Wallet Management (`maids wallet`)

| Command | Description |
|---------|-------------|
| `maids wallet balance --user-id <id>` | Check wallet balance for user |
| `maids wallet transactions --user-id <id>` | View transaction history |

### Notifications (`maids notification`)

| Command | Description |
|---------|-------------|
| `maids notification list --user-id <id>` | List notifications for user |
| `maids notification list --user-id <id> --unread` | Show only unread |
| `maids notification unread-count --user-id <id>` | Get unread count |
| `maids notification read <id>` | Mark as read |
| `maids notification read-all --user-id <id>` | Mark all as read |
| `maids notification delete <id>` | Delete a notification |

### User Management (`maids user`)

| Command | Description |
|---------|-------------|
| `maids user list` | List all users |
| `maids user list --role <role>` | Filter by role (admin/maid/employer) |
| `maids user list --status <status>` | Filter by status |
| `maids user get <id>` | Get user details |
| `maids user update-status <id> --status <status>` | Update user status |

## Global Options

| Option | Description |
|--------|-------------|
| `--json` | Output raw JSON instead of human-readable text |
| `--version`, `-v` | Show version information |
| `--help`, `-h` | Show help message |

## Output Formats

### Human-Readable (Default)

```bash
$ maids maid list
Found 3 maid(s) (showing 3)

--- Maid #1 ---
Location: Lagos
Experience: 5 years
Status: Available
Verified: Yes
Rating: 4.8/5.0 (24 reviews)
```

### JSON Output

```bash
$ maids maid list --json
{
  "data": [...],
  "total": 3,
  "limit": 20,
  "offset": 0
}
```

## Configuration File

The CLI stores configuration in `~/.maids-ng/config.json`:

```json
{
  "api_url": "https://api.maids.ng/v1",
  "api_key": "your-cli-agent-token",
  "cli_agent_token": "your-cli-agent-token",
  "default_output_format": "human"
}
```

### Config File Locations

| OS | Path |
|----|------|
| Windows | `C:\Users\<username>\.maids-ng\config.json` |
| macOS/Linux | `~/.maids-ng/config.json` |

## Error Handling

The CLI uses standard exit codes:

- `0`: Success
- `1`: Error (API error, connection failure, invalid input)

Error messages are sent to stderr, while successful output goes to stdout.

## Examples

### For Human Operators

```bash
# Check all pending assignments
maids assignment list --status pending

# Accept a specific assignment
maids assignment accept 123

# Check wallet balance for a user
maids wallet balance --user-id 456

# Browse verified maids in Lagos
maids maid list --location "Lagos" --verified

# Update user status
maids user update-status 789 --status suspended
```

### For AI Agents (JSON Output)

```bash
# Get machine-readable assignment data
maids assignment get 123 --json | python -m json.tool

# Script-friendly status check
maids status check --json

# Export all notifications for a user
maids notification list --user-id 456 --json > notifications.json
```

### Automation Scripts

```bash
#!/bin/bash
# Check CLI API health and notify if down
if ! maids status check --json | grep -q '"status": "healthy"'; then
    echo "CLI API is down!" | mail -s "Maids.ng Alert" admin@example.com
fi
```

## Backend Routes

The CLI uses the following dedicated routes:

```
GET    /api/v1/cli/status              - System status
GET    /api/v1/cli/health              - Health check
GET    /api/v1/cli/maids               - List maids
GET    /api/v1/cli/maids/{id}          - Get maid profile
PATCH  /api/v1/cli/maids/{id}/availability - Update availability
GET    /api/v1/cli/maids/{id}/earnings - Get earnings
GET    /api/v1/cli/employers/{id}/preferences - Get preferences
PATCH  /api/v1/cli/employers/{id}/preferences - Update preferences
GET    /api/v1/cli/bookings            - List bookings
POST   /api/v1/cli/bookings/create     - Create booking
POST   /api/v1/cli/bookings/{id}/cancel - Cancel booking
GET    /api/v1/cli/assignments         - List assignments
GET    /api/v1/cli/assignments/{id}    - Get assignment
POST   /api/v1/cli/assignments/{id}/accept - Accept
POST   /api/v1/cli/assignments/{id}/reject - Reject
POST   /api/v1/cli/assignments/{id}/complete - Complete
GET    /api/v1/cli/assignments/statistics - Statistics
GET    /api/v1/cli/wallet              - Get wallet (requires user_id)
GET    /api/v1/cli/wallet/transactions - Get transactions (requires user_id)
GET    /api/v1/cli/notifications       - List notifications (requires user_id)
POST   /api/v1/cli/notifications/{id}/read - Mark as read
POST   /api/v1/cli/notifications/mark-all-read - Mark all read
DELETE /api/v1/cli/notifications/{id}  - Delete notification
GET    /api/v1/cli/users               - List users
GET    /api/v1/cli/users/{id}          - Get user
PUT    /api/v1/cli/users/{id}/status   - Update status
GET    /api/v1/cli/reference/skills    - Get skills
GET    /api/v1/cli/reference/help-types - Get help types
```

## Audit Logging

All CLI actions are logged to the `audit` log channel with:
- Action name
- Request data
- User ID (if impersonating)
- Timestamp

Example log entry:
```json
{
  "action": "accept_assignment",
  "data": {"assignment_id": 123},
  "user_id": null,
  "timestamp": "2026-05-25T21:00:00+01:00"
}
```

## Project Structure

```
cli/
├── pyproject.toml          # Package configuration
├── README.md               # This file
└── maids_cli/
    ├── __init__.py
    ├── main.py             # CLI entry point
    ├── config.py           # Configuration management
    ├── client/
    │   ├── __init__.py
    │   ├── api_client.py   # HTTP client
    │   └── schemas.py      # Pydantic models
    └── commands/
        ├── __init__.py
        ├── config.py       # Config commands
        ├── status.py       # Status commands
        ├── bookings.py     # Booking commands
        ├── maids.py        # Maid commands
        ├── assignments.py  # Assignment commands
        ├── wallet.py       # Wallet commands
        ├── notifications.py # Notification commands
        └── users.py        # User commands
```

## Troubleshooting

### "API key is not configured"

Run `maids config set-api-key <token>` to set your CLI agent token.

### "Connection Error"

- Check your internet connection
- Verify the API URL is correct
- Ensure the API server is running

### "HTTP 401: Unauthorized"

Your CLI agent token may be invalid. Ensure it matches `MCP_SECRET_KEY` in your `.env` file.

## License

Proprietary - Maids.ng