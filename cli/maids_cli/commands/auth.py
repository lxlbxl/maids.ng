import typer
from ..config import load_config, save_config

app = typer.Typer(help="Manage CLI configuration and authentication")

@app.command("set-api-key")
def set_api_key(
    ctx: typer.Context,
    key: str = typer.Argument(..., help="Your Maids.ng API key"),
    api_url: str = typer.Option(None, help="Optionally override the API URL")
):
    """Set the API key for authenticating with Maids.ng."""
    config = load_config()
    config.api_key = key
    if api_url:
        config.api_url = api_url
    save_config(config)
    
    if ctx.obj and ctx.obj.get("json"):
        import json
        typer.echo(json.dumps({"status": "success", "message": "API key saved successfully"}))
    else:
        typer.echo("API key saved successfully.")
