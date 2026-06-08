# Maids.ng Agent API v1 — Complete Reference

> **For:** External agents (OpenClaw, n8n, Hermes, Claude Code, Paperclip, custom scripts)  
> **Base URL:** `https://maids.ng/api/agent-api/v1` (live) / `https://maids.ai20.city/api/agent-api/v1` (staging)  
> **Auth:** `Authorization: Bearer mng_sk_{64-char-key}`  
> **Response envelope:** `{ success, message, data, meta: { timestamp, request_id, api_version } }`

---

## Quick Start

```bash
# Set your API key
export AGENT_KEY="mng_sk_your_key_here"

# Test connection
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/metrics/platform

# Find a user
curl -s -X POST \
  -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phone":"08012345678"}' \
  https://maids.ng/api/agent-api/v1/users/lookup
```

---

## Authentication

All endpoints require a `Bearer` token with the `mng_sk_` prefix. Tokens are hashed (SHA-256) in the database and never stored in plaintext.

**Headers required on every request:**
```
Authorization: Bearer mng_sk_{64-character-hex-key}
Content-Type: application/json
```

**401 response (missing/invalid key):**
```json
{ "success": false, "message": "Unauthorized. Provide Bearer token with mng_sk_ prefix." }
```

**403 response (expired key):**
```json
{ "success": false, "message": "Invalid or expired API key." }
```

---

## Response Format

Every endpoint returns the same envelope:

```json
{
  "success": true,
  "message": "Human-readable status",
  "data": { /* payload — varies by endpoint */ },
  "meta": {
    "timestamp": "2026-06-08T17:00:00+00:00",
    "request_id": "req_6a26f53007f6b4.62652310",
    "api_version": "v1"
  }
}
```

**Error responses:**
```json
{
  "success": false,
  "message": "Validation failed",
  "code": "VALIDATION_ERROR",
  "errors": { "phone": ["The phone field is required."] },
  "meta": { "timestamp": "...", "request_id": "...", "api_version": "v1" }
}
```

---

## Group 1 — Platform & Health

Endpoints for operational dashboards. Run at the start of every agent session.

### GET /metrics/platform
Full platform KPIs.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/metrics/platform
```
**Response:**
```json
{
  "success": true,
  "data": {
    "employers_registered_today": 0,
    "employers_registered_7d": 1,
    "quiz_completed_not_paid": 0,
    "payments_today": 0,
    "active_assignments": 0,
    "total_maids": 7,
    "total_employers": 7,
    "payments_this_month": 0,
    "active_bookings": 0,
    "conversion_rate": 0
  }
}
```
**Use case:** CEO/Ops agent polls every 4 hours to monitor platform health.

### GET /metrics/agent-health
Circuit breaker states for all internal agents.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/metrics/agent-health
```
**Response:**
```json
{
  "data": { "circuit_breakers": [], "status": "healthy" }
}
```

### GET /metrics/revenue
Revenue summary — GMV, fees, escrow.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/metrics/revenue
```

### GET /metrics/funnel
Conversion funnel — visitors → registrations → quiz → payment → active.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/metrics/funnel
```

---

## Group 2 — Users

Every agent uses these to resolve who they're dealing with.

### POST /users/lookup
Find user by phone, email, or ID. The primary resolver for all agents.

```bash
# By ID
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"user_id":1}' \
  https://maids.ng/api/agent-api/v1/users/lookup

# By phone
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phone":"08012345678"}' \
  https://maids.ng/api/agent-api/v1/users/lookup

# By email
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@maids.ng"}' \
  https://maids.ng/api/agent-api/v1/users/lookup
```
**Response:**
```json
{
  "data": {
    "id": 1,
    "name": "Administrator",
    "email": "admin@maids.ng",
    "phone": "08000000000",
    "role": "admin",
    "status": "active"
  }
}
```
**Use case:** VAPI call handler resolves caller by phone number before opening conversation.

