<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MaidProfile;
use App\Models\EmployerPreference;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Dispute;
use Illuminate\Support\Facades\DB;

class McpAgentController extends Controller
{
    // Maid Management
    public function getMaidProfile($maid_id)
    {
        $maid = MaidProfile::with('user')->where('user_id', $maid_id)->firstOrFail();
        return response()->json(['data' => $maid]);
    }

    public function updateMaidAvailability(Request $request, $maid_id)
    {
        $request->validate(['is_available' => 'required|boolean']);
        $maid = MaidProfile::where('user_id', $maid_id)->firstOrFail();
        $maid->availability_status = $request->is_available ? 'available' : 'unavailable';
        $maid->save();
        return response()->json(['message' => 'Availability updated', 'data' => $maid]);
    }

    public function getMaidEarnings($maid_id)
    {
        $wallet = DB::table('maid_wallet')->where('user_id', $maid_id)->first();
        return response()->json(['data' => ['wallet' => $wallet]]);
    }

    // Employer Management
    public function getEmployerPreferences($employer_id)
    {
        $prefs = EmployerPreference::where('user_id', $employer_id)->firstOrFail();
        return response()->json(['data' => $prefs]);
    }

    public function updateEmployerPreferences(Request $request, $employer_id)
    {
        $prefs = EmployerPreference::where('user_id', $employer_id)->firstOrFail();
        $prefs->update($request->only(['schedule', 'budget', 'help_types']));
        return response()->json(['message' => 'Preferences updated', 'data' => $prefs]);
    }

    // Booking & Assignment
    public function createBooking(Request $request)
    {
        $request->validate([
            'employer_id' => 'required|integer',
            'maid_id' => 'required|integer',
            'service_type' => 'required|string',
        ]);
        
        $booking = Booking::create($request->all());
        return response()->json(['message' => 'Booking created', 'data' => $booking]);
    }

    public function cancelBooking($booking_id)
    {
        $booking = Booking::findOrFail($booking_id);
        $booking->status = 'cancelled';
        $booking->save();
        return response()->json(['message' => 'Booking cancelled', 'data' => $booking]);
    }

    public function getUserBookings(Request $request)
    {
        $user_id = $request->user_id;
        $user_type = $request->user_type; // 'employer' or 'maid'

        $bookings = Booking::where($user_type . '_id', $user_id)->get();
        return response()->json(['data' => $bookings]);
    }

    public function triggerAiMatching(Request $request)
    {
        $request->validate(['employer_id' => 'required|integer']);
        return response()->json(['message' => 'AI matching triggered for employer ' . $request->employer_id]);
    }

    // Support Tools
    public function createReview(Request $request)
    {
        $review = Review::create($request->all());
        return response()->json(['message' => 'Review created', 'data' => $review]);
    }

    public function fileDispute(Request $request)
    {
        $dispute = Dispute::create($request->all());
        return response()->json(['message' => 'Dispute filed', 'data' => $dispute]);
    }
}
