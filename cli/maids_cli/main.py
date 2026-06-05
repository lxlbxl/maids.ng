import typer
from .commands import auth, status, bookings, maids, assignments, wallet, notifications, matching, users

app = typer.Typer(
    name="maids",
    help="Maids.ng CLI Tool - The local limb for agents and developers",
    no_args_is_help=True,
    context_settings={"help_option_names": ["-h", "--help"]},
)

# Register command groups
app.add_typer(auth.app, name="config")
app.add_typer(status.app, name="status")
app.add_typer(bookings.app, name="booking")
app.add_typer(maids.app, name="maid")
app.add_typer(assignments.app, name="assignment")
app.add_typer(wallet.app, name="wallet")
app.add_typer(notifications.app, name="notification")
app.add_typer(matching.app, name="matching")
app.add_typer(users.app, name="user")


@app.callback()
def main(
    ctx: typer.Context,
    json: bool = typer.Option(
        False, 
        "--json", 
        help="Output raw JSON instead of human-readable text",
    ),
    version: bool = typer.Option(
        False,
        "--version",
        "-v",
        help="Show version and exit",
        is_flag=True,
    ),
):
    """
    Maids.ng CLI Tool
    
    A command-line interface for interacting with the Maids.ng API.
    Use this tool to manage bookings, assignments, wallets, notifications,
    and AI-powered matching operations.
    
    Examples:
    
        # Configure API key
        $ maids config set-api-key your-cli-agent-token
        
        # Check system status
        $ maids status check
        
        # Browse maids
        $ maids maid list --location "Lagos" --verified
        
        # Manage bookings
        $ maids booking list
        $ maids booking get --id 123
        
        # Handle assignments
        $ maids assignment list
        $ maids assignment accept 456
        
        # Check wallet for a user
        $ maids wallet balance --user-id 123
        
        # Manage notifications
        $ maids notification list --user-id 123 --unread
        $ maids notification read-all --user-id 123
        
        # User management
        $ maids user list
        $ maids user get 123
        $ maids user update-status 123 --status suspended
        
        # AI matching
        $ maids matching request --employer-id 1
        $ maids matching status 123
        $ maids matching results 123
    """
    if version:
        typer.echo("maids-cli version 0.1.0")
        raise typer.Exit()
    
    if ctx.obj is None:
        ctx.obj = {}
    
    ctx.obj["json"] = json


if __name__ == "__main__":
    app()