### GET /users/{id}/summary
Full user context — onboarding, preferences, recent messages, lead score.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/users/1/summary
```
**Response:**
```json
{
  "data": {
    "user": { "id": 1, "name": "Administrator", "role": "admin" },
    "onboarding": { "maid_profile": null },
    "latest_preference": null,
    "recent_messages": []
  }
}
```
**Use case:** Sales agent reads full context before calling a hot lead.

### POST /users
Create employer or maid account from agent interaction.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane Doe","phone":"08012345678","role":"employer"}' \
  https://maids.ng/api/agent-api/v1/users
```
**Response:** `{ "data": { "user_id": 18 }, "message": "User created" }`

### PATCH /users/{id}
Update user profile fields.

```bash
curl -s -X PATCH -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"status":"active","name":"Jane Doe Updated"}' \
  https://maids.ng/api/agent-api/v1/users/18
```

### GET /users/{id}/conversation-history
Last 50 messages across all channels for this user.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/users/1/conversation-history
```
**Use case:** Agent reads conversation history before responding — prevents asking questions already answered.

### GET /users/scan/inactive
Employers with no login in 30+ days.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/users/scan/inactive
```

### GET /users/scan/incomplete-maids
Maids with profile completeness < 80%.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/users/scan/incomplete-maids
```

---

## Group 3 — Onboarding

The Onboarding Agent uses these to drive users through registration → quiz → payment → active.

### Scan Endpoints

| Endpoint | Returns | Use Case |
|----------|---------|----------|
| `GET /onboarding/scan/needs-welcome-call` | Users registered 1-3h ago, not called | Onboarding agent welcome queue |
| `GET /onboarding/scan/quiz-abandoned` | Quiz started >2h ago, not completed | Call/sms reminder |
| `GET /onboarding/scan/awaiting-payment` | Quiz completed, matches shown, no payment | Sales handoff trigger |
| `GET /onboarding/scan/maid-profile-incomplete` | Profile < 80% complete, >24h old | Profile completion nudge |
| `GET /onboarding/scan/nin-pending` | NIN pending > 48h | Verification follow-up |
| `GET /onboarding/scan/abandoned` | No activity for 7+ days | Winback campaign trigger |

```bash
# Quiz abandoned — core onboarding list
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/onboarding/scan/quiz-abandoned
```
**Response:**
```json
{
  "data": {
    "count": 4,
    "users": [
      {
        "user_id": 89,
        "name": "Ngozi O.",
        "phone": "+2348012345678",
        "quiz_started_at": "2026-05-03T10:00:00Z",
        "hours_since_start": 3.2,
        "current_step": 4,
        "total_steps": 8,
        "recommended_action": "call"
      }
    ]
  }
}
```

### GET /onboarding/{userId}
Full journey for a user — touchpoints, status, next step.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/onboarding/89
```

### PATCH /onboarding/{userId}/milestone
Record milestone confirmed during a call.

```bash
curl -s -X PATCH -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"milestone":"quiz_completed"}' \
  https://maids.ng/api/agent-api/v1/onboarding/89/milestone
```
**Body:** `{ milestone: "quiz_started" | "quiz_completed" | "payment_confirmed" | "nin_submitted" | "profile_updated" }`

### POST /onboarding/touchpoints
Log that a touchpoint was sent (prevents duplicates).

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"journey_id":14,"user_id":89,"touchpoint_type":"welcome_call","channel":"phone","status":"sent"}' \
  https://maids.ng/api/agent-api/v1/onboarding/touchpoints
```

### GET /onboarding/touchpoints/{journeyId}
All touchpoints for a journey.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/onboarding/touchpoints/14
```

### PATCH /onboarding/{journeyId}/status
Update journey status.

```bash
curl -s -X PATCH -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"status":"converted"}' \
  https://maids.ng/api/agent-api/v1/onboarding/14/status
```

### POST /onboarding/{journeyId}/note
Add agent note to journey.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"note":"Employer travelling until 10 May"}' \
  https://maids.ng/api/agent-api/v1/onboarding/14/note
```

---

## Group 4 — Matching & Assignments

### POST /matching/run
Run Scout matching for a preference.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"preference_id":1}' \
  https://maids.ng/api/agent-api/v1/matching/run
```

### POST /matching/assign
Create assignment between employer and maid.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"employer_id":2,"maid_id":3,"preference_id":1}' \
  https://maids.ng/api/agent-api/v1/matching/assign
```

### GET /assignments/{id}
Full assignment detail.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/assignments/1
```

### PATCH /assignments/{id}/status
Update assignment status.

