import typer
import json
import sys
from ..client.api_client import ApiClient, ApiClientError

app = typer.Typer(help="Manage notifications")

def _format_notification_output(notification: dict, json_output: bool = False) -> str:
    """Format notification data for display."""
    if json_output:
        return json.dumps(notification, indent=2)
    
    lines = []
    
    # Unread indicator
    unread = not notification.get('read_at')
    unread_indicator = "[NEW] " if unread else ""
    
    lines.append(f"{unread_indicator}--- Notification #{notification.get('id', 'N/A')} ---")
    
    if notification.get('type'):
        lines.append(f"Type: {notification['type']}")
    
    if notification.get('title'):
        lines.append(f"Title: {notification['title']}")
    
    if notification.get('message'):
        lines.append(f"Message: {notification['message']}")
    
    if notification.get('data'):
        data = notification['data']
        if isinstance(data, dict):
            lines.append("Data:")
            for k, v in data.items():
                lines.append(f"  {k}: {v}")
    
    if notification.get('created_at'):
        lines.append(f"Created: {notification['created_at']}")
    
    if notification.get('read_at'):
        lines.append(f"Read: {notification['read_at']}")
    else:
        lines.append("Read: No")
    
    return "\n".join(lines)


@app.command("list")
def list_notifications(
    ctx: typer.Context,
    unread_only: bool = typer.Option(False, "--unread", "-u", help="Show only unread notifications"),
    limit: int = typer.Option(20, "--limit", help="Number of notifications to return"),
    offset: int = typer.Option(0, "--offset", help="Offset for pagination"),
):
    """List notifications with optional filters."""
    try:
        client = ApiClient()
        response = client.list_notifications(
            unread_only=unread_only,
            limit=limit,
            offset=offset,
        )
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            notifications = response.get('data', [])
            unread_count = response.get('unread_count', 0)
            total = response.get('total', len(notifications))
            
            if not notifications:
                typer.echo("No notifications found.")
                return
            
            typer.echo(f"Notifications ({total} total, {unread_count} unread, showing {len(notifications)})\n")
            
            for notification in notifications:
                typer.echo(_format_notification_output(notification, json_output=False))
                typer.echo("")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("unread-count")
def get_unread_count(
    ctx: typer.Context,
):
    """Get count of unread notifications."""
    try:
        client = ApiClient()
        response = client.get_unread_count()
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            count = response.get('count', response.get('unread_count', 0))
            typer.echo(f"Unread notifications: {count}")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("get")
def get_notification(
    ctx: typer.Context,
    notification_id: int = typer.Argument(..., help="The ID of the notification to view"),
):
    """Get detailed information about a notification."""
    try:
        client = ApiClient()
        response = client.get_notification(notification_id)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            notification = response.get('data', response)
            typer.echo(_format_notification_output(notification, json_output=False))
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("read")
def mark_as_read(
    ctx: typer.Context,
    notification_id: int = typer.Argument(..., help="The ID of the notification to mark as read"),
):
    """Mark a notification as read."""
    try:
        client = ApiClient()
        response = client.mark_as_read(notification_id)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            message = response.get('message', 'Notification marked as read.')
            typer.echo(message)
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("read-all")
def mark_all_as_read(
    ctx: typer.Context,
):
    """Mark all notifications as read."""
    try:
        client = ApiClient()
        response = client.mark_all_as_read()
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            message = response.get('message', 'All notifications marked as read.')
            typer.echo(message)
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("delete")
def delete_notification(
    ctx: typer.Context,
    notification_id: int = typer.Argument(..., help="The ID of the notification to delete"),
):
    """Delete a notification."""
    try:
        client = ApiClient()
        response = client.delete_notification(notification_id)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            message = response.get('message', 'Notification deleted.')
            typer.echo(message)
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)