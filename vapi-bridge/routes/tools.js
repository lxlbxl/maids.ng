/**
 * routes/tools.js - Express router with POST endpoints for all 8 Vapi tools.
 *
 * Each endpoint:
 *   1. Accepts Vapi webhook body: { toolName, parameters, callId, assistantId }
 *   2. Validates input parameters
 *   3. Calls the Maids.ng API via the service layer
 *   4. Returns Vapi-compatible response: { results: [...] } or { error: "..." }
 */

const express = require("express");
const router = express.Router();
const api = require("../services/maidsNgApi");

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Wrap an async handler so thrown errors reach Express error middleware.
 */
function asyncHandler(fn) {
  return function (req, res, next) {
    Promise.resolve(fn(req, res, next)).catch(next);
  };
}

/**
 * Build a Vapi-compatible success response: { results: [...] }
 */
function ok(res, data) {
  return res.json({ results: Array.isArray(data) ? data : [data] });
}

/**
 * Build a Vapi-compatible error response: { error: "message" }
 */
function fail(res, status, message) {
  return res.status(status).json({ error: message });
}

/**
 * Validate req.body for a well-formed Vapi tool call.
 * Returns the normalised body or sends a 400 and returns null.
 */
function validateBody(req, res) {
  var body = req.body;
  if (!body || typeof body !== "object") {
    fail(res, 400, "Request body must be a JSON object");
    return null;
  }
  if (!body.toolName || typeof body.toolName !== "string") {
    fail(res, 400, "Missing or invalid toolName");
    return null;
  }
  if (!body.parameters || typeof body.parameters !== "object") {
    fail(res, 400, "Missing or invalid parameters");
    return null;
  }
  return {
    toolName: body.toolName,
    parameters: body.parameters,
    callId: body.callId || "",
    assistantId: body.assistantId || "",
  };
}

/**
 * Validate that params contains the given required fields.
 * Returns true on success, or sends a 422 error and returns false.
 */
function requireFields(params, fields, res) {
  for (var i = 0; i < fields.length; i++) {
    var field = fields[i];
    if (params[field] === undefined || params[field] === null || params[field] === "") {
      fail(res, 422, "Missing required parameter: " + field);
      return false;
    }
  }
  return true;
}

// ===========================================================================
// POST /search_maids
// Search for maids by location, nationality, skills, experience, or rate.
// ===========================================================================
router.post("/search_maids", asyncHandler(async function (req, res) {
  var vapi = validateBody(req, res);
  if (!vapi) return;
  try {
    var data = await api.searchMaids(vapi.parameters);
    return ok(res, data);
  } catch (err) {
    return fail(res, err.status || 500, err.error || err.message);
  }
}));

// ===========================================================================
// POST /get_maid_details
// Get full profile details for a specific maid.
// Required parameters: maidId
// ===========================================================================
router.post("/get_maid_details", asyncHandler(async function (req, res) {
  var vapi = validateBody(req, res);
  if (!vapi) return;
  if (!requireFields(vapi.parameters, ["maidId"], res)) return;
  try {
    var data = await api.getMaidDetails(vapi.parameters);
    return ok(res, data);
  } catch (err) {
    return fail(res, err.status || 500, err.error || err.message);
  }
}));

// ===========================================================================
// POST /get_maid_availability
// Get availability schedule for a specific maid.
// Required parameters: maidId
// Optional parameters: dateFrom, dateTo
// ===========================================================================
router.post("/get_maid_availability", asyncHandler(async function (req, res) {
  var vapi = validateBody(req, res);
  if (!vapi) return;
  if (!requireFields(vapi.parameters, ["maidId"], res)) return;
  try {
    var data = await api.getMaidAvailability(vapi.parameters);
    return ok(res, data);
  } catch (err) {
    return fail(res, err.status || 500, err.error || err.message);
  }
}));

// ===========================================================================
// POST /search_employers
// Search for employers by location, service type, or budget.
// ===========================================================================
router.post("/search_employers", asyncHandler(async function (req, res) {
  var vapi = validateBody(req, res);
  if (!vapi) return;
  try {
    var data = await api.searchEmployers(vapi.parameters);
    return ok(res, data);
  } catch (err) {
    return fail(res, err.status || 500, err.error || err.message);
  }
}));

// ===========================================================================
// POST /get_employer_details
// Get full profile details for a specific employer.
// Required parameters: employerId
// ===========================================================================
router.post("/get_employer_details", asyncHandler(async function (req, res) {
  var vapi = validateBody(req, res);
  if (!vapi) return;
  if (!requireFields(vapi.parameters, ["employerId"], res)) return;
  try {
    var data = await api.getEmployerDetails(vapi.parameters);
    return ok(res, data);
  } catch (err) {
    return fail(res, err.status || 500, err.error || err.message);
  }
}));

// ===========================================================================
// POST /get_employer_requirements
// Get requirements posted by a specific employer.
// Required parameters: employerId
// ===========================================================================
router.post("/get_employer_requirements", asyncHandler(async function (req, res) {
  var vapi = validateBody(req, res);
  if (!vapi) return;
  if (!requireFields(vapi.parameters, ["employerId"], res)) return;
  try {
    var data = await api.getEmployerRequirements(vapi.parameters);
    return ok(res, data);
  } catch (err) {
    return fail(res, err.status || 500, err.error || err.message);
  }
}));

// ===========================================================================
// POST /schedule_interview
// Schedule an interview between a maid and an employer.
// Required parameters: maidId, employerId, scheduledAt (ISO 8601)
// Optional parameters: location, notes
// ===========================================================================
router.post("/schedule_interview", asyncHandler(async function (req, res) {
  var vapi = validateBody(req, res);
  if (!vapi) return;
  if (!requireFields(vapi.parameters, ["maidId", "employerId", "scheduledAt"], res)) return;
  try {
    var data = await api.scheduleInterview(vapi.parameters);
    return ok(res, data);
  } catch (err) {
    return fail(res, err.status || 500, err.error || err.message);
  }
}));

// ===========================================================================
// POST /update_placement_status
// Update the status of a placement record.
// Required parameters: placementId, status
// Valid statuses: pending, interview_scheduled, interview_completed, offered,
//   accepted, rejected, active, completed, cancelled
// Optional parameters: notes
// ===========================================================================
var VALID_STATUSES = [
  "pending", "interview_scheduled", "interview_completed",
  "offered", "accepted", "rejected", "active", "completed", "cancelled"
];

router.post("/update_placement_status", asyncHandler(async function (req, res) {
  var vapi = validateBody(req, res);
  if (!vapi) return;
  if (!requireFields(vapi.parameters, ["placementId", "status"], res)) return;
  if (VALID_STATUSES.indexOf(vapi.parameters.status) === -1) {
    return fail(res, 422, "Invalid status value. Must be one of: " + VALID_STATUSES.join(", "));
  }
  try {
    var data = await api.updatePlacementStatus(vapi.parameters);
    return ok(res, data);
  } catch (err) {
    return fail(res, err.status || 500, err.error || err.message);
  }
}));

module.exports = router;
