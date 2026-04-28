# Maids.ng API - Agentic AI Integration Guide

## Overview

This guide is specifically designed for AI agents and automated systems integrating with the Maids.ng API. The API follows RESTful principles with standardized responses optimized for machine consumption.

## Quick Start for AI Agents

### Base URL
```
Production:  https://api.maids.ng/v1
Staging:     https://staging-api.maids.ng/v1
Local:       http://localhost:8000/api/v1
```

### Authentication Flow

1. **Register** (if needed):
   ```http
   POST /api/v1/auth/register
   Content-Type: application/json

   {
     "name": "Agent User",
     "email": "agent@example.com",
     "phone": "+2348012345678",
     "password": "securepassword123",
     "password_confirmation": "securepassword123",
     "role": "employer"
   }
   ```

2. **Login** to obtain token:
   ```http
   POST /api/v1/auth/login
   Content-Type: application/json

   {
     "email": "agent@example.com",
     "password": "securepassword123",
     "device_name": "AI Agent Instance"
   }
   ```

3. **Use token** in subsequent requests:
   ```http
   Authorization: Bearer {token}
   ```

### Standard Response Format

All API responses follow this exact structure:

```json
{
  "success": true|false,
  "message": "Human-readable description",
  "data": { ... },           // Response payload
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "request_id": "uuid-v4-string",
    "api_version": "1.0.0",
    "pagination": {            // Only for paginated responses
      "current_page": 1,
      "last_page": 10,
      "per_page": 15,
      "total": 150,
      "from": 1,
      "to": 15
    }
  }
}
```

## AI-Optimized Features

### 1. Structured Error Responses

Errors include machine-readable codes:

```json
{
  "success": false,
  "message": "Validation failed",
  "data": null,
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "request_id": "uuid-v4-string",
    "api_version": "1.0.0"
  },
  "error": {
    "code": "VALIDATION_ERROR",
    "errors": {
      "field_name": ["Error message"]
    }
  }
}
```

Common error codes:
- `INVALID_CREDENTIALS` - Authentication failed
- `VALIDATION_ERROR` - Input validation failed
- `NOT_FOUND` - Resource not found
- `FORBIDDEN` - Insufficient permissions
- `BOOKING_CONFLICT` - Scheduling conflict
- `PAYMENT_EXISTS` - Payment already processed
- `MAID_UNAVAILABLE` - Maid not available

### 2. Request ID Tracking

Every response includes a `request_id` in the meta object. Use this for:
- Logging and debugging
- Idempotency tracking
- Support requests

### 3. Consistent HTTP Status Codes

- `200` - Success
- `201` - Created
- `204` - No Content (successful deletion)
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `409` - Conflict
- `422` - Validation Error
- `429` - Rate Limited
- `500` - Server Error

## Core Operations for AI Agents

### Finding Maids (Matching)

**Search with filters:**
```http
GET /api/v1/maids?skills=cooking&location=Lagos&min_rating=4.0&verified_only=true
```

**AI Matching (Recommended):**
```http
POST /api/v1/matching/find
Content-Type: application/json

{
  "help_type": "live-in",
  "location": "Lekki, Lagos",
  "schedule_type": "full-time",
  "salary_budget": 50000,
  "required_skills": ["cooking", "cleaning"],
  "num_children": 2,
  "children_ages": [3, 5]
}
```

Response includes `match_score` and `match_confidence` for each maid.

### Booking Lifecycle

**Create booking:**
```http
POST /api/v1/bookings
Content-Type: application/json

{
  "preference_id": 1,
  "maid_id": 5,
  "start_date": "2024-02-01",
  "schedule_type": "full-time",
  "agreed_salary": 50000,
  "notes": "Start at 8 AM daily"
}
```

**Status transitions:**
1. `pending` → `confirmed` (maid confirms)
2. `confirmed` → `active` (booking starts)
3. `active` → `completed` (booking ends)
4. Any status → `cancelled` (with reason)

### Payment Processing

**Initialize payment:**
```http
POST /api/v1/payments/initialize
Content-Type: application/json

{
  "booking_id": 123,
  "amount": 50000,
  "payment_method": "card",
  "payment_type": "booking_fee"
}
```

**Verify payment:**
```http
GET /api/v1/payments/verify/{reference}
```

## Role-Based Access Patterns

### As an Employer Agent

```
GET  /api/v1/employer/preferences          # List preferences
POST /api/v1/employer/preferences          # Create preference
GET  /api/v1/employer/bookings             # List bookings
POST /api/v1/employer/reviews              # Create review
GET  /api/v1/employer/dashboard            # Get stats
```

### As a Maid Agent

```
GET  /api/v1/maid/profile                  # Get profile
PUT  /api/v1/maid/profile                  # Update profile
GET  /api/v1/maid/bookings                 # List bookings
POST /api/v1/maid/bookings/{id}/confirm    # Confirm booking
```

