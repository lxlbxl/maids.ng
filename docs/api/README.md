# Maids.ng REST API Documentation

> **API Version:** 1.0.0 | **Base URL:** `https://maids.ng/api/v1`

## Overview

The Maids.ng REST API provides full access to the domestic help marketplace platform. Built for AI agents and third-party integrations with standardized JSON responses and token-based authentication.

## Base URL

```
https://maids.ng/api/v1
```

## Authentication

All protected endpoints require a Bearer token obtained via `/auth/login`:

```bash
curl -X POST https://maids.ng/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"user@example.com","password":"password","device_name":"My App"}'
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": { "id": 1, "name": "John Doe", "email": "user@example.com", "role": "employer" },
    "token": "1|abc123...",
    "token_type": "Bearer"
  }
}
```

## Response Format

All endpoints return JSON with standardized envelope:

```json
{
  "success": true,
  "message": "Operation description",
  "data": { ... },
  "meta": {
    "timestamp": "2026-05-11T10:00:00+00:00",
    "request_id": "req_abc123",
    "api_version": "v1",
    "pagination": { ... }
  }
}
```

**Success:** `success: true`, data in `data` field
**Error:** `success: false`, error details in `code`, `message`, and `errors` fields

```json
{
  "success": false,
  "message": "Validation failed",
  "code": "VALIDATION_ERROR",
  "errors": { "email": ["The email field is required."] }
}
```

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 204 | No Content |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Rate Limited |
| 500 | Server Error |

---

## Public Endpoints

No authentication required.

### Health Check

```
GET /health
```

```bash
curl https://maids.ng/api/v1/health
```

**Response:**
```json
{
  "status": "healthy",
  "service": "Maids.ng API",
  "version": "1.0.0",
  "timestamp": "2026-05-11T10:00:00+00:00"
}
```

---

## Authentication Endpoints

### Register

```
POST /auth/register
```

```bash
curl -X POST https://maids.ng/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"John Doe","email":"john@example.com","phone":"+2348012345678","password":"password123","password_confirmation":"password123","role":"employer"}'
```

### Login

```
POST /auth/login
```

```bash
curl -X POST https://maids.ng/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"user@example.com","password":"password"}'
```

### Get Current User

```
GET /auth/me
Authorization: Bearer {token}
```

```bash
curl https://maids.ng/api/v1/auth/me \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Logout

```
POST /auth/logout
Authorization: Bearer {token}
```

### Update Profile

```
PUT /auth/profile
Authorization: Bearer {token}
```

### Change Password

```
PUT /auth/password
Authorization: Bearer {token}
```

---

## Public Maid Discovery

### List Available Maids

```
GET /maids
```

Query parameters for filtering:
- `location` - City or area name (partial match)
- `state` - State name (exact match)
- `lga` - Local Government Area
- `help_types[]` - Array: `live-in`, `nanny`, `cooking`, `elderly-care`, `driver`, `cleaning`, `laundry`
- `min_experience` - Minimum years of experience
- `max_salary` - Maximum expected salary
- `schedule_preference` - `live-in`, `part-time`, `full-time`, `weekends-only`, `flexible`
- `verified_only` - Boolean, show only fully verified maids
- `per_page` - Results per page (1-100, default 15)

```bash
curl "https://maids.ng/api/v1/maids?location=Lagos&verified_only=true&per_page=10" \
  -H "Accept: application/json"
```

**Response:**
```json
{
  "success": true,
  "message": "Available maids retrieved successfully",
  "data": [
    {
      "id": 1,
      "user_id": 5,
      "bio": "Experienced housekeeper...",
      "role": "Housekeeper",
      "skills": ["cooking", "cleaning", "laundry"],
      "languages": ["English", "Yoruba"],
      "schedule_preference": "live-in",
      "availability_status": "available",
      "location": "Lagos",
      "state": "Lagos",
      "expected_salary": 150000,
      "experience_years": 5,
      "rating": 4.8,
      "total_reviews": 23,
      "verification": {
        "nin_verified": true,
        "background_verified": true,
        "fully_verified": true
      },
      "user": {
        "id": 5,
        "name": "Comfort Johnson",
        "avatar": "https://...",
        "location": "Lagos"
      }
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 15,
      "total": 42
    }
  }
}
```

### Get Maid Profile

```
GET /maids/{id}
```

```bash
curl https://maids.ng/api/v1/maids/5 \
  -H "Accept: application/json"
