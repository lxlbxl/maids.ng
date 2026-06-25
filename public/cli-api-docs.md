# Maids.ng CLI Agent API Reference

> **Base URL:** `https://maids.ng/api/v1/cli`  
> **Auth:** `Authorization: Bearer {mcp_token}`  
> **Content-Type:** `application/json`  
> **Updated:** 2026-06-23

---

## Authentication

All endpoints require the MCP auth token. Pass it as a Bearer token:

```bash
curl -H "Authorization: Bearer YOUR_MCP_TOKEN" https://maids.ng/api/v1/cli/health
```

Invalid/missing tokens return `401 Unauthorized`.

---

## System

### GET /health
Health check with database status.

**Response:**
```json
{
  "healthy": true,
  "database": "connected",
  "cache": "database",
  "timestamp": "2026-06-23T09:00:00+00:00"
}
```

### GET /status
Platform statistics overview.

**Response:**
```json
{
  "total_maids": 36,
  "total_employers": 30,
  "active_bookings": 5,
  "pending_verifications": 12,
  "pending_matches": 3
}
```

---

## User Management

### GET /users
List all users with optional filters.

| Param | Type | Description |
|---|---|---|
| `role` | string | `maid`, `employer`, or `admin` |
| `status` | string | `active`, `suspended`, `inactive` |
| `search` | string | Search by name, email, or phone |
| `page` | int | Page number (default 1) |
| `limit` | int | Items per page (default 20) |

```bash
curl "https://maids.ng/api/v1/cli/users?role=maid&status=active" \
  -H "Authorization: Bearer $TOKEN"
```

### GET /users/{id}
Get a single user with full profile data.

```bash
curl "https://maids.ng/api/v1/cli/users/63" \
  -H "Authorization: Bearer $TOKEN"
```

### GET /users/lookup
Find a user by phone number. Returns maid or employer profile.

| Param | Type | Description |
|---|---|---|
| `phone` | string | **Required.** Phone number (e.g. `08030835736`) |

```bash
curl "https://maids.ng/api/v1/cli/users/lookup?phone=08030835736" \
  -H "Authorization: Bearer $TOKEN"
```

**Response:**
```json
{
  "data": {
    "id": 63,
    "name": "Lorelly Uba",
    "phone": "08030835736",
    "email": "08030835736@maids.ng",
    "role": "maid",
    "status": "active",
    "location": "Igando, Lagos",
    "nin_verified": true,
    "verification_status": "verified",
    "maid_profile": {
      "skills": ["cleaning", "cooking"],
      "help_types": ["cleaning", "nanny"],
      "expected_salary": 50000,
      "availability_status": "available",
      "rating": 4.5
    },
    "employer_preferences": null
  }
}
```

### PUT /users/{id}/status
Suspend or activate a user.

```bash
curl -X PUT "https://maids.ng/api/v1/cli/users/63/status" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "suspended"}'
```

| Body Field | Type | Description |
|---|---|---|
| `status` | string | `active`, `inactive`, `suspended`, or `banned` |

---

## Maid Management

### GET /maids
List all maids with filters.

| Param | Type | Description |
|---|---|---|
| `status` | string | `active`, `inactive` |
| `search` | string | Search by name or location |
| `verified` | string | `yes` or `no` |
| `page` | int | Page number |
| `limit` | int | Items per page (default 20) |

```bash
curl "https://maids.ng/api/v1/cli/maids?status=active&verified=yes" \
  -H "Authorization: Bearer $TOKEN"
```

### GET /maids/{maid_id}
Full maid profile including verification, assignments, and reviews.

```bash
curl "https://maids.ng/api/v1/cli/maids/13" \
  -H "Authorization: Bearer $TOKEN"
```

### PATCH /maids/{maid_id}/availability
Toggle a maid's availability status.

```bash
curl -X PATCH "https://maids.ng/api/v1/cli/maids/13/availability" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"is_available": true}'
```

### GET /maids/{maid_id}/earnings
Get a maid's earnings history.

```bash
curl "https://maids.ng/api/v1/cli/maids/13/earnings" \
  -H "Authorization: Bearer $TOKEN"
```

---

## Employer Management

### GET /employers/{employer_id}/preferences
Get an employer's matching preferences and requirements.

```bash
curl "https://maids.ng/api/v1/cli/employers/51/preferences" \
  -H "Authorization: Bearer $TOKEN"
```

**Response:**
```json
{
  "data": {
    "id": 33,
    "help_types": ["cleaning", "nanny"],
    "location": "Ikorodu, Lagos",
    "budget_min": 70000,
    "budget_max": 90000,
    "schedule": "full-time",
    "urgency": "immediate",
    "matching_status": "matched",
    "selected_maid_id": 24
  }
}
```

### PATCH /employers/{employer_id}/preferences
Update employer preferences.

