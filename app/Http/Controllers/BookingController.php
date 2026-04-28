<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Booking;
use App\Services\Agents\TreasurerAgent;
use App\Services\Agents\RefereeAgent;
use App\Events\BookingCreated;
use Illuminate\Support\Facades\Mail;

class BookingController extends Controller
{
    public function indexEmployer() { 
        return Inertia::render('Employer/Bookings', [
            'bookings' => Booking::where('employer_id', Auth::id())
                ->with('maid')
                ->latest()
                ->paginate(10)
        ]); 
    }
    
    public function indexMaid() { 
        return Inertia::render('Maid/Bookings', [
            'bookings' => Booking::where('maid_id', Auth::id())
                ->with('employer')
                ->latest()
                ->paginate(10)
        ]); 
    }
    
    public function show($id) { 
        $user = Auth::user();
        $booking = Booking::with(['employer', 'maid.maidProfile'])->findOrFail($id);
        
        // Fetch any disputes related to this booking
        $disputes = \App\Models\Dispute::where('booking_id', $id)->latest()->get();
        
        // Fetch agent activities related to this booking (if we have a polymorphic link or similar, but for now we search by ID in reasoning or related subject)
        // Since AgentActivityLog subject could be any model, we'll fetch logs where this booking might be involved
        $agentLogs = \App\Models\AgentActivityLog::where('subject_type', Booking::class)
            ->where('subject_id', $id)
            ->latest()
            ->get();

        $view = $user->hasRole('employer') ? 'Employer/BookingDetail' : 'Maid/BookingDetail';
        
        return Inertia::render($view, [
            'booking' => $booking,
            'disputes' => $disputes,
            'agentLogs' => $agentLogs
        ]); 
    }
    
    public function create(Request $request) 
    { 
        $request->validate([
            'maid_id' => 'required|exists:users,id',
            'preference_id' => 'nullable|exists:employer_preferences,id',
            'start_date' => 'required|date|after:today',
            'end_date' => 'nullable|date|after:start_date',
            'schedule_type' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $employer = Auth::user();
        $maid = \App\Models\User::findOrFail($request->maid_id);
        $salary = $maid->maidProfile->expected_salary ?? 50000;

        $booking = Booking::create([
            'employer_id' => $employer->id,
            'maid_id' => $maid->id,
            'preference_id' => $request->preference_id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'schedule_type' => $request->schedule_type,
            'agreed_salary' => $salary,
            'total_amount' => $salary, // Could include commission here
            'notes' => $request->notes,
        ]);

        // Dispatch broadcasting event
        event(new BookingCreated($booking));
        
        return redirect()->route('employer.bookings.show', $booking->id)
            ->with('success', 'Booking request sent successfully to ' . $maid->name); 
    }
    
    public function accept($id) { 
        Booking::findOrFail($id)->update(['status' => 'accepted']); 
        return back()->with('success', 'Booking accepted.'); 
    }
    
    public function reject($id) { 
        Booking::findOrFail($id)->update(['status' => 'cancelled']); 
        return back()->with('success', 'Booking rejected.'); 
    }
    
    public function cancel(Request $request, $id, RefereeAgent $referee) { 
        $booking = Booking::findOrFail($id);
        $booking->update(['status' => 'cancelled']); 
        
        if ($booking->start_date && now()->isAfter($booking->start_date)) {
            $referee->assessDispute($booking, 'late_cancellation', 'Cancelled after start date.', Auth::user());
            return back()->with('warning', 'Booking cancelled. Subject to Referee Agent review for late cancellation.');
        }

        return back()->with('success', 'Booking cancelled successfully.'); 
    }

    public function start($id) { 
        Booking::findOrFail($id)->update(['status' => 'active']); 
        return back()->with('success', 'Booking started.'); 
    }
    
    public function complete($id, TreasurerAgent $treasurer) { 
        $booking = Booking::findOrFail($id);
        $booking->update(['status' => 'completed', 'completed_at' => now()]); 
        
        // Let the AI Treasurer handle the payout calculation and transfer
        $treasurer->processPayout($booking);

        return back()->with('success', 'Booking completed. Treasurer Agent has been notified to process payouts.'); 
    }
    
    public function stats() { 
        return response()->json(['total' => Booking::where('employer_id', Auth::id())->count()]); 
    }
}
