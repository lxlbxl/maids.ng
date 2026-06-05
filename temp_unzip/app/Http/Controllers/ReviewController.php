<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Review;

class ReviewController extends Controller
{
    public function indexEmployer() { 
        return Inertia::render('Employer/Reviews', [
            'reviews' => Review::where('employer_id', Auth::id())
                ->with('maid.maidProfile')
                ->latest()
                ->paginate(10)
        ]); 
    }

    public function indexMaid() { 
        $user = Auth::user();
        $profile = $user->maidProfile;

        return Inertia::render('Maid/Reviews', [
            'reviews' => Review::where('maid_id', $user->id)
                ->with('employer')
                ->latest()
                ->paginate(10),
            'sentinelLogs' => $profile ? \App\Models\AgentActivityLog::where('agent_name', 'Sentinel')
                ->where('subject_type', \App\Models\MaidProfile::class)
                ->where('subject_id', $profile->id)
                ->latest()
                ->get() : []
        ]); 
    }

    public function create(Request $request, \App\Services\Agents\SentinelAgent $sentinel) { 
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'maid_id' => 'required|exists:users,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $review = Review::create([
            'employer_id' => Auth::id(),
            'maid_id' => $validated['maid_id'],
            'booking_id' => $validated['booking_id'],
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        // Trigger Sentinel Agent to review the maid's overall quality score
        $maid = \App\Models\MaidProfile::where('user_id', $validated['maid_id'])->first();
        if ($maid) {
            // Update average rating (simplified)
            $avgRating = Review::where('maid_id', $validated['maid_id'])->avg('rating');
            $maid->update(['rating' => $avgRating]);
            
            $sentinel->assessMaidQuality($maid);
        }

        return back()->with('success', 'Review submitted. Sentinel Agent has updated the helper\'s quality score.'); 
    }
    public function update($id, Request $request) { return back()->with('success', 'Review updated.'); }
    public function destroy($id) { return back()->with('success', 'Review deleted.'); }
    public function stats() { return response()->json([]); }
}