```bash
curl -s -X PATCH -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"status":"active"}' \
  https://maids.ng/api/agent-api/v1/assignments/1/status
```

### GET /assignments/scan/no-start-date
Active assignments with no confirmed start date.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/assignments/scan/no-start-date
```

### GET /assignments/scan/expiring-soon
Assignments expiring within 7 days.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/assignments/scan/expiring-soon
```

---

## Group 5 — Payments & Wallets

### GET /payments/status/{userId}
Check if employer has confirmed payment.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/payments/status/2
```
**Response:**
```json
{
  "data": {
    "has_paid": true,
    "reference": "PAY-abc123",
    "paid_at": "2026-05-03T10:00:00Z",
    "payment_type": "matching_fee"
  }
}
```

### GET /payments/generate-link
Generate payment link.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  "https://maids.ng/api/agent-api/v1/payments/generate-link?user_id=2&amount=5000&type=matching_fee"
```

### GET /payments/scan/pending-72h
Employers with quiz completed 72h+ ago, no payment.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/payments/scan/pending-72h
```

### GET /wallets/scan/salary-delayed
Active assignments with salary overdue 3+ days.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/wallets/scan/salary-delayed
```

### POST /wallets/release-escrow
Release escrow for an assignment.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"assignment_id":1,"reason":"Monthly salary release"}' \
  https://maids.ng/api/agent-api/v1/wallets/release-escrow
```

---

## Group 6 — Fulfillment

The Fulfillment Agent drives cases through this pipeline:
```
payment_confirmed → contact_shared → salary_negotiation → salary_agreed
→ resumption_set → pre_arrival → day_one → active
```

### Scan Endpoints

| Endpoint | Returns | Trigger |
|----------|---------|---------|
| `GET /fulfillment/scan/all-active` | All active cases not at 'active' stage | Every 4 hours |
| `GET /fulfillment/scan/stalled` | Cases stuck >24h at same stage | Every 4 hours |
| `GET /fulfillment/scan/awaiting-first-day` | salary_agreed / resumption_set stage | Every 4 hours |
| `GET /fulfillment/scan/day-one-not-confirmed` | day_one stage, arrival not confirmed | Every 2 hours |
| `GET /fulfillment/scan/ready-to-activate` | day_one stage, 3+ days since confirmed | Every 4 hours |

```bash
# Stalled cases — urgent attention needed
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/fulfillment/scan/stalled
```
**Response:**
```json
{
  "data": {
    "count": 1,
    "cases": [
      {
        "id": 7,
        "employer_name": "Ngozi O.",
        "maid_name": "Blessing A.",
        "stage": "salary_negotiation",
        "hours_in_stage": 28,
        "last_contact_at": "2026-05-02T10:00:00Z",
        "recommended_action": "call_employer"
      }
    ]
  }
}
```

### POST /fulfillment/open
Open a fulfillment case after payment confirmed.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"employer_id":2,"maid_id":3,"preference_id":1}' \
  https://maids.ng/api/agent-api/v1/fulfillment/open
```

### GET /fulfillment/{id}
Full case detail with events.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/fulfillment/7
```

### GET /fulfillment/by-employer/{userId}
Active case for an employer.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/fulfillment/by-employer/2
```

### PATCH /fulfillment/{id}/stage
Advance case stage.

```bash
curl -s -X PATCH -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"stage":"salary_agreed","notes":"Both parties confirmed ₦60,000/month"}' \
  https://maids.ng/api/agent-api/v1/fulfillment/7/stage
```

### POST /fulfillment/{id}/record-salary
Record salary confirmation from one party.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"party":"employer","salary":60000}' \
  https://maids.ng/api/agent-api/v1/fulfillment/7/record-salary
```

### POST /fulfillment/{id}/set-start-date
Set confirmed start date.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"start_date":"2026-05-10","start_time":"08:00","employer_address":"12 Admiralty Way, Lekki Phase 1, Lagos"}' \
  https://maids.ng/api/agent-api/v1/fulfillment/7/set-start-date
```

### POST /fulfillment/{id}/confirm-arrival
Record first-day maid arrival.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"arrived":true,"notes":"Maid arrived at 07:45, employer confirmed"}' \
  https://maids.ng/api/agent-api/v1/fulfillment/7/confirm-arrival
