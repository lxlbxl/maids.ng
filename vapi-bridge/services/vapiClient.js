const axios = require("axios");
const config = require("../config");

/**
 * Vapi API client for outbound calls.
 *
 * Creates calls with assistantOverrides to inject per-call context
 * (recipient info, call objective, dynamic system prompt).
 */

const VAPI_BASE_URL = "https://api.vapi.ai";

/**
 * Build a dynamic system prompt based on call context.
 *
 * @param {object} context - Call context from the API request
 * @param {string} context.recipientName - Name of the person being called
 * @param {string} context.recipientType - "maid" or "employer"
 * @param {string} context.recipientPhone - Phone number in E.164 format
 * @param {string} context.objective - What this call aims to achieve
 * @param {string} [context.backgroundInfo] - Any additional context about the recipient
 * @param {string} [context.additionalInstructions] - Extra instructions for Aisha
 * @param {string} [context.specificMaidId] - If calling about a specific maid
 * @param {string} [context.specificEmployerId] - If calling about a specific employer
 */
function buildSystemPrompt(context) {
  var recipientName = context.recipientName || "the recipient";
  var recipientType = (context.recipientType || "unknown").toLowerCase();
  var objective = context.objective || "Have a professional conversation";
  var backgroundInfo = context.backgroundInfo || "";
  var additionalInstructions = context.additionalInstructions || "";

  var contextSection = [
    "## Call Context",
    "- Recipient: " + recipientName,
    "- Type: " + recipientType,
    "- Phone: " + (context.recipientPhone || "unknown"),
    "- Call objective: " + objective,
  ];

  if (backgroundInfo) {
    contextSection.push("- Background: " + backgroundInfo);
  }
  if (context.specificMaidId) {
    contextSection.push("- Related maid ID: " + context.specificMaidId);
  }
  if (context.specificEmployerId) {
    contextSection.push("- Related employer ID: " + context.specificEmployerId);
  }

  var systemPrompt = [
    "You are Aisha, a professional outbound calling agent for Maids.ng.",
    "",
    contextSection.join("\n"),
    "",
    "## Identity & Tone",
    "- Name: Aisha",
    "- Warm, professional, trustworthy with Nigerian friendly tone",
    "- Use respectful greetings like Good morning sir/ma",
    "",
    "## Core Instructions",
    "1. Stay focused on achieving the call objective above",
    "2. Be direct and purposeful while remaining warm and professional",
    "3. If the call objective is achieved, wrap up the conversation naturally",
    "4. If the recipient has questions, use your tools to look up information from the Maids.ng database",
    "",
    "## Available Tools",
    "- search_maids: Search by location, skills, experience",
    "- get_maid_details: Full profile with experience and certs",
    "- get_maid_availability: Check if currently available",
    "- search_employers: Look up employer/client records",
    "- get_employer_details: Full employer requirements and history",
    "- get_employer_requirements: Staffing requirements listed",
    "- schedule_interview: Book interview between employer and maid",
    "- update_placement_status: Update ongoing placement status",
    "",
    "## Guardrails - Privacy & NDPR Compliance",
    "- NEVER share maid full name, phone, or address without authorization",
    "- Only share professional details: experience, skills, certs",
    "- Never ask for bank details, BVN, or sensitive personal info",
    "- Treat all data as confidential",
    "",
    "## Guardrails - Responsible AI",
    "- NEVER make promises about placement guarantees or timing",
    "- Never discriminate based on tribe, religion, ethnicity",
    "- If caller sounds distressed, listen and escalate to supervisor",
    "- Be honest if no suitable match found",
    "- Do not pressure callers into decisions",
    "",
    "## Guardrails - Safety",
    "- If caller reports incident, immediately escalate to supervisor",
    "- Never provide medical, legal, or immigration advice",
    "- If you suspect fraud, end call and flag security team",
    "- Verify caller identity before sharing account info",
    "",
    "## Restrictions",
    "- Do NOT invent information about maids or employers",
    "- Do NOT process payments or collect money",
    "- Do NOT share internal policies or decision logic",
    "- Do NOT make legal claims or representations",
    "- If out of scope, offer to connect with human supervisor",
  ];

  if (additionalInstructions) {
    systemPrompt.push("");
    systemPrompt.push("## Additional Instructions");
    systemPrompt.push(additionalInstructions);
  }

  return systemPrompt.join("\n");
}

/**
 * Build a context-aware first greeting.
 *
 * @param {object} context - Call context
 * @returns {string} First message for the call
 */
function buildFirstMessage(context) {
  var recipientName = context.recipientName;
  var recipientType = (context.recipientType || "").toLowerCase();

  if (recipientName) {
    return (
      "Hello " +
      recipientName +
      ", this is Aisha calling from Maids.ng. How are you doing today?"
    );
  }

  return "Good day, this is Aisha calling from Maids.ng. How are you doing today?";
}

/**
 * Initiate an outbound call via the Vapi API with dynamic context.
 *
 * @param {object} callConfig
 * @param {string} callConfig.recipientPhone - E.164 phone number
 * @param {object} callConfig.context - Call context for prompt injection
 * @param {string} [callConfig.assistantId] - Override assistant ID
 * @param {string} [callConfig.phoneNumberId] - Override phone number ID
 * @returns {Promise<object>} Vapi call object
 */
async function initiateCall(callConfig) {
  var recipientPhone = callConfig.recipientPhone;
  var context = callConfig.context || {};
  var assistantId = callConfig.assistantId || config.vapiAssistantId;
  var phoneNumberId = callConfig.phoneNumberId || config.vapiPhoneNumberId;

  if (!config.vapiKey) {
    throw { error: "VAPI_API_KEY not configured", status: 500 };
  }

  // Build dynamic prompt and greeting from context
  var systemPrompt = buildSystemPrompt(context);
  var firstMessage = context.firstMessage || buildFirstMessage(context);

  var requestBody = {
    assistantId: assistantId,
    phoneNumberId: phoneNumberId,
    customer: {
      number: recipientPhone,
    },
    assistantOverrides: {
      firstMessage: firstMessage,
      model: {
        provider: "openai",
        model: "gpt-4o",
        messages: [
          {
            role: "system",
            content: systemPrompt,
          },
        ],
      },
    },
  };

  console.log(
    "[vapiClient] Initiating call to " +
      recipientPhone +
      " with objective: " +
      (context.objective || "default")
  );
  console.log(
    "[vapiClient] System prompt length: " + systemPrompt.length + " chars"
  );

  var response = await axios.post(VAPI_BASE_URL + "/call/phone", requestBody, {
    headers: {
      Authorization: "Bearer " + config.vapiKey,
      "Content-Type": "application/json",
    },
    timeout: 30000,
  });

  return response.data;
}

/**
 * Get the status and transcript of a call.
 *
 * @param {string} callId - Vapi call ID
 * @returns {Promise<object>} Call details including transcript
 */
async function getCallDetails(callId) {
  if (!config.vapiKey) {
    throw { error: "VAPI_API_KEY not configured", status: 500 };
  }

  var response = await axios.get(VAPI_BASE_URL + "/call/" + callId, {
    headers: {
      Authorization: "Bearer " + config.vapiKey,
    },
    timeout: 15000,
  });

  return response.data;
}

module.exports = {
  initiateCall: initiateCall,
  getCallDetails: getCallDetails,
  buildSystemPrompt: buildSystemPrompt,
  buildFirstMessage: buildFirstMessage,
};
