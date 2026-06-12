const axios = require("axios");
const config = require("../config");

/**
 * Axios instance pre-configured for the Maids.ng staging API.
 *
 * All requests automatically include the API key in the Authorization header
 * and use the configured base URL. Timeout is 15 seconds.
 */
const headers = { "Content-Type": "application/json" };
if (config.maidsNgApiKey) {
  headers["Authorization"] = "Bearer " + config.maidsNgApiKey;
}

const client = axios.create({
  baseURL: config.maidsNgApiUrl,
  timeout: 15000,
  headers: headers,
});

// ---------------------------------------------------------------------------
// Request / response interceptors for logging
// ---------------------------------------------------------------------------

client.interceptors.request.use(
  function (req) {
    console.log("[maidsNgApi] -> " + req.method.toUpperCase() + " " + req.baseURL + req.url);
    return req;
  },
  function (err) {
    console.error("[maidsNgApi] Request error:", err.message);
    return Promise.reject(err);
  }
);

client.interceptors.response.use(
  function (res) {
    console.log("[maidsNgApi] <- " + res.status + " " + res.config.url);
    return res;
  },
  function (err) {
    var status = err.response ? err.response.status : "NETWORK";
    var url = err.config ? err.config.url : "unknown";
    console.error("[maidsNgApi] <- " + status + " " + url + ": " + err.message);
    return Promise.reject(err);
  }
);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Normalise an axios error into a consistent { error, status } shape
 * that callers can forward to Vapi without leaking internals.
 */
function normaliseError(err) {
  if (err.response && err.response.data) {
    var data = err.response.data;
    return {
      error: data.message || data.error || JSON.stringify(data),
      status: err.response.status,
    };
  }
  return { error: err.message || "Unknown upstream error", status: 502 };
}

/**
 * Thin wrapper around client.request that catches and normalises errors.
 */
async function request(method, url, data, params) {
  try {
    var res = await client.request({ method: method, url: url, data: data, params: params });
    return res.data;
  } catch (err) {
    throw normaliseError(err);
  }
}

// ---------------------------------------------------------------------------
// Tool methods
// ---------------------------------------------------------------------------

/**
 * Search maids by location, nationality, skills, experience, rate.
 */
async function searchMaids(params) {
  return request("GET", "/maids", null, params);
}

/**
 * Get full details for a specific maid by ID.
 */
async function getMaidDetails(params) {
  var maidId = params.maidId;
  var rest = Object.assign({}, params);
  delete rest.maidId;
  return request("GET", "/maids/" + maidId, null, rest);
}

/**
 * Get availability schedule for a specific maid.
 */
async function getMaidAvailability(params) {
  var maidId = params.maidId;
  var rest = Object.assign({}, params);
  delete rest.maidId;
  return request("GET", "/maids/" + maidId, null, rest);
}

/**
 * Search employers by location, service type, budget.
 */
async function searchEmployers(params) {
  return request("GET", "/maids", null, params);
}

/**
 * Get full details for a specific employer by ID.
 */
async function getEmployerDetails(params) {
  var employerId = params.employerId;
  return request("GET", "/maids/" + employerId, null, {});
}

/**
 * Get requirements posted by a specific employer.
 */
async function getEmployerRequirements(params) {
  return request("GET", "/maids", null, params);
}

/**
 * Schedule an interview between a maid and employer.
 */
async function scheduleInterview(params) {
  return request("POST", "/matching/find", params);
}

/**
 * Update the status of a placement (e.g. pending -> active -> completed).
 */
async function updatePlacementStatus(params) {
  var placementId = params.placementId;
  var rest = Object.assign({}, params);
  delete rest.placementId;
  return request("POST", "/matching/find", rest);
}

module.exports = {
  client: client,
  searchMaids: searchMaids,
  getMaidDetails: getMaidDetails,
  getMaidAvailability: getMaidAvailability,
  searchEmployers: searchEmployers,
  getEmployerDetails: getEmployerDetails,
  getEmployerRequirements: getEmployerRequirements,
  scheduleInterview: scheduleInterview,
  updatePlacementStatus: updatePlacementStatus,
};
