/**
 * server.js - Main Express server for Vapi-Maids.ng middleware.
 *
 * Receives Vapi tool-call webhooks and proxies them to the Maids.ng staging API.
 * Routes:  POST /api/vapi/tools/*  - Tool-call proxy endpoints
 *          GET  /health             - Health check for Docker / load balancers
 */

const express = require("express");
const cors = require("cors");
const helmet = require("helmet");
const config = require("./config");
const toolsRouter = require("./routes/tools");
const callsRouter = require("./routes/calls");

const app = express();

// ---------------------------------------------------------------------------
// Global middleware
// ---------------------------------------------------------------------------

// Security headers (relaxed CSP for a pure API server)
app.use(helmet({ contentSecurityPolicy: false }));

// CORS: allow all origins in development; restrict in production
app.use(cors());

// Parse JSON request bodies (1 MB limit for large tool payloads)
app.use(express.json({ limit: "1mb" }));

// Simple request logger for debugging
app.use(function (req, _res, next) {
  console.log("[request] " + req.method + " " + req.originalUrl);
  next();
});

// ---------------------------------------------------------------------------
// Routes
// ---------------------------------------------------------------------------

/**
 * Health check endpoint.
 * Used by Docker HEALTHCHECK and upstream load balancers.
 */
app.get("/health", function (_req, res) {
  res.json({
    status: "ok",
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
  });
});

// Vapi tool-call proxy routes
app.use("/api/vapi/tools", toolsRouter);

// Outbound call initiation with context injection
app.use("/api/vapi", callsRouter);

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------

// 404 handler
app.use(function (_req, res) {
  res.status(404).json({ error: "Not found" });
});

// Global error handler - catches unhandled errors from all routes
app.use(function (err, _req, res, _next) {
  console.error("[error]", err);
  var status = err.status || 500;
  var message = err.error || err.message || "Internal server error";
  res.status(status).json({ error: message });
});

// ---------------------------------------------------------------------------
// Start server
// ---------------------------------------------------------------------------

app.listen(config.port, function () {
  console.log("[server] Vapi-Maids.ng middleware listening on port " + config.port);
  console.log("[server] Maids.ng API URL: " + config.maidsNgApiUrl);
  console.log("[server] Webhook signature validation: " + (config.vapiWebhookSecret ? "ENABLED" : "DISABLED"));
});

module.exports = app;