```

### POST /fulfillment/{id}/activate
Mark case as fully active. Creates CS case.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -d '{}' https://maids.ng/api/agent-api/v1/fulfillment/7/activate
```

### POST /fulfillment/{id}/fail
Mark case as failed.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"reason":"Maid did not show up after 3 days"}' \
  https://maids.ng/api/agent-api/v1/fulfillment/7/fail
```

### POST /fulfillment/{id}/note
Add agent note to case.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"notes":"Called employer at 14:00 — confirmed salary terms"}' \
  https://maids.ng/api/agent-api/v1/fulfillment/7/note
```

### GET /fulfillment/events/{id}
Event timeline for a case.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/fulfillment/events/7
```

---

## Group 7 — Sales

The Sales Agent converts leads and re-engages lapsed employers.

### Scan Endpoints

| Endpoint | Returns | Use Case |
|----------|---------|----------|
| `GET /sales/scan/hot-leads` | Lead score 80+, no payment | Priority call queue |
| `GET /sales/scan/warm-leads` | Lead score 50-79, quiz started | WhatsApp nudge |
| `GET /sales/scan/payment-pending` | Matches shown >1h, no payment | Sales follow-up |
| `GET /sales/scan/winback-recent` | Last assignment ended 14-30d ago | Re-engagement |
| `GET /sales/scan/winback-lapsed` | No login 45+ days | Low priority |
| `GET /sales/scan/upsell-candidates` | Paid matching 48h+, no assignment | Premium upsell |

```bash
# Hot leads — highest priority
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/sales/scan/hot-leads
```
**Response:**
```json
{
  "data": {
    "count": 3,
    "leads": [
      {
        "user_id": 89,
        "name": "Ngozi O.",
        "phone": "+2348012345678",
        "lead_score": 85,
        "funnel_stage": "decision",
        "payment_link": "https://paystack.com/pay/..."
      }
    ]
  }
}
```

### GET /sales/pipeline/{userId}
Full pipeline for a user.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/sales/pipeline/89
```

### PATCH /sales/pipeline/{userId}
Update funnel stage and lead score.

```bash
curl -s -X PATCH -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"funnel_stage":"decision","lead_score":90,"notes":"Confirmed budget — ready to convert"}' \
  https://maids.ng/api/agent-api/v1/sales/pipeline/89
```

### POST /sales/pipeline/{userId}/event
Log a sales event.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"event_type":"call_placed","notes":"Discussed pricing","outcome":"interested"}' \
  https://maids.ng/api/agent-api/v1/sales/pipeline/89/event
```

### POST /sales/pipeline/{userId}/action-taken
Record outreach action (prevents duplicates).

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"action":"call","channel":"phone","message_preview":"Called to discuss maid options in Lekki"}' \
  https://maids.ng/api/agent-api/v1/sales/pipeline/89/action-taken
```

---

## Group 8 — Customer Success

The CS Agent manages active assignments, tickets, and maid exits.

### Case Management

#### GET /cs/cases/{id}
Full CS case with employer, maid, tickets.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/cs/cases/7
```

#### GET /cs/cases/by-employer/{userId}
Active CS case for employer.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/cs/cases/by-employer/2
```

#### Case Scan Endpoints

| Endpoint | Returns | Priority |
|----------|---------|----------|
| `GET /cs/cases/scan/at-risk` | health_status = `at_risk` | URGENT |
| `GET /cs/cases/scan/appraisal-due` | 30-day appraisal overdue | HIGH |
| `GET /cs/cases/scan/no-contact-30d` | No contact 30+ days | MEDIUM |

```bash
# At-risk cases — address immediately
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/cs/cases/scan/at-risk
```

#### PATCH /cs/cases/{id}/health
Update health status.

```bash
curl -s -X PATCH -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"health_status":"at_risk","notes":"Employer reported maid lateness 3x this week"}' \
  https://maids.ng/api/agent-api/v1/cs/cases/7/health
```

#### POST /cs/cases/{id}/appraisal
Record appraisal.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"appraisal_type":"30-day","employer_score":4,"maid_score":5,"notes":"Both parties satisfied"}' \
  https://maids.ng/api/agent-api/v1/cs/cases/7/appraisal