```

---

## Reference Data

### Get Skills

```
GET /reference/skills
```

### Get Help Types

```
GET /reference/help-types
```

### Get Payment Methods

```
GET /reference/payment-methods
```

---

## Public Matching

### Find Matches

```
POST /matching/find
```

```bash
curl -X POST https://maids.ng/api/v1/matching/find \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "help_types": ["nanny", "cleaning"],
    "schedule": "full-time",
    "urgency": "immediate",
    "location": "Lagos, NG",
    "budget_max": 50000
  }'
```

---

## Maid Endpoints (Requires: maid role)

### Profile

```
GET /maid/profile
POST /maid/profile
PUT /maid/availability
```

### Assignments

```
GET /maid/assignments
GET /maid/assignments/{id}
```

### Earnings & Payments

```
GET /maid/earnings
GET /maid/payments
GET /maid/upcoming-payments
```

### Dashboard

```
GET /maid/dashboard
```

---

## Employer Endpoints (Requires: employer role)

### Profile

```
GET /employer/profile
PUT /employer/profile
```

### Preferences

```
GET /employer/preferences
POST /employer/preferences
GET /employer/preferences/{id}
PUT /employer/preferences/{id}
POST /employer/preferences/{id}/cancel
```

### Assignments

```
GET /employer/assignments
GET /employer/assignments/{id}
```

### Spending & Payments

```
GET /employer/spending
GET /employer/payments
GET /employer/upcoming-payments
```

### Reviews

```
POST /employer/reviews
GET /employer/my-reviews
```

### Dashboard

```
GET /employer/dashboard
```

---

## Booking Endpoints (Authenticated)

```
GET /bookings
POST /bookings
GET /bookings/{id}
POST /bookings/{id}/start
POST /bookings/{id}/complete
POST /bookings/{id}/cancel
GET /bookings/statistics
```

---

## Payment Endpoints (Authenticated)

```
GET /payments
POST /payments/initialize
GET /payments/verify/{reference}
GET /payments/statistics
POST /payments/{id}/retry
GET /payments/{id}
```

### Payment Webhook

```
POST /payments/webhook
```

Public endpoint protected by Paystack signature verification.

---

## Assignment Endpoints (Authenticated)

```
GET /assignments
POST /assignments
GET /assignments/{id}
POST /assignments/{id}/accept
POST /assignments/{id}/reject
POST /assignments/{id}/complete
POST /assignments/{id}/cancel
GET /assignments/statistics
```

---

## Wallet Endpoints (Authenticated)

```
GET /wallets
GET /wallets/transactions
POST /wallets/deposit
POST /wallets/withdraw
GET /wallets/withdrawals/pending
```

---

## Salary Endpoints (Authenticated)

```
GET /salary/schedules
GET /salary/schedules/{id}
POST /salary/schedules/{id}/pay
GET /salary/payments
GET /salary/overdue
GET /salary/statistics
```

---

## Matching Endpoints (Authenticated)

```
POST /matching/request
GET /matching/status/{jobId}
GET /matching/results/{jobId}
POST /matching/manual-assign
GET /matching/queue
```

---

## Notification Endpoints (Authenticated)

```
GET /notifications
GET /notifications/unread-count
POST /notifications/{id}/read
POST /notifications/mark-all-read
DELETE /notifications/{id}
```

---

## Admin Endpoints (Requires: admin role)

### Dashboard & Health

```
GET /admin/dashboard
GET /admin/system-health
```

### Users

```
GET /admin/users
GET /admin/users/{id}
PUT /admin/users/{id}/status
POST /admin/users/{id}/verify-maid
```

### Assignments

```
GET /admin/assignments
GET /admin/assignments/{id}
POST /admin/assignments/{id}/cancel
```

### Withdrawals

```
GET /admin/withdrawals
GET /admin/withdrawals/pending
POST /admin/withdrawals/{id}/approve
POST /admin/withdrawals/{id}/reject
```

### Settings

```
GET /admin/settings
PUT /admin/settings
```

### AI Configuration

```
GET /admin/ai/config
PUT /admin/ai/config
POST /admin/ai/test-connection
```

### Reports

```
GET /admin/reports/platform-overview
GET /admin/reports/financial
GET /admin/reports/user-activity
GET /admin/reports/assignment-analytics
GET /admin/reports/ai-matching
GET /admin/reports/notifications
GET /admin/reports/agent-logs
POST /admin/reports/export
```

### Matching Queue

```
GET /admin/matching/queue
GET /admin/matching/statistics
GET /admin/ai-matching/monitor
GET /admin/ai-matching/jobs/{jobId}
POST /admin/ai-matching/jobs/{jobId}/retry
POST /admin/ai-matching/jobs/{jobId}/cancel
```

### Salary Management

```
GET /admin/salary/schedules
GET /admin/salary/overdue
POST /admin/salary/{id}/escalate
POST /admin/salary/{id}/remind
POST /admin/salary/batch-pay
POST /admin/salary/{id}/mark-paid
GET /admin/salary/payments
GET /admin/salary/statistics
```

### Wallet Oversight

```
GET /admin/wallets/overview
POST /admin/wallets/{walletId}/adjust
```

---

## Legacy Routes (Backward Compatibility)

These routes exist for backward compatibility with older integrations:

```
GET  /maid-legacy/profile
PUT  /maid-legacy/profile
PUT  /maid-legacy/bank-details
GET  /maid-legacy/bookings
POST /maid-legacy/bookings/{id}/confirm

