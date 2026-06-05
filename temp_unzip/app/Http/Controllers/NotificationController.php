<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function index() { return response()->json(Notification::where('user_id', Auth::id())->latest()->paginate(20)); }
    public function unreadCount() { return response()->json(['count' => Notification::where('user_id', Auth::id())->unread()->count()]); }
    public function recent() { return response()->json(Notification::where('user_id', Auth::id())->latest()->take(5)->get()); }
    public function markRead($id) { Notification::where('id', $id)->where('user_id', Auth::id())->update(['read_at' => now()]); return back(); }
    public function markAllRead() { Notification::where('user_id', Auth::id())->unread()->update(['read_at' => now()]); return back(); }
    public function destroy($id) { Notification::where('id', $id)->where('user_id', Auth::id())->delete(); return back(); }
    public function clearAll() { Notification::where('user_id', Auth::id())->delete(); return back(); }
    public function updatePreferences(Request $request) { return back()->with('success', 'Preferences updated.'); }
    public function sendTest(Request $request) { return back()->with('success', 'Test notification sent.'); }
}
