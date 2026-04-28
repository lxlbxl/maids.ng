# Maids.ng API Documentation

## Overview

Welcome to the Maids.ng API - a comprehensive REST API designed for the "Agentic Era". This API provides full access to the Maids.ng platform for domestic help matching and management, optimized for both human developers and AI agents.

## Documentation Structure

```
docs/api/
├── README.md              # This file - Getting started guide
├── openapi.yaml           # OpenAPI 3.0 specification
├── AGENTIC_GUIDE.md       # AI agent integration guide
└── examples/              # Code examples (coming soon)
    ├── python/
    ├── javascript/
    └── php/
```

## Quick Start

### 1. Authentication

Obtain an access token:

```bash
curl -X POST https://api.maids.ng/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "yourpassword",
    "device_name": "My App"
  }'
```

Response:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": { ... },
    "token": "your-api-token",
    "token_type": "Bearer"
  },
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "request_id": "uuid-v4",
    "api_version": "1.0.0"
  }
}
```

### 2. Make Authenticated Requests

```bash
curl -X GET https://api.maids.ng/v1/maids \
  -H "Authorization: Bearer your-api-token"
```

## API Features

### 🎯 Core Functionality

- **Authentication**: Token-based authentication with Laravel Sanctum
- **Maid Discovery**: Search and filter available maids
- **AI Matching**: Intelligent matching based on preferences
- **Booking Management**: Full booking lifecycle (create, confirm, complete, cancel)
- **Payment Processing**: Initialize, verify, and manage payments
- **Reviews & Ratings**: Rate and review completed bookings
- **Admin Operations**: Comprehensive admin dashboard and management

### 🤖 AI-Optimized

- **Standardized Responses**: Consistent JSON envelope across all endpoints
- **Machine-Readable Errors**: Structured error codes for automated handling
- **Request Tracking**: UUID-based request IDs for debugging
- **Pagination**: Standardized pagination with metadata
- **Rate Limiting**: Clear rate limit headers

## API Endpoints

### Public Endpoints (No Authentication)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/v1/health` | GET | Health check |
| `/v1/maids` | GET | List maids |
| `/v1/maids/search` | GET | Search maids |
| `/v1/maids/{id}` | GET | Get maid profile |
| `/v1/maids/top-rated` | GET | Top rated maids |
| `/v1/maids/verified` | GET | Verified maids |
| `/v1/reference/skills` | GET | Available skills |
| `/v1/reference/help-types` | GET | Help types |
| `/v1/reference/payment-methods` | GET | Payment methods |
| `/v1/matching/find` | POST | AI matching |

### Authentication

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/v1/auth/register` | POST | Register new user |
| `/v1/auth/login` | POST | Login |
| `/v1/auth/me` | GET | Get current user |
| `/v1/auth/logout` | POST | Logout |
| `/v1/auth/logout-all` | POST | Logout all sessions |
| `/v1/auth/refresh` | POST | Refresh token |
| `/v1/auth/profile` | PUT | Update profile |
| `/v1/auth/password` | PUT | Change password |

### Maid Endpoints (Requires: maid role)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/v1/maid/profile` | GET | Get profile |
| `/v1/maid/profile` | PUT | Update profile |
| `/v1/maid/bank-details` | PUT | Update bank details |
| `/v1/maid/bookings` | GET | List bookings |
| `/v1/maid/bookings/{id}/confirm` | POST | Confirm booking |

### Employer Endpoints (Requires: employer role)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/v1/employer/preferences` | GET | List preferences |
| `/v1/employer/preferences` | POST | Create preference |
| `/v1/employer/preferences/{id}` | PUT | Update preference |
| `/v1/employer/preferences/{id}` | DELETE | Delete preference |
| `/v1/employer/bookings` | GET | List bookings |
| `/v1/employer/reviews` | GET | List reviews |
| `/v1/employer/reviews` | POST | Create review |
| `/v1/employer/dashboard` | GET | Dashboard stats |