```

#### POST /cs/cases/{id}/note
Add case note.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"note":"Spoke with employer — resolved schedule conflict"}' \
  https://maids.ng/api/agent-api/v1/cs/cases/7/note
```

### Ticket Management

#### GET /cs/tickets
List tickets with filters.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  "https://maids.ng/api/agent-api/v1/cs/tickets?status=open&priority=high"
```

#### GET /cs/tickets/scan/sla-breached
Tickets past SLA — CRITICAL.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/cs/tickets/scan/sla-breached
```
**Response:**
```json
{
  "data": {
    "count": 1,
    "tickets": [{
      "id": 23, "cs_case_id": 7,
      "ticket_type": "salary_delay",
      "priority": "high",
      "sla_deadline": "2026-05-03T10:00:00Z",
      "hours_overdue": 6.2,
      "recommended_action": "call_employer_immediately"
    }]
  }
}
```

#### GET /cs/tickets/scan/critical-open
Open critical-priority tickets.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/cs/tickets/scan/critical-open
```

#### POST /cs/tickets
Open new ticket.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"cs_case_id":7,"user_id":2,"role":"employer","type":"salary_delay","description":"Maid reports salary not paid","priority":"high","disputed_amount":60000}' \
  https://maids.ng/api/agent-api/v1/cs/tickets
```

#### PATCH /cs/tickets/{id}
Update ticket.

```bash
curl -s -X PATCH -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"status":"in_progress","priority":"critical","notes":"Employer unreachable — escalating"}' \
  https://maids.ng/api/agent-api/v1/cs/tickets/23
```

#### POST /cs/tickets/{id}/resolve
Resolve ticket.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"resolution":"Employer paid outstanding salary","resolved_amount":60000}' \
  https://maids.ng/api/agent-api/v1/cs/tickets/23/resolve
```

#### POST /cs/tickets/{id}/escalate
Escalate to human admin.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"reason":"Cannot resolve — needs legal review"}' \
  https://maids.ng/api/agent-api/v1/cs/tickets/23/escalate
```

### Maid Exit

#### GET /cs/exits/scan/recent
Exits in last 7 days.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/cs/exits/scan/recent
```

#### POST /cs/exits
Record maid exit.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"cs_case_id":7,"exit_type":"resignation","reason":"Personal reasons"}' \
  https://maids.ng/api/agent-api/v1/cs/exits
```

---

## Group 9 — Communications

### POST /messages/send
Send message via any channel.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"user_id":2,"channel":"whatsapp","phone":"+2348012345678","message":"Hello from Maids.ng! We found 3 matches for you."}' \
  https://maids.ng/api/agent-api/v1/messages/send
```

### POST /messages/sms
Send raw SMS.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+2348012345678","message":"Your verification code is 123456"}' \
  https://maids.ng/api/agent-api/v1/messages/sms
```

### POST /messages/call
Initiate VAPI AI voice call.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+2348012345678","call_type":"onboarding_welcome","context":"New employer — discuss matching process"}' \
  https://maids.ng/api/agent-api/v1/messages/call
```
**Response:** `{ "data": { "call_id": "abc-123", "status": "queued" } }`

### POST /messages/ambassador
Route through Ambassador AI pipeline.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"channel":"whatsapp","from_phone":"+2348012345678","from_name":"Ngozi","content":"How much does a full-time housekeeper cost in Lekki?"}' \
  https://maids.ng/api/agent-api/v1/messages/ambassador
```
**Response:** `{ "data": { "reply": "Full-time housekeepers in Lekki typically cost...", "conversation_id": 12 } }`

### Thread & Call History

```bash
# Communication thread by phone
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  "https://maids.ng/api/agent-api/v1/communications/thread/+2348012345678"

# Communication thread by user ID
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/communications/thread/by-user/2

# Log manual comms event
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"entity_type":"user","entity_id":2,"note":"Called employer — no answer","channel":"phone","status":"attempted"}' \
  https://maids.ng/api/agent-api/v1/communications/event

# Call logs
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/calls/logs

# Call detail
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/calls/logs/1

# Update call outcome
curl -s -X PATCH -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"goal_achieved":true,"notes":"Employer agreed to match","follow_up_action":"send_maid_profiles"}' \
  https://maids.ng/api/agent-api/v1/calls/logs/1/outcome
```