### As an Admin Agent

```
GET  /api/v1/admin/dashboard               # System stats
GET  /api/v1/admin/users                   # List users
PUT  /api/v1/admin/users/{id}/status       # Update status
GET  /api/v1/admin/maids                   # List maids
PUT  /api/v1/admin/maids/{id}/verify       # Verify maid
GET  /api/v1/admin/revenue-report          # Revenue data
```

## Data Models for AI Processing

### Maid Profile Structure

```json
{
  "id": 1,
  "user_id": 5,
  "bio": "Experienced housekeeper...",
  "role": "Live-in Helper",
  "skills": ["cooking", "cleaning", "laundry"],
  "schedule_preference": "full-time",
  "availability_status": "available",
  "location": "Lekki, Lagos",
  "state": "Lagos State",
  "expected_salary": 50000,
  "salary_currency": "NGN",
  "experience_years": 5,
  "rating": 4.5,
  "total_reviews": 12,
  "verification": {
    "nin_verified": true,
    "background_verified": true,
    "fully_verified": true
  },
  "match_score": 85,           // AI matching score
  "match_confidence": 0.92,    // Confidence level
  "user": {
    "id": 5,
    "name": "Jane Doe",
    "avatar": "https://..."
  }
}
```

### Booking Structure

```json
{
  "id": 123,
  "employer_id": 10,
  "maid_id": 5,
  "status": "active",
  "payment_status": "paid",
  "start_date": "2024-02-01",
  "end_date": "2024-12-31",
  "schedule_type": "full-time",
  "agreed_salary": 50000,
  "available_actions": ["complete", "cancel"],
  "maid": { ... },
  "employer": { ... },
  "review": null,
  "disputes": []
}
```

## Best Practices for AI Integration

### 1. Handle Pagination

Always check for pagination in list responses:

```python
# Python example
def fetch_all_pages(endpoint):
    results = []
    page = 1
    while True:
        response = requests.get(f"{endpoint}?page={page}")
        data = response.json()
        results.extend(data['data'])
        
        if page >= data['meta']['pagination']['last_page']:
            break
        page += 1
    return results
```

### 2. Implement Retry Logic

```python
import time
from requests.adapters import HTTPAdapter
from requests.packages.urllib3.util.retry import Retry

retry_strategy = Retry(
    total=3,
    backoff_factor=1,
    status_forcelist=[429, 500, 502, 503, 504],
)
adapter = HTTPAdapter(max_retries=retry_strategy)
http = requests.Session()
http.mount("https://", adapter)
```

### 3. Cache Reference Data

Reference endpoints (skills, help types, payment methods) rarely change:

```
GET /api/v1/reference/skills          # Cache: 1 hour
GET /api/v1/reference/help-types      # Cache: 1 hour
GET /api/v1/reference/payment-methods # Cache: 1 hour
```

### 4. Use Webhooks for Payments

Configure webhook endpoint to receive payment updates:

```http
POST /api/v1/payments/webhook
Content-Type: application/json

{
  "reference": "PAY-ABC123",
  "status": "success",
  "amount": 50000,
  "transaction_id": "TXN-XYZ789"
}
```

### 5. Validate Before Actions

Always check available actions before attempting state changes:

```python
booking = get_booking(booking_id)
if 'complete' in booking['data']['available_actions']:
    complete_booking(booking_id)
else:
    log_error(f"Cannot complete booking {booking_id}")
```

## Rate Limiting

- **Public endpoints**: 60 requests/minute
- **Authenticated**: 1000 requests/minute
- **Webhook endpoints**: No limit (validate signature)

Headers include rate limit info:
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
```

## Error Recovery Patterns

### Validation Errors

```python
try:
    response = create_booking(data)
except ValidationError as e:
    # Fix validation errors and retry
    fixed_data = fix_validation_errors(data, e.errors)
    response = create_booking(fixed_data)
```

### Conflict Resolution

```python
try:
    response = create_booking(data)
except ConflictError as e:
    if e.code == "BOOKING_CONFLICT":
        # Find alternative dates or maids
        alternatives = find_alternatives(data)
        response = create_booking(alternatives[0])
```

## Testing Endpoints

### Health Check
```http
GET /api/v1/health
```

### Test Authentication
```http
GET /api/v1/auth/me
Authorization: Bearer {token}
```

## Support

For AI agent integration support:
- Email: api-support@maids.ng
- Include `request_id` from response meta
- Reference: Agentic AI Integration Guide v1.0

## Changelog

### v1.0.0 (Current)
- Initial API release
- Full CRUD operations for all entities
- AI-optimized response formats
- Comprehensive error codes
- Role-based access control
