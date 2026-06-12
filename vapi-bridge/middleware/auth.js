var crypto = require("crypto");
var config = require("../config");

/**
 * Validate that the incoming request is a well-formed Vapi tool call.
 * Checks: body has toolName (string) and parameters (object).
 * Adds req.vapiBody with normalised payload.
 */
function validateVapiWebhook(req, res, next) {
  var body = req.body;
  if (!body || typeof body !== "object") {
    return res.status(400).json({ error: "Request body must be a JSON object" });
  }
  if (!body.toolName || typeof body.toolName !== "string") {
    return res.status(400).json({ error: "Missing or invalid toolName in request body" });
  }
  if (!body.parameters || typeof body.parameters !== "object") {
    return res.status(400).json({ error: "Missing or invalid parameters in request body" });
  }
  req.vapiBody = {
    toolName: body.toolName,
    parameters: body.parameters,
    callId: body.callId || "",
    assistantId: body.assistantId || "",
  };
  next();
}

/**
 * Verify Vapi webhook HMAC-SHA256 signature when VAPI_WEBHOOK_SECRET is set.
 * No-op when the secret is not configured.
 */
function verifySignature(req, res, next) {
  if (!config.vapiWebhookSecret) { return next(); }
  var signature = req.headers["x-vapi-signature"];
  if (!signature) {
    return res.status(401).json({ error: "Missing x-vapi-signature header" });
  }
  var rawBody = typeof req.body === "string" ? req.body : JSON.stringify(req.body);
  var expected = crypto.createHmac("sha256", config.vapiWebhookSecret).update(rawBody).digest("hex");
  var sigBuf = Buffer.from(signature, "hex");
  var expectedBuf = Buffer.from(expected, "hex");
  if (sigBuf.length !== expectedBuf.length || !crypto.timingSafeEqual(sigBuf, expectedBuf)) {
    return res.status(401).json({ error: "Invalid webhook signature" });
  }
  next();
}

module.exports = { validateVapiWebhook: validateVapiWebhook, verifySignature: verifySignature };