---

## Group 10 — Conversations

Agent conversation CRUD — the backbone for n8n WhatsApp integration.

### POST /conversations/message
Log a message. Resolves identity, creates conversation, stores message.

```bash
# Log user message
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"channel":"whatsapp","from_phone":"+2348012345678","from_name":"Ngozi","content":"Hello, I need a housekeeper","role":"user"}' \
  https://maids.ng/api/agent-api/v1/conversations/message

# Log assistant response
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"channel":"whatsapp","from_phone":"+2348012345678","content":"Hello Ngozi! What area are you in?","role":"assistant"}' \
  https://maids.ng/api/agent-api/v1/conversations/message
```
**Response:** `{ "data": { "conversation_id": 12, "identity_id": 5, "user_id": 2, "channel": "whatsapp" } }`

### GET /conversations
List conversations with filters.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  "https://maids.ng/api/agent-api/v1/conversations?channel=whatsapp&status=open"
```

### GET /conversations/{id}
Conversation detail with identity.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/conversations/12
```

### GET /conversations/{id}/messages
Message history for context building.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/conversations/12/messages
```

### GET /agent/identity/lookup
Find identity by channel + phone.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  "https://maids.ng/api/agent-api/v1/agent/identity/lookup?channel=whatsapp&phone=+2348012345678"
```

---

## Group 11 — Agent Notes (Universal Audit Trail)

Every agent action must be logged here. This is how agents hand off context.

### POST /notes
Universal note endpoint.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "fulfillment_case",
    "entity_id": 7,
    "note": "Called Ngozi at 14:00. Confirmed ₦60,000 salary. Maid informed. Waiting for maid verbal confirmation.",
    "action_taken": "call_placed",
    "outcome": "partial_success",
    "next_action": "call_maid_for_salary_confirmation",
    "next_action_due_at": "2026-05-03T18:00:00Z"
  }' \
  https://maids.ng/api/agent-api/v1/notes
```

### GET /notes/{entityType}/{entityId}
All notes for an entity.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/notes/fulfillment_case/7
```

---

## Group 12 — Campaigns

### GET /campaigns
List campaigns.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/campaigns
```

### GET /campaigns/{id}/logs
Outreach logs for a campaign.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/campaigns/1/logs
```

### POST /campaigns/{id}/dispatch
Trigger outreach for a specific identity.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"channel_identity_id":5,"message_content":"Hi! We have maid matches for you."}' \
  https://maids.ng/api/agent-api/v1/campaigns/1/dispatch
```

### POST /campaigns/send-direct
Send direct outreach as part of a campaign.

```bash
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"user_id":2,"channel":"whatsapp","message":"We found 3 maids matching your requirements!","campaign_name":"post-quiz-nudge"}' \
  https://maids.ng/api/agent-api/v1/campaigns/send-direct
```

### GET /campaigns/check-cooldown/{channelIdentityId}
Check cooldown before sending.

```bash
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/campaigns/check-cooldown/5
```
**Response:** `{ "data": { "can_send": true, "sent_last_24h": 2, "sent_last_hour": false } }`

---

## Complete Agent Workflow Examples

### Onboarding Agent — Welcome New Employer
```bash
AGENT_KEY="mng_sk_..."

# 1. Scan for users needing welcome
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/onboarding/scan/needs-welcome-call

# 2. Read user context
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/users/89/summary

# 3. Read conversation history (avoid repeats)
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/users/89/conversation-history

# 4. Initiate call
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+2348012345678","call_type":"onboarding_welcome","context":"New employer in Lekki"}' \
  https://maids.ng/api/agent-api/v1/messages/call

# 5. Log the touchpoint
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"journey_id":14,"user_id":89,"touchpoint_type":"welcome_call","channel":"phone","status":"sent"}' \
  https://maids.ng/api/agent-api/v1/onboarding/touchpoints

# 6. Log agent note
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"entity_type":"user","entity_id":89,"note":"Welcome call placed. Employer interested in full-time housekeeper, Lekki. Budget ₦50-80k.","action_taken":"call_placed","outcome":"success","next_action":"send_quiz_link"}' \
  https://maids.ng/api/agent-api/v1/notes