```bash
curl -X PATCH "https://maids.ng/api/v1/cli/employers/51/preferences" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"budget_max": 120000, "schedule": "full-time"}'
```

---

## Bookings

### GET /bookings
List all bookings with filters.

| Param | Type | Description |
|---|---|---|
| `status` | string | `pending`, `active`, `completed`, `cancelled` |
| `employer_id` | int | Filter by employer |
| `maid_id` | int | Filter by maid |
| `page` | int | Page number |

```bash
curl "https://maids.ng/api/v1/cli/bookings?status=active" \
  -H "Authorization: Bearer $TOKEN"
```

### GET /bookings/user
Get bookings for a specific user.

| Param | Type | Description |
|---|---|---|
| `user_id` | int | **Required.** User ID |

```bash
curl "https://maids.ng/api/v1/cli/bookings/user?user_id=51" \
  -H "Authorization: Bearer $TOKEN"
```

### POST /bookings/create
Create a new booking between an employer and a maid.

```bash
curl -X POST "https://maids.ng/api/v1/cli/bookings/create" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "employer_id": 51,
    "maid_id": 13,
    "start_date": "2026-07-01",
    "schedule_type": "full-time",
    "agreed_salary": 80000,
    "location": "Ikorodu, Lagos"
  }'
```

### POST /bookings/{booking_id}/cancel
Cancel a booking.

```bash
curl -X POST "https://maids.ng/api/v1/cli/bookings/5/cancel" \
  -H "Authorization: Bearer $TOKEN"
```

---

## Assignments

### GET /assignments
List all assignments (guarantee matches, direct selections).

| Param | Type | Description |
|---|---|---|
| `status` | string | `pending_acceptance`, `accepted`, `active`, `completed`, `cancelled`, `rejected` |
| `type` | string | `guarantee_match`, `direct_selection`, `replacement_search` |
| `page` | int | Page number |

```bash
curl "https://maids.ng/api/v1/cli/assignments?status=pending_acceptance" \
  -H "Authorization: Bearer $TOKEN"
```

### GET /assignments/{id}
Get a single assignment with full details.

```bash
curl "https://maids.ng/api/v1/cli/assignments/12" \
  -H "Authorization: Bearer $TOKEN"
```

### GET /assignments/statistics
Get aggregate assignment statistics.

```bash
curl "https://maids.ng/api/v1/cli/assignments/statistics" \
  -H "Authorization: Bearer $TOKEN"
```

### POST /assignments/{id}/accept
Employer accepts a pending assignment.

```bash
curl -X POST "https://maids.ng/api/v1/cli/assignments/12/accept" \
  -H "Authorization: Bearer $TOKEN"
```

### POST /assignments/{id}/reject
Employer rejects a pending assignment.

```bash
curl -X POST "https://maids.ng/api/v1/cli/assignments/12/reject" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Budget too high"}'
```

### POST /assignments/{id}/complete
Mark an active assignment as completed.

```bash
curl -X POST "https://maids.ng/api/v1/cli/assignments/12/complete" \
  -H "Authorization: Bearer $TOKEN"
```

---

## Matching Queue

### POST /matching/request
Submit a matching request for an employer.

```bash
curl -X POST "https://maids.ng/api/v1/cli/matching/request" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "employer_id": 51,
    "preference_id": 33,
    "priority": 5
  }'
```

### GET /matching/status/{jobId}
Check the status of a matching job.

```bash
curl "https://maids.ng/api/v1/cli/matching/status/abc123-job-id" \
  -H "Authorization: Bearer $TOKEN"
```

**Response:**
```json
{
  "data": {
    "job_id": "abc123-job-id",
    "status": "completed",
    "job_type": "guarantee_match",
    "started_at": "2026-06-23T09:00:00Z",
    "completed_at": "2026-06-23T09:05:00Z",
    "duration": "5m",
    "match_candidates": [{"maid_id": 13, "score": 0.85}, {"maid_id": 3, "score": 0.72}]
  }
}
```

### GET /matching/results/{jobId}
Get the results of a completed matching job.

```bash
curl "https://maids.ng/api/v1/cli/matching/results/abc123-job-id" \
  -H "Authorization: Bearer $TOKEN"
```

### GET /matching/queue
View the matching queue.

| Param | Type | Description |
|---|---|---|
| `status` | string | `pending`, `processing`, `completed`, `failed` |
| `type` | string | `auto_match`, `guarantee_match`, `direct_selection`, `replacement_search` |

```bash
curl "https://maids.ng/api/v1/cli/matching/queue?status=pending" \
  -H "Authorization: Bearer $TOKEN"
```

### POST /matching/manual-assign
Manually assign a maid to an employer.

```bash
curl -X POST "https://maids.ng/api/v1/cli/matching/manual-assign" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"employer_id": 51, "maid_id": 13, "preference_id": 33}'
```