### Booking Endpoints (Authenticated)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/v1/bookings` | GET | List bookings |
| `/v1/bookings` | POST | Create booking |
| `/v1/bookings/{id}` | GET | Get booking |
| `/v1/bookings/{id}/start` | POST | Start booking |
| `/v1/bookings/{id}/complete` | POST | Complete booking |
| `/v1/bookings/{id}/cancel` | POST | Cancel booking |
| `/v1/bookings/statistics` | GET | Booking stats |

### Payment Endpoints (Authenticated)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/v1/payments` | GET | List payments |
| `/v1/payments/initialize` | POST | Initialize payment |
| `/v1/payments/verify/{reference}` | GET | Verify payment |
| `/v1/payments/{id}` | GET | Get payment |
| `/v1/payments/{id}/retry` | POST | Retry payment |
| `/v1/payments/statistics` | GET | Payment stats |
| `/v1/payments/webhook` | POST | Payment webhook |

### Admin Endpoints (Requires: admin role)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/v1/admin/dashboard` | GET | Dashboard stats |
| `/v1/admin/system-health` | GET | System health |
| `/v1/admin/users` | GET | List users |
| `/v1/admin/users/{id}` | GET | Get user |
| `/v1/admin/users/{id}/status` | PUT | Update user status |
| `/v1/admin/maids` | GET | List maids |
| `/v1/admin/maids/{id}/verify` | PUT | Verify maid |
| `/v1/admin/bookings` | GET | List bookings |
| `/v1/admin/payments` | GET | List payments |
| `/v1/admin/revenue-report` | GET | Revenue report |
| `/v1/admin/reviews` | GET | List reviews |

## Response Format

All responses follow this standardized structure:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { ... },
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "api_version": "1.0.0",
    "pagination": {
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

## Error Handling

Errors include structured information:

```json
{
  "success": false,
  "message": "Validation failed",
  "data": null,
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "api_version": "1.0.0"
  },
  "error": {
    "code": "VALIDATION_ERROR",
    "errors": {
      "email": ["The email field is required."],
      "password": ["The password must be at least 8 characters."]
    }
  }
}
```

### HTTP Status Codes

- `200` - Success
- `201` - Created
- `204` - No Content
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `409` - Conflict
- `422` - Validation Error
- `429` - Rate Limited
- `500` - Server Error

## Rate Limiting

- **Public endpoints**: 60 requests/minute
- **Authenticated endpoints**: 1000 requests/minute

Rate limit headers:
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
```

## For AI Agents

See [AGENTIC_GUIDE.md](./AGENTIC_GUIDE.md) for:
- AI-optimized integration patterns
- Machine-readable error handling
- Automated retry strategies
- Webhook processing
- Best practices for automated systems

## OpenAPI Specification

The complete API specification is available in [openapi.yaml](./openapi.yaml). You can:

1. **Import into Postman**: Import the YAML file for interactive testing
2. **Generate Client SDKs**: Use OpenAPI generators for your language
3. **View Documentation**: Use Swagger UI or Redoc for visual documentation

## SDKs and Libraries

### Official SDKs (Coming Soon)

- Python: `maidsng-python`
- JavaScript/Node.js: `maidsng-js`
- PHP: `maidsng-php`

### Community SDKs

Community SDKs are welcome! Please submit a PR to add yours to this list.

## Support

- **API Support**: api-support@maids.ng
- **Documentation**: https://docs.maids.ng
- **Status Page**: https://status.maids.ng

## Changelog

### v1.0.0 (2024-01-15)
- Initial API release
- Full CRUD operations for all entities
- AI-optimized response formats
- Comprehensive error codes
- Role-based access control
- OpenAPI 3.0 specification
- Agentic AI integration guide

## Contributing

We welcome contributions! Please see our [Contributing Guide](../../CONTRIBUTING.md) for details.

## License

This API is proprietary software. See [LICENSE](../../LICENSE) for details.

---

**Built with ❤️ by the Maids.ng Team**
