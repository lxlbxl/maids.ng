import typer
import json
import sys
from ..client.api_client import ApiClient, ApiClientError

app = typer.Typer(help="User management commands")

@app.command("list")
def list_users(
    ctx: typer.Context,
    role: str = typer.Option(None, "--role", "-r", help="Filter by role (admin/maid/employer)"),
    status: str = typer.Option(None, "--status", "-s", help="Filter by status"),
    limit: int = typer.Option(20, "--limit", "-l", help="Number of results"),
    offset: int = typer.Option(0, "--offset", "-o", help="Offset for pagination"),
):
    """List all users with optional filters."""
    try:
        client = ApiClient()
        response = client.list_users(
            role=role,
            status=status,
            limit=limit,
            offset=offset,
        )
        
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps(response, indent=2))
        else:
            users = response.get("data", [])
            total = response.get("total", 0)
            typer.echo(f"Found {total} user(s) (showing {len(users)})")
            typer.echo()
            for user in users:
                typer.echo(f"--- User #{user.get('id', 'N/A')} ---")
                typer.echo(f"  Name: {user.get('name', 'N/A')}")
                typer.echo(f"  Email: {user.get('email', 'N/A')}")
                typer.echo(f"  Role: {user.get('role', 'N/A')}")
                typer.echo(f"  Status: {user.get('status', 'N/A')}")
                typer.echo()
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)

@app.command("get")
def get_user(ctx: typer.Context, user_id: int = typer.Argument(..., help="User ID")):
    """Get detailed user information."""
    try:
        client = ApiClient()
        response = client.get_user(user_id)
        
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps(response, indent=2))
        else:
            user = response.get("data", {})
            typer.echo(f"User #{user.get('id', 'N/A')}")
            typer.echo(f"  Name: {user.get('name', 'N/A')}")
            typer.echo(f"  Email: {user.get('email', 'N/A')}")
            typer.echo(f"  Role: {user.get('role', 'N/A')}")
            typer.echo(f"  Status: {user.get('status', 'N/A')}")
            if user.get('maid_profile'):
                typer.echo(f"  Maid Status: {user['maid_profile'].get('availability_status', 'N/A')}")
            if user.get('employer_preference'):
                typer.echo(f"  Has Preferences: Yes")
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)

@app.command("update-status")
def update_user_status(
    ctx: typer.Context,
    user_id: int = typer.Argument(..., help="User ID"),
    status: str = typer.Option(..., "--status", "-s", help="New status (active/inactive/suspended/banned)"),
):
    """Update user status."""
    try:
        client = ApiClient()
        response = client.update_user_status(user_id, status)
        
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps(response, indent=2))
        else:
            typer.echo(f"User #{user_id} status updated to '{status}'")
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)