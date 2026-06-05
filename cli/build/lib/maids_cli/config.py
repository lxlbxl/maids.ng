import json
from pathlib import Path
from typing import Optional, Dict, Any
from pydantic import BaseModel

CONFIG_DIR = Path.home() / ".maids-ng"
CONFIG_FILE = CONFIG_DIR / "config.json"
SESSIONS_FILE = CONFIG_DIR / "sessions.json"

class CLIConfig(BaseModel):
    api_url: str = "https://api.maids.ng/v1"
    api_key: Optional[str] = None
    cli_agent_token: Optional[str] = None  # Dedicated token for CLI agent routes
    default_output_format: str = "human"

class UserSession(BaseModel):
    """Represents an authenticated user session."""
    user_id: int
    email: str
    role: str  # admin, maid, employer
    token: str  # Sanctum token
    name: Optional[str] = None

def load_config() -> CLIConfig:
    if not CONFIG_FILE.exists():
        return CLIConfig()
    try:
        with open(CONFIG_FILE, "r") as f:
            data = json.load(f)
            return CLIConfig(**data)
    except Exception:
        return CLIConfig()

def save_config(config: CLIConfig) -> None:
    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    with open(CONFIG_FILE, "w") as f:
        json.dump(config.model_dump(), f, indent=2)

def load_sessions() -> Dict[str, UserSession]:
    """Load all user sessions from the sessions file."""
    if not SESSIONS_FILE.exists():
        return {}
    try:
        with open(SESSIONS_FILE, "r") as f:
            data = json.load(f)
            return {k: UserSession(**v) for k, v in data.items()}
    except Exception:
        return {}

def save_session(email: str, session: UserSession) -> None:
    """Save a user session."""
    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    sessions = load_sessions()
    sessions[email] = session
    with open(SESSIONS_FILE, "w") as f:
        json.dump({k: v.model_dump() for k, v in sessions.items()}, f, indent=2)

def get_session(email: str) -> Optional[UserSession]:
    """Get a specific user session by email."""
    sessions = load_sessions()
    return sessions.get(email)

def delete_session(email: str) -> bool:
    """Delete a user session."""
    sessions = load_sessions()
    if email in sessions:
        del sessions[email]
        CONFIG_DIR.mkdir(parents=True, exist_ok=True)
        with open(SESSIONS_FILE, "w") as f:
            json.dump({k: v.model_dump() for k, v in sessions.items()}, f, indent=2)
        return True
    return False

def list_sessions() -> Dict[str, Dict[str, Any]]:
    """List all saved sessions (without tokens)."""
    sessions = load_sessions()
    return {
        email: {
            "user_id": s.user_id,
            "email": s.email,
            "role": s.role,
            "name": s.name,
        }
        for email, s in sessions.items()
    }

def get_current_session() -> Optional[UserSession]:
    """Get the current active session (most recently used)."""
    sessions = load_sessions()
    if not sessions:
        return None
    # Return the first session (could be enhanced with last-used tracking)
    return next(iter(sessions.values()))