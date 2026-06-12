# Maids.ng MCP Server (VPS Deployment)

This is the standalone Model Context Protocol (MCP) server for Maids.ng. It acts as a secure, HTTP-based intermediary between AI agents (like Claude Desktop) and your primary Maids.ng web application.

## Architecture: Dedicated Agent Routes (Option B)

To ensure high observability, security, and scalability, this MCP Server operates using a **Dedicated Agent Routes** architecture:

1. **Complete Separation:** The server proxies all AI tool calls to a specialized `Route::prefix('v1/mcp')` on your primary Laravel backend. It does not hack or reuse standard user/session routes.
2. **Stateless Authentication:** It sidesteps Laravel Sanctum session constraints. Incoming AI requests are validated using an `MCP_AUTH_TOKEN`. The server then signs its backend requests with a static `MAIDS_NG_API_TOKEN` (which your Laravel backend validates via the `mcp.auth` middleware using `MCP_SECRET_KEY`).
3. **Targeted Actions:** Instead of relying on `$request->user()`, the MCP server passes explicit IDs (`maid_id`, `employer_id`, `booking_id`) to the backend `McpAgentController`, allowing the AI to manage *any* user securely.

---

## The 11 Core Agent Tools

This server exposes a massive suite of capabilities covering the entire Maids.ng platform:

### 🏠 Employer Management
- **`get_employer_preferences`**: Read an employer's current schedule, budget, and help types.
- **`update_employer_preferences`**: Dynamically adjust an employer's needs.
- **`trigger_ai_matching`**: Force the backend matching algorithm to generate new assignments.

### 🧹 Maid Management
- **`get_maid_profile`**: Read a maid's bio, skills, and verification status.
- **`update_maid_availability`**: Toggle a maid's availability status on or off.
- **`get_maid_earnings`**: Read wallet balances and upcoming salary info.

### 📅 Bookings & Support
- **`create_booking`**: Initiate a new engagement between an employer and maid.
- **`cancel_booking`**: Terminate an active or pending booking.
- **`get_user_bookings`**: Universal fetching of assignments/bookings for either a maid or employer.
- **`get_booking_status`**: Pinpoint checking of a specific booking.
- **`create_review`**: Submit positive feedback/ratings.
- **`file_dispute`**: Escalate issues by opening a support ticket.

---

## 🚀 VPS Deployment Guide

This repository is completely containerized and production-ready for deployment on a Linux VPS.

### Prerequisites
- A Linux VPS with root access.
- **Docker** and **Docker Compose** installed.
- A domain name pointing to your VPS IP (e.g., `mcp.maids.ng`).

### 1. Initial Setup
1. Clone your repository to the VPS and navigate to this folder:
   ```bash
   cd mcp
   ```
2. Set up your environment variables:
   ```bash
   cp .env.example .env
   nano .env
   ```
   **Important Variables:**
   - `MCP_AUTH_TOKEN`: A secure random string you generate. You will put this into your Claude Desktop or AI Client configuration.
   - `MAIDS_NG_API_TOKEN`: The exact same secure random string you set as `MCP_SECRET_KEY` in your main Laravel `.env` file on shared hosting.

### 2. SSL/TLS Certificates (Let's Encrypt)
Nginx requires SSL certificates to start. We use Let's Encrypt. 
Run the following temporary Certbot container to generate your initial certificates:

```bash
docker run -it --rm --name certbot \
  -v "$(pwd)/certbot/conf:/etc/letsencrypt" \
  -v "$(pwd)/certbot/www:/var/www/certbot" \
  -p 80:80 \
  certbot/certbot certonly --standalone -d mcp.maids.ng
```
*(Replace `mcp.maids.ng` with your actual domain).*

### 3. Spin Up the Containers
Once your `.env` is configured and certificates are present, launch the server:

```bash
docker-compose up -d --build
```

**What happens?**
- `FastMCP` spins up in a `python:3.12-slim` container, binding to `0.0.0.0` to ensure internal Docker network visibility.
- `Nginx` spins up, routing all HTTPS traffic to the Python container. It includes optimized configurations (`proxy_buffering off`, `Connection ''`) specifically tuned to keep Server-Sent Event (SSE) streams alive for long-running AI queries.

### 4. Connect Your AI Agent
Configure your AI Agent (like Claude Desktop) to connect to your live VPS:
- **Transport URL:** `https://mcp.maids.ng/`
- **Headers:** `Authorization: Bearer <your-MCP_AUTH_TOKEN>`

---

## Local Testing & Development

If you want to test the tools locally before pushing to the VPS:

1. Install dependencies:
   ```bash
   pip install -r requirements.txt
   ```
2. Run the official MCP Inspector (requires Node.js):
   ```bash
   npx @modelcontextprotocol/inspector python main.py
   ```
   *This will spin up a local web UI where you can trigger the tools and ensure they successfully ping your shared-hosting API.*
