<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    public function index(Request $request) 
    { 
        $reviews = \App\Models\Review::with(['employer', 'maid', 'booking'])
            ->when($request->flagged, fn($q) => $q->where('is_flagged', true))
            ->latest()
            ->paginate(20);

        $stats = [
            'total' => \App\Models\Review::count(),
            'average_rating' => round(\App\Models\Review::avg('rating'), 1),
            'flagged' => \App\Models\Review::where('is_flagged', true)->count(),
        ];

        return Inertia::render('Admin/Reviews', [
            'reviews' => $reviews,
            'stats' => $stats,
            'filters' => $request->only(['flagged']),
        ]); 
    }

    public function toggleFlag($id) 
    { 
        $review = \App\Models\Review::findOrFail($id);
        $review->update(['is_flagged' => !$review->is_flagged]);
        return back()->with('success', $review->is_flagged ? 'Review flagged.' : 'Flag removed.'); 
    }

    public function destroy($id) 
    { 
        \App\Models\Review::findOrFail($id)->delete();
        return back()->with('success', 'Review deleted.'); 
    }
}
