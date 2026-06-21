<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Booking;
use App\Models\MaidProfile;
use App\Models\Review;

class ReviewController extends Controller
{
    public function indexEmployer()
    {
        $employerId = Auth::id();

        // Completed/active bookings that haven't been reviewed yet
        $reviewedIds = Review::where('employer_id', $employerId)->pluck('booking_id');

        $reviewableBookings = Booking::where('employer_id', $employerId)
            ->whereIn('status', ['completed', 'active'])
            ->whereNotIn('id', $reviewedIds)
            ->with('maid')
            ->latest()
            ->get()
            ->map(fn($b) => [
                'id'          => $b->id,
                'maid_id'     => $b->maid_id,
                'maid_name'   => $b->maid?->name,
                'maid_avatar' => $b->maid?->avatar,
                'status'      => $b->status,
                'start_date'  => $b->start_date?->format('M d, Y'),
            ]);

        return Inertia::render('Employer/Reviews', [
            'reviews' => Review::where('employer_id', $employerId)
                ->with('maid.maidProfile')
                ->latest()
                ->paginate(10),
            'reviewableBookings' => $reviewableBookings,
        ]);
    }

    public function indexMaid()
    {
        $user    = Auth::user();
        $profile = $user->maidProfile;

        return Inertia::render('Maid/Reviews', [
            'reviews' => Review::where('maid_id', $user->id)
                ->with('employer')
                ->where('is_flagged', false)
                ->latest()
                ->paginate(10),
            'sentinelLogs' => $profile
                ? \App\Models\AgentActivityLog::where('agent_name', 'Sentinel')
                    ->where('subject_type', MaidProfile::class)
                    ->where('subject_id', $profile->id)
                    ->latest()
                    ->get()
                : [],
        ]);
    }

    public function create(Request $request, \App\Services\Agents\SentinelAgent $sentinel)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'maid_id'    => 'required|exists:users,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'required|string|min:20|max:1000',
        ]);

        $employerId = Auth::id();

        // Verify the booking belongs to this employer and involves this maid
        $booking = Booking::where('id', $validated['booking_id'])
            ->where('employer_id', $employerId)
            ->where('maid_id', $validated['maid_id'])
            ->whereIn('status', ['completed', 'active'])
            ->first();

        if (!$booking) {
            return back()->withErrors(['booking_id' => 'This engagement is not eligible for review.']);
        }

        // Prevent duplicate reviews for the same booking
        $alreadyReviewed = Review::where('employer_id', $employerId)
            ->where('booking_id', $validated['booking_id'])
            ->exists();

        if ($alreadyReviewed) {
            return back()->withErrors(['booking_id' => 'You have already submitted a review for this engagement.']);
        }

        $review = Review::create([
            'employer_id' => $employerId,
            'maid_id'     => $validated['maid_id'],
            'booking_id'  => $validated['booking_id'],
            'rating'      => $validated['rating'],
            'comment'     => $validated['comment'],
            'is_flagged'  => false,
        ]);

        // Let Sentinel assess the maid's overall quality — it may flag the review
        $maidProfile = MaidProfile::where('user_id', $validated['maid_id'])->first();
        if ($maidProfile) {
            try {
                $sentinel->assessMaidQuality($maidProfile);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Sentinel assessment failed: ' . $e->getMessage());
            }

            // Recompute rating using only non-flagged reviews (post-Sentinel refresh)
            $review->refresh();
            $avgRating = Review::where('maid_id', $validated['maid_id'])
                ->where('is_flagged', false)
                ->avg('rating');

            if ($avgRating !== null) {
                $maidProfile->update(['rating' => round($avgRating, 2)]);
            }
        }

        return back()->with('success', 'Your review has been submitted and is under Sentinel review. It will appear on the helper\'s profile once approved.');
    }

    public function update(Request $request) { return back()->with('success', 'Review updated.'); }
    public function destroy() { return back()->with('success', 'Review deleted.'); }
    public function stats() { return response()->json([]); }
}
