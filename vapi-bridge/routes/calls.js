var express = require("express");
var router = express.Router();
var vapiClient = require("../services/vapiClient");

/**
 * POST /api/vapi/call - Initiate an outbound call with dynamic context
 *
 * This endpoint creates an outbound call via Vapi, injecting per-call
 * context (recipient info, objective, background) into Aisha's system
 * prompt dynamically. This is the core of the context-injection pipeline.
 *
 * Request body:
 * {
 *   "recipientPhone": "+2348068000981",  // Required: E.164 format
 *   "context": {
 *     "recipientName": "Jane",            // Who we're calling
 *     "recipientType": "maid",            // "maid" or "employer"
 *     "objective": "Follow up on registration",  // What we want to achieve
 *     "backgroundInfo": "Signed up last week",   // Optional context
 *     "additionalInstructions": "Ask about her cooking skills",  // Optional
 *     "firstMessage": "...",              // Optional: override greeting
 *     "specificMaidId": "...",            // Optional: link to maid record
 *     "specificEmployerId": "..."         // Optional: link to employer record
 *   }
 * }
 */
router.post("/call", async function (req, res, next) {
  try {
    var recipientPhone = req.body.recipientPhone;
    var context = req.body.context || {};

    if (!recipientPhone) {
      return res.status(400).json({ error: "recipientPhone is required" });
    }

    // Validate E.164 format
    if (!recipientPhone.startsWith("+") || recipientPhone.length < 10) {
      return res
        .status(400)
        .json({ error: "recipientPhone must be in E.164 format (e.g. +234...)" });
    }

    var result = await vapiClient.initiateCall({
      recipientPhone: recipientPhone,
      context: context,
      assistantId: req.body.assistantId,
      phoneNumberId: req.body.phoneNumberId,
    });

    res.json({
      callId: result.id,
      status: result.status,
      assistantId: result.assistantId,
      recipientPhone: recipientPhone,
      objective: context.objective || "default",
      createdAt: result.createdAt,
    });
  } catch (err) {
    if (err.status) {
      return res.status(err.status).json({ error: err.error });
    }
    console.error("[calls] Error initiating call:", err.message || err);
    res.status(500).json({ error: "Failed to initiate call" });
  }
});

/**
 * GET /api/vapi/call/:callId - Get call status and transcript
 *
 * Returns the full call details including status, transcript,
 * cost, duration, and analysis summary.
 */
router.get("/call/:callId", async function (req, res, next) {
  try {
    var callId = req.params.callId;

    if (!callId) {
      return res.status(400).json({ error: "callId is required" });
    }

    var result = await vapiClient.getCallDetails(callId);

    res.json({
      callId: result.id,
      status: result.status,
      endedReason: result.endedReason,
      cost: result.cost,
      createdAt: result.createdAt,
      endedAt: result.endedAt,
      transcript: result.transcript,
      summary: result.summary,
      analysis: result.analysis,
      customer: result.customer,
      assistantId: result.assistantId,
    });
  } catch (err) {
    if (err.status) {
      return res.status(err.status).json({ error: err.error });
    }
    console.error("[calls] Error fetching call:", err.message || err);
    res.status(500).json({ error: "Failed to fetch call details" });
  }
});

module.exports = router;