GET  /employer-legacy/preferences
POST /employer-legacy/preferences
PUT  /employer-legacy/preferences/{id}
DELETE /employer-legacy/preferences/{id}
GET  /employer-legacy/bookings
GET  /employer-legacy/reviews
POST /employer-legacy/reviews
GET  /employer-legacy/dashboard
```

---

## Verification Endpoints (Public)

```
POST /verification/nin
GET  /verification/nin/{reference}
POST /verification/nin/batch
```

---

## Agent Channel Webhooks (Public)

```
POST /agent/webhook/email
POST /agent/webhook/whatsapp
POST /agent/webhook/instagram
POST /agent/webhook/facebook
GET  /agent/webhook/facebook/verify
```

---

## User Events (Public)

```
POST /user-events
```

---

## Filter Examples

### Find Verified Maids in Lagos

```bash
curl "https://maids.ng/api/v1/maids?location=Lagos&verified_only=true" \
  -H "Accept: application/json"
```

### Find Nannies with 3+ Years Experience

```bash
curl "https://maids.ng/api/v1/maids?help_types[]=nanny&min_experience=3" \
  -H "Accept: application/json"
```

### Find Full-Time Maids Under 100k

```bash
curl "https://maids.ng/api/v1/maids?schedule_preference=full-time&max_salary=100000" \
  -H "Accept: application/json"
```

---

## Error Response Format

```json
{
  "success": false,
  "message": "Human-readable error message",
  "code": "MACHINE_READABLE_CODE",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

### Error Codes

| Code | Meaning |
|------|---------|
| `VALIDATION_ERROR` | Request validation failed |
| `NOT_FOUND` | Resource not found |
| `UNAUTHORIZED` | Authentication required |
| `FORBIDDEN` | Insufficient permissions |
| `INTERNAL_ERROR` | Server error |
| `INVALID_CREDENTIALS` | Wrong email/password |

---

## Pagination Format

List endpoints return paginated results:

```json
{
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 15,
      "total": 72,
      "from": 1,
      "to": 15,
      "has_more": true
    },
    "links": {
      "first": "https://maids.ng/api/v1/maids?page=1",
      "last": "https://maids.ng/api/v1/maids?page=5",
      "prev": null,
      "next": "https://maids.ng/api/v1/maids?page=2"
    }
  }
}
```

---

## Support

- **API Support:** api-support@maids.ng
- **Documentation:** https://docs.maids.ng
- **Status Page:** https://status.maids.ng
