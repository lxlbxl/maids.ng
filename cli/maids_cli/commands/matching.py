import typer
import json
import sys
from ..client.api_client import ApiClient, ApiClientError

app = typer.Typer(help="AI-powered matching")

def _format_matching_job_output(job: dict, json_output: bool = False) -> str:
    """Format matching job data for display."""
    if json_output:
        return json.dumps(job, indent=2)
    
    lines = []
    lines.append(f"--- Matching Job #{job.get('id', 'N/A')} ---")
    
    if job.get('employer_id'):
        lines.append(f"Employer ID: {job['employer_id']}")
    
    if job.get('status'):
        status_icon = "✓" if job['status'] == 'completed' else "○" if job['status'] == 'pending' else "..."
        lines.append(f"Status: {status_icon} {job['status']}")
    
    if job.get('progress') is not None:
        lines.append(f"Progress: {job['progress']}%")
    
    if job.get('created_at'):
        lines.append(f"Created: {job['created_at']}")
    
    if job.get('completed_at'):
        lines.append(f"Completed: {job['completed_at']}")
    
    return "\n".join(lines)


def _format_matching_result_output(result: dict, json_output: bool = False) -> str:
    """Format matching result data for display."""
    if json_output:
        return json.dumps(result, indent=2)
    
    lines = []
    lines.append(f"Maid #{result.get('maid_id', 'N/A')}")
    
    if result.get('score') is not None:
        lines.append(f"  Match Score: {result['score']}%")
    
    if result.get('reasons'):
        lines.append("  Reasons:")
        for reason in result['reasons']:
            lines.append(f"    - {reason}")
    
    return "\n".join(lines)


@app.command("request")
def request_match(
    ctx: typer.Context,
    employer_id: int = typer.Option(None, "--employer-id", "-e", help="Employer ID to find matches for"),
    preference_id: int = typer.Option(None, "--preference-id", "-p", help="Preference ID to use for matching"),
    max_results: int = typer.Option(5, "--max-results", "-m", help="Maximum number of results to return"),
):
    """Request AI-powered matching for an employer."""
    try:
        client = ApiClient()
        
        data = {"max_results": max_results}
        if employer_id:
            data["employer_id"] = employer_id
        if preference_id:
            data["preference_id"] = preference_id
        
        response = client.request_match(**data)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            message = response.get('message', 'Matching job created.')
            job = response.get('job', {})
            
            typer.echo(message)
            
            if job.get('id'):
                typer.echo(f"Job ID: {job['id']}")
            if job.get('status'):
                typer.echo(f"Status: {job['status']}")
            
            typer.echo("\nUse 'maids matching status <job_id>' to check progress.")
            typer.echo("Use 'maids matching results <job_id>' to get results when complete.")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("status")
def get_matching_status(
    ctx: typer.Context,
    job_id: int = typer.Argument(..., help="The ID of the matching job to check"),
):
    """Get the status of a matching job."""
    try:
        client = ApiClient()
        response = client.get_matching_status(job_id)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            job = response.get('job', response)
            typer.echo(_format_matching_job_output(job, json_output=False))
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("results")
def get_matching_results(
    ctx: typer.Context,
    job_id: int = typer.Argument(..., help="The ID of the matching job to get results for"),
):
    """Get the results of a completed matching job."""
    try:
        client = ApiClient()
        response = client.get_matching_results(job_id)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            results = response.get('results', [])
            
            if not results:
                typer.echo("No results found for this job.")
                return
            
            typer.echo(f"Matching Results ({len(results)} matches):\n")
            
            for result in results:
                typer.echo(_format_matching_result_output(result, json_output=False))
                typer.echo("")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("review")
def review_manual_match(
    ctx: typer.Context,
    maid_id: int = typer.Option(..., "--maid-id", "-m", help="Maid ID to assign"),
    employer_id: int = typer.Option(..., "--employer-id", "-e", help="Employer ID to assign to"),
    preference_id: int = typer.Option(None, "--preference-id", "-p", help="Preference ID for the match"),
    notes: str = typer.Option(None, "--notes", "-n", help="Notes for the manual assignment"),
):
    """Review and confirm a manual match assignment."""
    try:
        client = ApiClient()
        
        data = {
            "maid_id": maid_id,
            "employer_id": employer_id,
        }
        if preference_id:
            data["preference_id"] = preference_id
        if notes:
            data["notes"] = notes
        
        response = client.review_manual_match(**data)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            message = response.get('message', 'Manual match confirmed.')
            typer.echo(message)
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("queue")
def get_queue_stats(
    ctx: typer.Context,
):
    """Get matching queue statistics."""
    try:
        client = ApiClient()
        response = client.get_matching_queue_stats()
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            typer.echo("Matching Queue Statistics:")
            for key, value in response.items():
                if isinstance(value, (int, float)):
                    typer.echo(f"  {key.replace('_', ' ').title()}: {value}")
                elif isinstance(value, dict):
                    typer.echo(f"  {key.replace('_', ' ').title()}:")
                    for k, v in value.items():
                        typer.echo(f"    {k}: {v}")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)