```

### Fulfillment Agent — Drive Case from Payment to Active
```bash
# 1. Scan for cases needing attention
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/fulfillment/scan/all-active

# 2. Open case after payment confirmed
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"employer_id":2,"maid_id":3,"preference_id":1}' \
  https://maids.ng/api/agent-api/v1/fulfillment/open

# 3. Record salary agreement
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"party":"employer","salary":60000}' \
  https://maids.ng/api/agent-api/v1/fulfillment/7/record-salary

# 4. Advance stage
curl -s -X PATCH -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"stage":"salary_agreed","notes":"Both parties confirmed"}' \
  https://maids.ng/api/agent-api/v1/fulfillment/7/stage

# 5. Set start date
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"start_date":"2026-05-10","start_time":"08:00","employer_address":"12 Admiralty Way, Lekki"}' \
  https://maids.ng/api/agent-api/v1/fulfillment/7/set-start-date

# 6. Confirm day-one arrival
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"arrived":true,"notes":"Maid arrived, employer present"}' \
  https://maids.ng/api/agent-api/v1/fulfillment/7/confirm-arrival

# 7. Activate (creates CS case automatically)
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -d '{}' https://maids.ng/api/agent-api/v1/fulfillment/7/activate
```

### CS Agent — Handle Complaint
```bash
# 1. Scan for breached SLAs (CRITICAL)
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/cs/tickets/scan/sla-breached

# 2. Read ticket detail
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/cs/tickets/23

# 3. Read case history
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/cs/cases/7

# 4. Read all agent notes on case
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/notes/cs_case/7

# 5. Call employer
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+2348012345678","call_type":"complaint_followup","context":"Salary delay — second month"}' \
  https://maids.ng/api/agent-api/v1/messages/call

# 6. Update ticket with findings
curl -s -X PATCH -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"status":"in_progress","notes":"Employer claims bank issue. Promises payment by Friday."}' \
  https://maids.ng/api/agent-api/v1/cs/tickets/23

# 7. Log note for next agent session
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"entity_type":"cs_case","entity_id":7,"note":"Called employer — bank delay confirmed. Follow up Friday.","action_taken":"call_placed","outcome":"partial","next_action":"verify_payment_friday"}' \
  https://maids.ng/api/agent-api/v1/notes
```

### n8n WhatsApp Integration
```bash
# n8n receives WhatsApp message, forwards to maids.ng
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"channel":"whatsapp","from_phone":"+2348012345678","from_name":"Ngozi","content":"I need a housekeeper in Lekki"}' \
  https://maids.ng/api/agent-api/v1/conversations/message

# n8n queries maids.ng for maid matches
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  "https://maids.ng/api/agent-api/v1/users/lookup" \
  -d '{"phone":"+2348012345678"}'

# n8n gets conversation history for AI context
curl -s -H "Authorization: Bearer $AGENT_KEY" \
  https://maids.ng/api/agent-api/v1/conversations/12/messages

# n8n logs AI reply back
curl -s -X POST -H "Authorization: Bearer $AGENT_KEY" \
  -H "Content-Type: application/json" \
  -d '{"channel":"whatsapp","from_phone":"+2348012345678","content":"We have 7 housekeepers in Lekki. Budget range ₦50-80k. Would you like to see profiles?","role":"assistant"}' \
  https://maids.ng/api/agent-api/v1/conversations/message
```

---

## Error Codes

| HTTP | Code | Meaning |
|------|------|---------|
| 400 | `BAD_REQUEST` | Invalid input |
| 401 | `UNAUTHORIZED` | Missing or invalid API key |
| 403 | `FORBIDDEN` | Expired key or insufficient scope |
| 404 | `NOT_FOUND` | Resource not found |
| 422 | `VALIDATION_ERROR` | Field validation failed |
| 429 | `RATE_LIMITED` | Too many requests |
| 500 | `INTERNAL_ERROR` | Server error |

## Rate Limits
- Default: 60 requests/minute per API key
- Scan endpoints: Cache for 5 minutes (repeated scans within window return cached results)
- Bulk operations: Batch up to 50 records per request

---

*Documentation version: 1.0.0 — Generated 2026-06-08*
