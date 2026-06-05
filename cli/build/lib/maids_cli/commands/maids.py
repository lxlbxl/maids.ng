import typer
import json
import sys
from ..client.api_client import ApiClient, ApiClientError

app = typer.Typer(help="Browse and search for maids")

def _format_maid_output(maid: dict, json_output: bool = False) -> str:
    """Format maid data for display."""
    if json_output:
        return json.dumps(maid, indent=2)
    
    lines = []
    lines.append(f"--- Maid #{maid.get('id', 'N/A')} ---")
    
    if maid.get('user_id'):
        lines.append(f"User ID: {maid['user_id']}")
    
    if maid.get('location'):
        lines.append(f"Location: {maid['location']}")
    
    if maid.get('experience_years'):
        lines.append(f"Experience: {maid['experience_years']} years")
    
    if maid.get('bio'):
        lines.append(f"Bio: {maid['bio']}")
    
    if maid.get('availability'):
        status = "Available" if maid['availability'] else "Not Available"
        lines.append(f"Status: {status}")
    
    if maid.get('verified'):
        lines.append(f"Verified: {'Yes' if maid['verified'] else 'No'}")
    
    if maid.get('rating'):
        lines.append(f"Rating: {maid['rating']}/5.0 ({maid.get('total_reviews', 0)} reviews)")
    
    if maid.get('skills'):
        lines.append("Skills:")
        for skill in maid['skills']:
            skill_name = skill.get('name', 'Unknown')
            skill_level = skill.get('level', '')
            if skill_level:
                lines.append(f"  - {skill_name} ({skill_level})")
            else:
                lines.append(f"  - {skill_name}")
    
    return "\n".join(lines)


@app.command("list")
def list_maids(
    ctx: typer.Context,
    location: str = typer.Option(None, "--location", "-l", help="Filter by location"),
    verified_only: bool = typer.Option(False, "--verified", "-v", help="Show only verified maids"),
    limit: int = typer.Option(20, "--limit", help="Number of results to return"),
    offset: int = typer.Option(0, "--offset", help="Offset for pagination"),
):
    """List available maids with optional filters."""
    try:
        client = ApiClient()
        response = client.list_maids(
            location=location,
            verified_only=verified_only,
            limit=limit,
            offset=offset,
        )
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            maids = response.get('data', [])
            total = response.get('total', len(maids))
            
            if not maids:
                typer.echo("No maids found matching your criteria.")
                return
            
            typer.echo(f"Found {total} maid(s) (showing {len(maids)})\n")
            
            for maid in maids:
                typer.echo(_format_maid_output(maid, json_output=False))
                typer.echo("")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("get")
def get_maid(
    ctx: typer.Context,
    maid_id: int = typer.Argument(..., help="The ID of the maid to view"),
):
    """Get detailed profile for a specific maid."""
    try:
        client = ApiClient()
        response = client.get_maid_profile(maid_id)
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            # Extract the maid data from the response
            maid = response.get('data', response)
            typer.echo(_format_maid_output(maid, json_output=False))
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("skills")
def list_skills(
    ctx: typer.Context,
):
    """List all available skills."""
    try:
        client = ApiClient()
        response = client.get_skills()
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            skills = response.get('data', [])
            if not skills:
                typer.echo("No skills found.")
                return
            
            typer.echo(f"Available Skills ({len(skills)}):\n")
            for skill in skills:
                skill_name = skill.get('name', 'Unknown')
                category = skill.get('category', '')
                if category:
                    typer.echo(f"  - {skill_name} ({category})")
                else:
                    typer.echo(f"  - {skill_name}")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)


@app.command("help-types")
def list_help_types(
    ctx: typer.Context,
):
    """List all available help types."""
    try:
        client = ApiClient()
        response = client.get_help_types()
        
        json_output = ctx.obj and ctx.obj.get("json")
        
        if json_output:
            typer.echo(json.dumps(response, indent=2))
        else:
            help_types = response.get('data', [])
            if not help_types:
                typer.echo("No help types found.")
                return
            
            typer.echo(f"Available Help Types ({len(help_types)}):\n")
            for ht in help_types:
                name = ht.get('name', 'Unknown')
                description = ht.get('description', '')
                if description:
                    typer.echo(f"  - {name}: {description}")
                else:
                    typer.echo(f"  - {name}")
                
    except ApiClientError as e:
        if ctx.obj and ctx.obj.get("json"):
            typer.echo(json.dumps({"error": str(e)}), err=True)
        else:
            typer.echo(f"Error: {e}", err=True)
        sys.exit(1)