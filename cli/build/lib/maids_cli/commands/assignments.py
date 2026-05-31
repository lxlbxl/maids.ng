import typer
import json
import sys
from ..client.api_client import ApiClient, ApiClientError

app = typer.Typer(help="Manage assignments")

def _format_assignment_output(assignment: dict, json_output: bool = False) -> str:
    """Format assignment data for display."""
    if json_output:
        return json.dumps(assignment, indent=2)
    
    lines = []
    lines.append(f"--- Assignment #{assignment.get('id', 'N/A')} ---")
    
    if assignment.get('employer_id'):
        lines.append(f"Employer ID: {assignment['employer_id']}")
    
    if assignment.get('maid_id'):
        lines.append(f"Maid ID: {assignment['maid_id']}")
    
    if assignment.get('booking_id'):
        lines.append(f"Booking ID: {assignment['booking_id']}")
    
    if assignment.get('status'):
        lines.append(f"Status: {assignment['status']}")
    
    if assignment.get('started_at'):
        lines.append(f"Started: {assignment['started_at']}")
    
    if assignment.get('completed_at'):
        lines.append(f"Completed: {assignment['completed_at']}")
    
    if assignment.get('created_at'):
        lines.append(f"Created: {assignment['created_at']}")
    
    return "\n".join(lines)


@app.command("list")
def list_assignments(
    ctx: typer.Context,
    status: str = typer.Option(None, "--status", "-s", help="Filter by status"),
    limit: int = typer.Option(20, "--limit", help="Number of results to return"),
    offset: int = typer.Option(0, "--offset", help="Offset for pagination"),
):
    """List assignments with optional filters."""
    try:
        client = ApiClient()
        response = client.list_assignments(
            status=status,
            limit=limit,
            offset=offset,
        )
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            assignments = response.get('data', [])
            total = response.get('total', len(assignments))
            
            if not assignments:
                typer.echo("No assignments found.")
                return
            
            typer.echo(f"Found {total} assignment(s) (showing {len(assignments)})\n")
            
            for assignment in assignments:
                typer.echo(_format_assignment_output(assignment, json_output=False))
                typer.echo("")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("get")
def get_assignment(
    ctx: typer.Context,
    assignment_id: int = typer.Argument(..., help="The ID of the assignment to view"),
):
    """Get detailed information about an assignment."""
    try:
        client = ApiClient()
        response = client.get_assignment(assignment_id)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            assignment = response.get('data', response)
            typer.echo(_format_assignment_output(assignment, json_output=False))
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("accept")
def accept_assignment(
    ctx: typer.Context,
    assignment_id: int = typer.Argument(..., help="The ID of the assignment to accept"),
):
    """Accept an assignment."""
    try:
        client = ApiClient()
        response = client.accept_assignment(assignment_id)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            message = response.get('message', 'Assignment accepted successfully.')
            typer.echo(message)
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("reject")
def reject_assignment(
    ctx: typer.Context,
    assignment_id: int = typer.Argument(..., help="The ID of the assignment to reject"),
    reason: str = typer.Option(None, "--reason", "-r", help="Reason for rejection"),
):
    """Reject an assignment."""
    try:
        client = ApiClient()
        response = client.reject_assignment(assignment_id, reason=reason)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            message = response.get('message', 'Assignment rejected.')
            typer.echo(message)
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("complete")
def complete_assignment(
    ctx: typer.Context,
    assignment_id: int = typer.Argument(..., help="The ID of the assignment to complete"),
):
    """Complete an assignment."""
    try:
        client = ApiClient()
        response = client.complete_assignment(assignment_id)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            message = response.get('message', 'Assignment completed successfully.')
            typer.echo(message)
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("stats")
def assignment_statistics(
    ctx: typer.Context,
):
    """Get assignment statistics."""
    try:
        client = ApiClient()
        response = client.get_assignment_statistics()
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            typer.echo("Assignment Statistics:")
            for key, value in response.items():
                if isinstance(value, (int, float)):
                    typer.echo(f"  {key.replace('_', ' ').title()}: {value}")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)