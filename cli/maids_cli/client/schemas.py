from pydantic import BaseModel
from typing import Optional, Dict, Any, List
from datetime import datetime

# =============================================================================
# Health & Status Schemas
# =============================================================================

class HealthStatus(BaseModel):
    """API health check response."""
    status: str
    service: str
    version: str
    timestamp: str

# =============================================================================
# Authentication Schemas
# =============================================================================

class User(BaseModel):
    """User profile data."""
    id: int
    name: str
    email: str
    role: str
    status: Optional[str] = None
    created_at: Optional[str] = None

class AuthResponse(BaseModel):
    """Authentication response with token."""
    user: User
    token: Optional[str] = None
    message: Optional[str] = None

# =============================================================================
# Maid Schemas
# =============================================================================

class MaidSkill(BaseModel):
    """Maid skill."""
    id: int
    name: str
    level: Optional[str] = None

class MaidProfile(BaseModel):
    """Maid profile data."""
    id: int
    user_id: int
    bio: Optional[str] = None
    location: Optional[str] = None
    experience_years: Optional[int] = None
    skills: Optional[List[MaidSkill]] = []
    availability: Optional[str] = None
    verified: bool = False
    rating: Optional[float] = None
    total_reviews: int = 0

class MaidListResponse(BaseModel):
    """List of maids response."""
    data: List[MaidProfile]
    total: int = 0
    message: Optional[str] = None

# =============================================================================
# Employer Schemas
# =============================================================================

class EmployerPreference(BaseModel):
    """Employer preference data."""
    id: int
    user_id: int
    help_types: Optional[List[str]] = []
    schedule: Optional[str] = None
    urgency: Optional[str] = None
    budget: Optional[float] = None
    location: Optional[str] = None
    matching_status: Optional[str] = None

class EmployerProfile(BaseModel):
    """Employer profile data."""
    id: int
    user_id: int
    preferences: Optional[List[EmployerPreference]] = []

# =============================================================================
# Booking Schemas
# =============================================================================

class Booking(BaseModel):
    """Booking data."""
    id: int
    employer_id: int
    maid_id: int
    status: str
    service_type: str
    start_date: Optional[str] = None
    end_date: Optional[str] = None
    schedule_type: Optional[str] = None
    salary: Optional[float] = None
    created_at: Optional[str] = None

class BookingResponse(BaseModel):
    """Booking response wrapper."""
    data: Booking
    message: Optional[str] = None

class BookingListResponse(BaseModel):
    """List of bookings response."""
    data: List[Booking]
    total: int = 0
    message: Optional[str] = None

# =============================================================================
# Assignment Schemas
# =============================================================================

class Assignment(BaseModel):
    """Assignment data."""
    id: int
    employer_id: int
    maid_id: int
    booking_id: Optional[int] = None
    status: str
    started_at: Optional[str] = None
    completed_at: Optional[str] = None
    created_at: Optional[str] = None

class AssignmentResponse(BaseModel):
    """Assignment response wrapper."""
    data: Assignment
    message: Optional[str] = None

class AssignmentListResponse(BaseModel):
    """List of assignments response."""
    data: List[Assignment]
    total: int = 0
    message: Optional[str] = None

# =============================================================================
# Wallet & Payment Schemas
# =============================================================================

class WalletBalance(BaseModel):
    """Wallet balance data."""
    user_id: int
    balance: float
    currency: str = "NGN"
    pending_withdrawals: float = 0.0
    available_balance: float = 0.0

class WalletTransaction(BaseModel):
    """Wallet transaction data."""
    id: int
    user_id: int
    type: str
    amount: float
    status: str
    reference: Optional[str] = None
    description: Optional[str] = None
    created_at: Optional[str] = None

class WalletResponse(BaseModel):
    """Wallet response wrapper."""
    data: WalletBalance
    transactions: Optional[List[WalletTransaction]] = []
    message: Optional[str] = None

# =============================================================================
# Salary Schemas
# =============================================================================

class SalarySchedule(BaseModel):
    """Salary schedule data."""
    id: int
    assignment_id: int
    amount: float
    due_date: str
    status: str
    paid_at: Optional[str] = None

class SalaryPayment(BaseModel):
    """Salary payment data."""
    id: int
    assignment_id: int
    amount: float
    status: str
    paid_at: Optional[str] = None
    reference: Optional[str] = None

class SalaryListResponse(BaseModel):
    """List of salary items response."""
    data: List[Dict[str, Any]]
    total: int = 0
    message: Optional[str] = None

# =============================================================================
# Notification Schemas
# =============================================================================

class Notification(BaseModel):
    """Notification data."""
    id: int
    user_id: int
    type: str
    title: str
    message: str
    data: Optional[Dict[str, Any]] = None
    read_at: Optional[str] = None
    created_at: Optional[str] = None

class NotificationListResponse(BaseModel):
    """List of notifications response."""
    data: List[Notification]
    unread_count: int = 0
    total: int = 0
    message: Optional[str] = None

# =============================================================================
# Matching Schemas
# =============================================================================

class MatchingJob(BaseModel):
    """Matching job data."""
    id: int
    employer_id: int
    status: str
    progress: int = 0
    created_at: Optional[str] = None
    completed_at: Optional[str] = None

class MatchingResult(BaseModel):
    """Matching result data."""
    maid_id: int
    score: float
    reasons: Optional[List[str]] = []

class MatchingResponse(BaseModel):
    """Matching response wrapper."""
    job: Optional[MatchingJob] = None
    results: Optional[List[MatchingResult]] = []
    message: Optional[str] = None

# =============================================================================
# Review Schemas
# =============================================================================

class Review(BaseModel):
    """Review data."""
    id: int
    reviewer_id: int
    reviewee_id: int
    booking_id: Optional[int] = None
    rating: int
    comment: Optional[str] = None
    flagged: bool = False
    created_at: Optional[str] = None

class ReviewListResponse(BaseModel):
    """List of reviews response."""
    data: List[Review]
    average_rating: Optional[float] = None
    total: int = 0
    message: Optional[str] = None

# =============================================================================
# Reference Data Schemas
# =============================================================================

class Skill(BaseModel):
    """Reference skill data."""
    id: int
    name: str
    category: Optional[str] = None

class HelpType(BaseModel):
    """Reference help type data."""
    id: int
    name: str
    description: Optional[str] = None

class ReferenceDataResponse(BaseModel):
    """Reference data response."""
    data: List[Dict[str, Any]]
    message: Optional[str] = None

# =============================================================================
# Admin & Report Schemas
# =============================================================================

class SystemHealth(BaseModel):
    """System health data."""
    status: str
    database: str
    cache: str
    queue: str
    agents: Dict[str, str]

class PlatformStats(BaseModel):
    """Platform statistics."""
    total_users: int
    total_maids: int
    total_employers: int
    active_assignments: int
    total_revenue: float
    pending_withdrawals: float

class AdminDashboardResponse(BaseModel):
    """Admin dashboard response."""
    stats: PlatformStats
    health: SystemHealth
    message: Optional[str] = None