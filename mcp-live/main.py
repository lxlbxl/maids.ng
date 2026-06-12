import os
import httpx
from typing import Optional, Dict, Any, List
from dotenv import load_dotenv
from fastmcp import FastMCP
from fastapi import Request
from fastapi.responses import JSONResponse

# Load environment variables
load_dotenv()

MCP_AUTH_TOKEN = os.getenv("MCP_AUTH_TOKEN")
MAIDS_NG_API_URL = os.getenv("MAIDS_NG_API_URL", "https://api.maids.ng")
MAIDS_NG_API_TOKEN = os.getenv("MAIDS_NG_API_TOKEN")

# Initialize FastMCP
mcp = FastMCP("Maids-NG-Assistant")

app = mcp._app if hasattr(mcp, "_app") else None

if app:
    @app.middleware("http")
    async def verify_token_middleware(request: Request, call_next):
        auth_header = request.headers.get("Authorization")
        if not auth_header or not auth_header.startswith("Bearer "):
            return JSONResponse(status_code=401, content={"detail": "Missing or invalid Bearer token"})
        
        token = auth_header.split(" ")[1]
        if token != MCP_AUTH_TOKEN:
            return JSONResponse(status_code=401, content={"detail": "Unauthorized"})
            
        return await call_next(request)

# --- HTTP Client Wrapper ---

def api_request(method: str, endpoint: str, params: Optional[Dict] = None, json_data: Optional[Dict] = None) -> dict:
    """Helper function to make requests to the Maids.ng API and handle errors gracefully."""
    url = f"{MAIDS_NG_API_URL}{endpoint}"
    headers = {
        "Authorization": f"Bearer {MAIDS_NG_API_TOKEN}",
        "Accept": "application/json"
    }
    
    try:
        with httpx.Client(timeout=15.0) as client:
            response = client.request(method, url, headers=headers, params=params, json=json_data)
            response.raise_for_status()
            if response.text.strip():
                return response.json()
            return {"success": True}
    except httpx.HTTPStatusError as e:
        try:
            error_data = e.response.json()
        except Exception:
            error_data = {"detail": e.response.text}
        return {"error": "API Error", "status_code": e.response.status_code, "details": error_data}
    except httpx.RequestError as e:
        return {"error": "Connection Error", "details": str(e)}

# --- Public/General Tools ---

@mcp.tool()
def search_maids(location: str, max_budget: int = None) -> dict:
    """Fetch real-time availability for maids in a specific area (Public Endpoint)."""
    params = {"location": location}
    if max_budget:
        params["max_budget"] = max_budget
    return api_request("GET", "/v1/maids", params=params)

@mcp.tool()
def get_booking_status(booking_id: int) -> dict:
    """Check the current state of an engagement/booking."""
    # Assuming standard booking endpoint
    return api_request("GET", f"/v1/bookings/{booking_id}")

# --- Dedicated Agent Tools (Requires MCP Backend Route Group) ---

@mcp.tool()
def get_maid_profile(maid_id: int) -> dict:
    """Fetch full maid details, skills, and verification status."""
    return api_request("GET", f"/v1/mcp/maids/{maid_id}")

@mcp.tool()
def update_maid_availability(maid_id: int, is_available: bool) -> dict:
    """Toggle a maid's availability for new assignments."""
    payload = {"is_available": is_available}
    return api_request("PATCH", f"/v1/mcp/maids/{maid_id}/availability", json_data=payload)

@mcp.tool()
def get_maid_earnings(maid_id: int) -> dict:
    """Retrieve salary and wallet balances for a maid."""
    return api_request("GET", f"/v1/mcp/maids/{maid_id}/earnings")

@mcp.tool()
def get_employer_preferences(employer_id: int) -> dict:
    """Fetch an employer's current needs (schedule, budget, help_types)."""
    return api_request("GET", f"/v1/mcp/employers/{employer_id}/preferences")

@mcp.tool()
def update_employer_preferences(employer_id: int, schedule: str = None, budget: int = None, help_types: str = None) -> dict:
    """Modify employer requirements. Help_types should be comma-separated if provided."""
    payload = {}
    if schedule: payload["schedule"] = schedule
    if budget: payload["budget"] = budget
    if help_types: payload["help_types"] = help_types.split(",")
    return api_request("PATCH", f"/v1/mcp/employers/{employer_id}/preferences", json_data=payload)

@mcp.tool()
def create_booking(employer_id: int, maid_id: int, service_type: str, start_date: str, schedule_type: str) -> dict:
    """Initiate a new engagement between an employer and a maid."""
    payload = {
        "employer_id": employer_id,
        "maid_id": maid_id,
        "service_type": service_type,
        "start_date": start_date,
        "schedule_type": schedule_type
    }
    return api_request("POST", "/v1/mcp/bookings/create", json_data=payload)

@mcp.tool()
def cancel_booking(booking_id: int) -> dict:
    """Terminate an active or pending booking."""
    return api_request("POST", f"/v1/mcp/bookings/{booking_id}/cancel")

@mcp.tool()
def get_user_bookings(user_id: int, user_type: str) -> dict:
    """Fetch all bookings for either an employer or a maid. user_type must be 'employer' or 'maid'."""
    if user_type not in ["employer", "maid"]:
        return {"error": "Invalid user_type. Must be 'employer' or 'maid'."}
    params = {"user_id": user_id, "user_type": user_type}
    return api_request("GET", "/v1/mcp/bookings", params=params)

@mcp.tool()
def trigger_ai_matching(employer_id: int) -> dict:
    """Force the backend matching algorithm to generate new assignments for an employer."""
    payload = {"employer_id": employer_id}
    return api_request("POST", "/v1/mcp/matching/trigger", json_data=payload)

@mcp.tool()
def create_review(booking_id: int, reviewer_id: int, rating: int, comment: str) -> dict:
    """Submit feedback for a booking (rating 1-5)."""
    payload = {
        "booking_id": booking_id,
        "reviewer_id": reviewer_id,
        "rating": rating,
        "comment": comment
    }
    return api_request("POST", "/v1/mcp/reviews", json_data=payload)

@mcp.tool()
def file_dispute(booking_id: int, issuer_id: int, dispute_type: str, description: str) -> dict:
    """Open a support ticket/dispute for a specific booking."""
    payload = {
        "booking_id": booking_id,
        "issuer_id": issuer_id,
        "type": dispute_type,
        "description": description
    }
    return api_request("POST", "/v1/mcp/disputes", json_data=payload)

if __name__ == "__main__":
    port = int(os.getenv("MCP_SERVER_PORT", "8000"))
    mcp.run(transport="sse", host="0.0.0.0", port=port)
