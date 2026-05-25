import typer
import json
import sys
from ..client.api_client import ApiClient, ApiClientError

app = typer.Typer(help="Manage bookings")

@app.command("get")
def get_booking(
    ctx: typer.Context,
    id: int = typer.Option(..., "--id", help="The ID of the booking to fetch")
):
    """Fetch details for a specific booking."""
    try:
        client = ApiClient()
        response = client.get_booking(id)
        
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps(response, indent=2))
        else:
            # Human readable format
            typer.echo(f"--- Booking #{id} ---")
            data = response.get("data", response)
            for k, v in data.items():
                if isinstance(v, dict):
                    typer.echo(f"{k}:")
                    for sub_k, sub_v in v.items():
                        typer.echo(f"  {sub_k}: {sub_v}")
                else:
                    typer.echo(f"{k}: {v}")
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error fetching booking: {e}", err=True)
        sys.exit(1)
