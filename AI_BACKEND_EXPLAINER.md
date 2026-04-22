# Maids.ng: AI-Powered Agentic Backend Explainer

## 🏗️ Architecture Overview
The Maids.ng v2 backend is built on a modular **Agentic AI** architecture. Instead of a single monolithic matching algorithm, the system decomposes marketplace operations into specialized **Agents**. Each agent is responsible for a specific domain, mimicking a real-world agency's organizational structure.

The system is designed to evolve from simple heuristic rules to complex LLM-driven reasoning, with a centralized "Agent Ledger" recording every decision for transparency and auditability.

---

## 🤖 The Agent Roster

### 1. **Scout Agent** ([ScoutAgent.php](file:///c:/Users/Alex/TraeCoder/Maids.ng/app/Services/Agents/ScoutAgent.php))
**Role**: The Matchmaker.
- **Logic**: Uses a weighted scoring algorithm (0-100) to match employers with maids.
- **Criteria**: Help Type (35pts), Budget (25pts), Location (25pts), and Quality/Rating (15pts).
- **AI Integration**: Calculates "Match Confidence" based on profile completeness and verification status.

### 2. **Gatekeeper Agent** ([GatekeeperAgent.php](file:///c:/Users/Alex/TraeCoder/Maids.ng/app/Services/Agents/GatekeeperAgent.php))
**Role**: Identity & Trust.
- **Logic**: Automates the verification of NIN (National Identity Number) and background documents.
- **Workflow**: Auto-approves high-confidence matches from verification APIs and escalates suspicious or edge cases for manual admin review.
- **Current Status**: Uses a simulation layer for QoreID/Verification APIs.

### 3. **Sentinel Agent** ([SentinelAgent.php](file:///c:/Users/Alex/TraeCoder/Maids.ng/app/Services/Agents/SentinelAgent.php))
**Role**: Career Coach & Quality Assurance.
- **Logic**: Monitors performance metrics and provides proactive feedback.
- **AI Integration**: Uses LLMs (OpenAI/Gemini) to generate personalized "Profile Strength" reports and career tips for maids to help them secure better jobs.
- **Automated Actions**: Can auto-suspend profiles if ratings fall below a critical threshold.

### 4. **Referee Agent** ([RefereeAgent.php](file:///c:/Users/Alex/TraeCoder/Maids.ng/app/Services/Agents/RefereeAgent.php))
**Role**: Dispute Arbitration.
- **Logic**: Analyzes disputes (no-shows, unsatisfactory work) using both heuristics and LLM reasoning.
- **Workflow**: Determines refund eligibility based on the 10-day money-back guarantee and contract fairness.

### 5. **Concierge Agent** ([ConciergeAgent.php](file:///c:/Users/Alex/TraeCoder/Maids.ng/app/Services/Agents/ConciergeAgent.php))
**Role**: Automated Support.
- **Logic**: Handles common user queries (refunds, payouts, account security).
- **Current Status**: Uses keyword-based intent matching; scheduled for upgrade to full LLM-based support.

### 6. **Treasurer Agent** ([TreasurerAgent.php](file:///c:/Users/Alex/TraeCoder/Maids.ng/app/Services/Agents/TreasurerAgent.php))
**Role**: Financial Controller.
- **Logic**: Calculates commission-deducted payouts and flags unusually large transfers for anti-fraud review.
- **Current Status**: Logic is functional; real bank transfer API (Paystack/Flutterwave) integration is pending.

---

## 🧠 AI Infrastructure
- **Base Layer**: [AgentService.php](file:///c:/Users/Alex/TraeCoder/Maids.ng/app/Services/AgentService.php) provides the `think()` method for all agents, enabling LLM reasoning.
- **Provider System**: [AiService.php](file:///c:/Users/Alex/TraeCoder/Maids.ng/app/Services/Ai/AiService.php) routes requests to different LLM providers (OpenAI, OpenRouter) based on system settings.
- **Observability**: All agent decisions are logged in the `agent_activity_logs` table via the [AgentActivityLog](file:///c:/Users/Alex/TraeCoder/Maids.ng/app/Models/AgentActivityLog.php) model.

---

## ✅ Current Status & Gaps

### What is Working
- [x] **Modular Agent Architecture**: All agents are integrated and callable.
- [x] **Intelligent Matching**: The Scout Agent is fully operational in the front-end quiz flow.
- [x] **LLM Reasoning**: Sentinel and Referee agents successfully use external LLMs for complex decisions.
- [x] **Admin Visibility**: Basic activity logging is functional.
- [x] **Centralized Settings**: AI providers can be swapped via the database settings.

### ⚠️ Identified Gaps & Pending Items
1.  **Mocked Integrations**: Gatekeeper and Treasurer agents currently rely on simulation layers rather than real production APIs (QoreID, Paystack Payouts).
2.  **Concierge Upgrade**: needs transition from keyword detection to NLP for support queries.
3.  **Real-time Learning**: A feedback loop where a Referee dispute *automatically* lowers the Scout matching score is designed but not yet fully automated.
4.  **Error Resilience**: Need more robust circuit breakers for when external AI APIs (OpenAI/Gemini) are unreachable.

---

## 🚀 Roadmap to "Solid" Foundation
1.  **Production Keys**: Switch `AiService` to production API keys.
2.  **Workflow Orchestration**: Implement a central "Mission Control" dashboard for Admins to review agent escalations.
3.  **Verification API**: Connect Gatekeeper Agent to a real identity provider (e.g., QoreID).
4.  **Payout Automation**: Connect Treasurer Agent to Paystack's Transfer API.
