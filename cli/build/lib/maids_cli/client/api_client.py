import httpx
from typing import Optional, Any, Dict, List
from ..config import load_config, get_current_session
from .schemas import (
    HealthStatus,
    AuthResponse,
    User,
    MaidProfile,
    MaidListResponse,
    Booking,
    BookingResponse,
    BookingListResponse,
    Assignment,
    AssignmentResponse,
    AssignmentListResponse,
    WalletResponse,
    WalletTransaction,
    Notification,
    NotificationListResponse,
    MatchingResponse,
    ReviewListResponse,
    ReferenceDataResponse,
)

class ApiClientError(Exception):
    """Custom exception for API client errors."""
    pass

class ApiClient:
    """
    HTTP client for Maids.ng API.
    
    This client handles authentication, error handling, and provides
    type-safe methods for interacting with the API endpoints.
    
    For CLI Agent usage, this client uses the dedicated /api/v1/cli/* routes
    with a single service token (CLI_AGENT_TOKEN env var). Impersonation of
    specific users is supported via the X-User-ID header.
    """
    
    def __init__(
        self,
        api_url: Optional[str] = None,
        api_key: Optional[str] = None,
        impersonate_user_id: Optional[int] = None,
    ):
        """
        Initialize the API client.
        
        Args:
            api_url: Override the default API URL from config
            api_key: Override the default API key from config (uses CLI_AGENT_TOKEN)
            impersonate_user_id: Optional user ID to impersonate via X-User-ID header
            
        Raises:
            ApiClientError: If no API key is configured
        """
        config = load_config()
        self.api_url = api_url or config.api_url
        # For CLI agent, use CLI_AGENT_TOKEN if set, otherwise fall back to api_key
        self.api_key = api_key or config.cli_agent_token or config.api_key
        
        if not self.api_key:
            raise ApiClientError(
                "API key is not configured. Set CLI_AGENT_TOKEN or run 'maids config set-api-key <key>'"
            )
            
        self.headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Accept": "application/json",
            "Content-Type": "application/json",
        }
        
        # Add impersonation header if specified
        if impersonate_user_id is not None:
            self.headers["X-User-ID"] = str(impersonate_user_id)
        
    def _request(
        self, 
        method: str, 
        endpoint: str, 
        params: Optional[Dict[str, Any]] = None,
        json_data: Optional[Dict[str, Any]] = None,
    ) -> Dict[str, Any]:
        """
        Make an HTTP request to the API.
        
        Args:
            method: HTTP method (GET, POST, PUT, DELETE, etc.)
            endpoint: API endpoint path
            params: Query parameters
            json_data: JSON body data
            
        Returns:
            Parsed JSON response
            
        Raises:
            ApiClientError: On HTTP errors or connection failures
        """
        url = f"{self.api_url.rstrip('/')}/{endpoint.lstrip('/')}"
        try:
            with httpx.Client(timeout=30.0) as client:
                response = client.request(
                    method, 
                    url, 
                    headers=self.headers, 
                    params=params,
                    json=json_data,
                )
                response.raise_for_status()
                if response.text.strip():
                    return response.json()
                return {}
        except httpx.HTTPStatusError as e:
            try:
                error_msg = e.response.json().get('message', e.response.text)
            except Exception:
                error_msg = e.response.text
            raise ApiClientError(f"HTTP {e.response.status_code}: {error_msg}")
        except httpx.RequestError as e:
            raise ApiClientError(f"Connection Error: {str(e)}")

    # =========================================================================
    # Health & Status
    # =========================================================================
    
    def health_check(self) -> Dict[str, Any]:
        """Check API health status."""
        return self._request("GET", "cli/health")
    
    def get_status(self) -> Dict[str, Any]:
        """Get CLI agent status."""
        return self._request("GET", "cli/status")

    # =========================================================================
    # Maid Endpoints
    # =========================================================================
    
    def list_maids(
        self, 
        location: Optional[str] = None,
        verified_only: bool = False,
        status: Optional[str] = None,
        limit: int = 20,
        offset: int = 0,
    ) -> Dict[str, Any]:
        """List available maids with optional filters."""
        params = {"limit": limit, "offset": offset}
        if location:
            params["location"] = location
        if verified_only:
            params["verified_only"] = "true"
        if status:
            params["status"] = status
        return self._request("GET", "cli/maids", params=params)
    
    def get_maid_profile(self, maid_id: int) -> Dict[str, Any]:
        """Get detailed maid profile."""
        return self._request("GET", f"cli/maids/{maid_id}")
    
    def update_maid_availability(self, maid_id: int, is_available: bool) -> Dict[str, Any]:
        """Update maid availability status."""
        return self._request("PATCH", f"cli/maids/{maid_id}/availability", json_data={
            "is_available": is_available,
        })
    
    def get_maid_earnings(self, maid_id: int) -> Dict[str, Any]:
        """Get maid earnings/wallet info."""
        return self._request("GET", f"cli/maids/{maid_id}/earnings")
    
    def get_skills(self) -> Dict[str, Any]:
        """Get list of available skills."""
        return self._request("GET", "cli/reference/skills")
    
    def get_help_types(self) -> Dict[str, Any]:
        """Get list of help types."""
        return self._request("GET", "cli/reference/help-types")

    # =========================================================================
    # Employer Endpoints
    # =========================================================================
    
    def get_employer_preferences(self, employer_id: int) -> Dict[str, Any]:
        """Get employer preferences."""
        return self._request("GET", f"cli/employers/{employer_id}/preferences")
    
    def update_employer_preferences(
        self,
        employer_id: int,
        **kwargs: Any,
    ) -> Dict[str, Any]:
        """Update employer preferences."""
        return self._request("PATCH", f"cli/employers/{employer_id}/preferences", json_data=kwargs)

    # =========================================================================
    # Booking Endpoints
    # =========================================================================
    
    def list_bookings(
        self, 
        status: Optional[str] = None,
        employer_id: Optional[int] = None,
        maid_id: Optional[int] = None,
        limit: int = 20,
        offset: int = 0,
    ) -> Dict[str, Any]:
        """List bookings with optional filters."""
        params = {"limit": limit, "offset": offset}
        if status:
            params["status"] = status
        if employer_id:
            params["employer_id"] = employer_id
        if maid_id:
            params["maid_id"] = maid_id
        return self._request("GET", "cli/bookings", params=params)
    
    def get_user_bookings(self, user_id: int, user_type: str) -> Dict[str, Any]:
        """Get bookings for a specific user."""
        return self._request("GET", "cli/bookings/user", params={
            "user_id": user_id,
            "user_type": user_type,
        })
    
    def create_booking(self, **kwargs: Any) -> Dict[str, Any]:
        """Create a new booking."""
        return self._request("POST", "cli/bookings/create", json_data=kwargs)
    
    def cancel_booking(self, booking_id: int) -> Dict[str, Any]:
        """Cancel a booking."""
        return self._request("POST", f"cli/bookings/{booking_id}/cancel")

    # =========================================================================
    # Assignment Endpoints
    # =========================================================================
    
    def list_assignments(
        self, 
        status: Optional[str] = None,
        employer_id: Optional[int] = None,
        maid_id: Optional[int] = None,
        limit: int = 20,
        offset: int = 0,
    ) -> Dict[str, Any]:
        """List assignments with optional filters."""
        params = {"limit": limit, "offset": offset}
        if status:
            params["status"] = status
        if employer_id:
            params["employer_id"] = employer_id
        if maid_id:
            params["maid_id"] = maid_id
        return self._request("GET", "cli/assignments", params=params)
    
    def get_assignment(self, assignment_id: int) -> Dict[str, Any]:
        """Get detailed assignment info."""
        return self._request("GET", f"cli/assignments/{assignment_id}")
    
    def accept_assignment(self, assignment_id: int) -> Dict[str, Any]:
        """Accept an assignment (as admin)."""
        return self._request("POST", f"cli/assignments/{assignment_id}/accept")
    
    def reject_assignment(self, assignment_id: int, reason: Optional[str] = None) -> Dict[str, Any]:
        """Reject an assignment (as admin)."""
        data = {"reason": reason} if reason else {}
        return self._request("POST", f"cli/assignments/{assignment_id}/reject", json_data=data)
    
    def complete_assignment(self, assignment_id: int) -> Dict[str, Any]:
        """Complete an assignment (as admin)."""
        return self._request("POST", f"cli/assignments/{assignment_id}/complete")
    
    def get_assignment_statistics(self) -> Dict[str, Any]:
        """Get assignment statistics."""
        return self._request("GET", "cli/assignments/statistics")

    # =========================================================================
    # Wallet Endpoints
    # =========================================================================
    
    def get_wallet(self, user_id: int) -> Dict[str, Any]:
        """Get wallet balance and info for a user."""
        return self._request("GET", "cli/wallet", params={"user_id": user_id})
    
    def get_wallet_transactions(
        self,
        user_id: int,
        limit: int = 20,
        offset: int = 0,
    ) -> Dict[str, Any]:
        """Get wallet transaction history for a user."""
        params = {"user_id": user_id, "limit": limit, "offset": offset}
        return self._request("GET", "cli/wallet/transactions", params=params)

    # =========================================================================
    # Notification Endpoints
    # =========================================================================
    
    def list_notifications(
        self, 
        user_id: int,
        unread_only: bool = False,
        limit: int = 20,
        offset: int = 0,
    ) -> Dict[str, Any]:
        """List notifications for a user."""
        params = {"user_id": user_id, "limit": limit, "offset": offset}
        if unread_only:
            params["unread_only"] = "true"
        return self._request("GET", "cli/notifications", params=params)
    
    def get_unread_count(self, user_id: int) -> Dict[str, Any]:
        """Get count of unread notifications for a user."""
        return self._request("GET", "cli/notifications/unread-count", params={"user_id": user_id})
    
    def mark_notification_as_read(self, notification_id: int) -> Dict[str, Any]:
        """Mark a notification as read."""
        return self._request("POST", f"cli/notifications/{notification_id}/read")
    
    def mark_all_notifications_as_read(self, user_id: int) -> Dict[str, Any]:
        """Mark all notifications as read for a user."""
        return self._request("POST", "cli/notifications/mark-all-read", params={"user_id": user_id})
    
    def delete_notification(self, notification_id: int) -> Dict[str, Any]:
        """Delete a notification."""
        return self._request("DELETE", f"cli/notifications/{notification_id}")

    # =========================================================================
    # User Management Endpoints
    # =========================================================================
    
    def list_users(
        self,
        role: Optional[str] = None,
        status: Optional[str] = None,
        limit: int = 20,
        offset: int = 0,
    ) -> Dict[str, Any]:
        """List users with optional filters."""
        params = {"limit": limit, "offset": offset}
        if role:
            params["role"] = role
        if status:
            params["status"] = status
        return self._request("GET", "cli/users", params=params)
    
    def get_user(self, user_id: int) -> Dict[str, Any]:
        """Get detailed user info."""
        return self._request("GET", f"cli/users/{user_id}")
    
    def update_user_status(self, user_id: int, status: str) -> Dict[str, Any]:
        """Update user status."""
        return self._request("PUT", f"cli/users/{user_id}/status", json_data={
            "status": status,
        })