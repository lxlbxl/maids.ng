# Maids.ng Frontend Build

## Summary

Complete mobile-first frontend built for Maids.ng with all required user flows.

## Files Created

### Root
- `index.html` - Unified landing page with three CTAs
- `router.php` - PHP router with trailing slash support and SPA fallback
- `common/style.css` - Design system (gold + dark theme)
- `common/api.js` - API service layer with all endpoints
- `common/app.js` - Shared UI utilities

### Household Flow (/household/)
- `index.html` - Browse maids search bar + featured helpers
- `login.html` - Household login
- `register.html` - Household registration
- `dashboard.html` - Household dashboard with stats
- `requests.html` - List of hire requests sent
- `helpers/index.html` - Search results with filters
- `helpers/profile.html` - Helper profile view (dynamic ID via URL)

### Helper Flow (/helper/)
- `login.html` - Helper login
- `register.html` - Multi-step registration (personal, experience, documents)
- `dashboard.html` - Helper dashboard with incoming requests
- `profile-edit.html` - Edit profile

### Agency Flow (/agency/)
- `login.html` - Agency login
- `register.html` - Agency registration
- `dashboard.html` - Agency dashboard with stats
- `helpers.html` - Manage helpers + bulk CSV upload
- `requests.html` - Incoming hire requests, approve/reject

### Admin Flow (/admin/)
- `login.html` - Admin login
- `dashboard.html` - Platform overview
- `helpers.html` - Manage all helpers (filter, bulk actions, create)
- `agencies.html` - Manage agencies (view, suspend/activate)
- `hire-requests.html` - Approve/reject any request

## Design Decisions

- **Color Scheme**: Gold (#D4AF37) on dark (#0a0a0a) background, per agency dashboard style
- **Mobile-First**: All layouts responsive with Tailwind-like utility classes (custom CSS)
- **Touch-Friendly**: All CTAs and inputs have minimum 44px height for mobile taps
- **Typography**: System fonts with responsive sizing, good line height
- **Spacing**: Consistent 1rem base unit, with gaps scaled (0.5rem, 1rem, 1.5rem, 2rem)
- **Components**: Cards, buttons, forms, tables all styled for dark mode
- **States**: Loading spinners, error alerts, success alerts, badges for status

## Technical Integration

- **API Service**: All calls go through `/common/api.js` using `window.api` singleton
- **Auth**: JWT tokens stored in localStorage; `requireAuth(role)` for protected pages
- **Endpoints**: Implemented per spec assuming backend provides:
  - `/api/auth/login`, `/api/auth/register`
  - `/api/helpers`, `/api/helpers/{id}`
  - `/api/hire-requests`, `/api/hire-requests/{id}`
  - Agency-specific endpoints: `/api/agency/dashboard`, `/api/agency/helpers`, etc.
  - Admin-specific endpoints: `/api/admin/dashboard`, `/api/admin/helpers`, etc.
- **File Uploads**: FormData for helper registration documents and agency bulk CSV
- **Router**: `router.php` handles:
  - Trailing slash normalization
  - Dynamic helper profile: `/household/helpers/{id}.html` → `profile.html`
  - SPA fallback for any unmatched routes to `index.html`
  - Known route mappings for each section

## Testing Instructions for Dr. Alex

1. **Deploy** the `public/` directory to your staging server at `/var/www/maids.ng-staging/backend/public/`
   - Ensure PHP is enabled for `router.php`
   - Check that `/api` endpoints are live and CORS configured if needed

2. **Root Landing Page**:
   - Visit `/` - should see hero with three CTAs
   - Click each CTA to navigate to respective sections
   - "Admin Login" link goes to `/admin/login.html`

3. **Household Flow**:
   - Register a new household account at `/household/register.html`
   - Login at `/household/login.html`
   - Dashboard should load with stats (may need API data)
   - Browse helpers at `/household/helpers/`
   - Click a helper to view profile at `/household/helpers/{any-id}.html`
   - Send a hire request (prompts for description)
   - View requests at `/household/requests.html`

4. **Helper Flow**:
   - Register at `/helper/register.html` multi-step form
   - Upload photo and ID in step 3
   - Login at `/helper/login.html`
   - Dashboard shows incoming requests (need API)
   - Edit profile at `/helper/profile-edit.html`

5. **Agency Flow**:
   - Register at `/agency/register.html`, login
   - Dashboard shows stats
   - Add helper manually at `/agency/helpers.html`
   - Test bulk CSV upload (sample CSV format in UI)
   - Approve/reject requests at `/agency/requests.html`

6. **Admin Flow**:
   - Login at `/admin/login.html`
   - View dashboard stats
   - Manage helpers: filter, create, bulk actions at `/admin/helpers.html`
   - View agencies at `/admin/agencies.html`
   - Approve/reject hire requests at `/admin/hire-requests.html`

7. **Responsive Design**:
   - Test on mobile viewport: all CTAs should be touch-friendly, layouts stack vertically
   - On desktop: grid layouts appear where appropriate (3-column on large screens)

8. **Error Handling**:
   - Try accessing protected pages without login -> should redirect to appropriate login
   - API errors show inline alerts
   - Network failures show error messages

## Notes

- All API calls assume endpoints exist; adjust `API_BASE_URL` in `common/api.js` if needed
- The router works with trailing slash support; both `/household` and `/household/` work
- For helper profile pages, any numeric ID is accepted; if API returns 404, shows error
- Design tokens (gold, dark colors) defined in CSS variables for easy re-branding
- No backend code included; focus on frontend integration

## Next Steps for Tech Ops

- Implement the API endpoints as documented in `common/api.js`
- Ensure CORS headers allow frontend origin
- Set up database with required tables: users, helpers, agencies, hire_requests, documents
- Add sanitization and validation on backend (already on frontend)
- Add file upload handling for helper documents and agency bulk CSV
- Add email notifications for hire request status changes
- Consider adding CSRF protection for forms (currently using JWT)

---

Built with vanilla JS, CSS, and PHP router. No external dependencies beyond Tailwind-like custom CSS utilities.