---

## Wallet & Payments

### GET /wallet
Get wallet balance for a user.

| Param | Type | Description |
|---|---|---|
| `user_id` | int | **Required.** User ID |

```bash
curl "https://maids.ng/api/v1/cli/wallet?user_id=51" \
  -H "Authorization: Bearer $TOKEN"
```

### GET /wallet/transactions
Get wallet transaction history.

| Param | Type | Description |
|---|---|---|
| `user_id` | int | **Required.** User ID |
| `page` | int | Page number |

```bash
curl "https://maids.ng/api/v1/cli/wallet/transactions?user_id=51" \
  -H "Authorization: Bearer $TOKEN"
```

---

## Notifications

### GET /notifications
List notifications for a user.

| Param | Type | Description |
|---|---|---|
| `user_id` | int | User ID |
| `page` | int | Page number |

```bash
curl "https://maids.ng/api/v1/cli/notifications?user_id=51" \
  -H "Authorization: Bearer $TOKEN"
```

### GET /notifications/unread-count
Get unread notification count.

| Param | Type | Description |
|---|---|---|
| `user_id` | int | User ID |

```bash
curl "https://maids.ng/api/v1/cli/notifications/unread-count?user_id=51" \
  -H "Authorization: Bearer $TOKEN"
```

### POST /notifications/{id}/read
Mark a single notification as read.

```bash
curl -X POST "https://maids.ng/api/v1/cli/notifications/5/read" \
  -H "Authorization: Bearer $TOKEN"
```

### POST /notifications/mark-all-read
Mark all notifications as read for a user.

```bash
curl -X POST "https://maids.ng/api/v1/cli/notifications/mark-all-read" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 51}'
```

### DELETE /notifications/{id}
Delete a notification.

```bash
curl -X DELETE "https://maids.ng/api/v1/cli/notifications/5" \
  -H "Authorization: Bearer $TOKEN"
```

---

## Reference Data

### GET /reference/skills
List all available skill categories.

```bash
curl "https://maids.ng/api/v1/cli/reference/skills" \
  -H "Authorization: Bearer $TOKEN"
```

### GET /reference/help-types
List all available help type categories.

```bash
curl "https://maids.ng/api/v1/cli/reference/help-types" \
  -H "Authorization: Bearer $TOKEN"
```

---

## Common Workflows

### Look up a caller by phone and check their status
```bash
TOKEN="your-mcp-token"
PHONE="08030835736"

# Find the user
USER=$(curl -s "https://maids.ng/api/v1/cli/users/lookup?phone=$PHONE" \
  -H "Authorization: Bearer $TOKEN")

# Extract key info
echo $USER | jq '{name: .data.name, role: .data.role, verified: .data.nin_verified, status: .data.verification_status}'
```

### Find available maids for an employer
```bash
# Get employer's requirements
REQS=$(curl -s "https://maids.ng/api/v1/cli/employers/51/preferences" \
  -H "Authorization: Bearer $TOKEN")

# Search for matching maids
HELP_TYPES=$(echo $REQS | jq -r '.data.help_types | join(",")')
LOCATION=$(echo $REQS | jq -r '.data.location')
BUDGET=$(echo $REQS | jq -r '.data.budget_max')

curl "https://maids.ng/api/v1/cli/maids?status=active&verified=yes" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Submit and track a matching request
```bash
# Submit match
JOB=$(curl -s -X POST "https://maids.ng/api/v1/cli/matching/request" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"employer_id": 51, "preference_id": 33}')

JOB_ID=$(echo $JOB | jq -r '.data.job_id')

# Check status
curl -s "https://maids.ng/api/v1/cli/matching/status/$JOB_ID" \
  -H "Authorization: Bearer $TOKEN" | jq

# Get results when complete
curl -s "https://maids.ng/api/v1/cli/matching/results/$JOB_ID" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Full maid verification check
```bash
MAID_ID=63

# Get full maid profile
curl -s "https://maids.ng/api/v1/cli/maids/$MAID_ID" \
  -H "Authorization: Bearer $TOKEN" | jq '{
    name: .data.user.name,
    nin: .data.nin,
    verified: .data.nin_verified,
    skills: .data.skills,
    rate: .data.expected_salary,
    available: .data.availability_status,
    rating: .data.rating
  }'

# Check earnings
curl -s "https://maids.ng/api/v1/cli/maids/$MAID_ID/earnings" \
  -H "Authorization: Bearer $TOKEN" | jq
```

---

## Error Responses

| Status | Meaning |
|---|---|
| 200 | Success |
| 401 | Invalid or missing MCP token |
| 404 | Resource not found |
| 422 | Validation error (check `message` field) |
| 500 | Server error |

Error body format:
```json
{
  "message": "Unauthorized. Invalid MCP token."
}
```
