/**
 * Configuration module for Vapi-Maids.ng middleware server.
 *
 * All values are read from environment variables with sensible defaults.
 * Copy .env.example to .env and fill in the required values.
 */

require("dotenv").config();

const config = {
  /** HTTP port the server listens on. */
  port: parseInt(process.env.PORT, 10) || 3000,

  /** Base URL for the Maids.ng staging API. */
  maidsNgApiUrl: process.env.MAIDS_NG_API_URL || "https://staging-api.maids.ng/v1",

  /** API key sent in the Authorization header to Maids.ng. */
  maidsNgApiKey: process.env.MAIDS_NG_API_KEY || "",

  /**
   * Optional webhook signing secret for Vapi.
   * When set, incoming requests are validated via HMAC-SHA256 signature
   * in the x-vapi-signature header.
   */
  vapiWebhookSecret: process.env.VAPI_WEBHOOK_SECRET || "",

  /** Log level (error | warn | info | debug). */
  logLevel: process.env.LOG_LEVEL || "info",

  /** Vapi API key for outbound calls. */
  vapiKey: process.env.VAPI_API_KEY || "",

  /** Default Vapi assistant ID (Aisha). */
  vapiAssistantId: process.env.VAPI_ASSISTANT_ID || "",

  /** Default Vapi phone number ID. */
  vapiPhoneNumberId: process.env.VAPI_PHONE_NUMBER_ID || "",
};

module.exports = config;