import typer
import json
import sys
from ..client.api_client import ApiClient, ApiClientError

app = typer.Typer(help="Check system status")

@app.command("check")
def check_status(ctx: typer.Context):
    """Check the availability of the Maids.ng backend API."""
    try:
        client = ApiClient()
        response = client.get_status()
        
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps(response, indent=2))
        else:
            typer.echo(f"System Status: {response.get('status', 'OK')}")
            if "version" in response:
                typer.echo(f"Version: {response['version']}")
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)
