<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminDisputeController extends Controller
{
    public function index() 
    { 
        $disputes = \App\Models\Dispute::with(['user', 'booking.employer', 'booking.maid'])
            ->latest()
            ->paginate(10);

        $stats = [
            'total' => \App\Models\Dispute::count(),
            'pending' => \App\Models\Dispute::where('status', 'pending')->count(),
            'resolved' => \App\Models\Dispute::where('status', 'resolved')->count(),
            'escalated' => \App\Models\Dispute::where('status', 'escalated')->count(),
        ];

        return Inertia::render('Admin/Disputes', [
            'disputes' => $disputes,
            'stats' => $stats,
        ]); 
    }

    public function resolve($id, Request $request) 
    { 
        $dispute = \App\Models\Dispute::findOrFail($id);
        $dispute->update([
            'status' => 'resolved',
            'resolution' => $request->notes,
        ]);
        return back()->with('success', 'Dispute marked as resolved.'); 
    }

    public function refund($id)
    {
        try {
            $dispute = \App\Models\Dispute::findOrFail($id);

            $booking = $dispute->booking_id ? \Illuminate\Support\Facades\DB::table('bookings')->where('id', $dispute->booking_id)->first() : null;
            $employerId = $booking->employer_id ?? $dispute->user_id ?? null;

            if ($employerId) {
                try {
                    $walletService = app(\App\Services\WalletService::class);
                    $walletService->creditEmployerWallet(
                        $employerId,
                        $booking->agreed_salary ?? 5000,
                        "Refund for dispute #DISP-{$id}",
                        $dispute->booking_id,
                        'dispute_refund'
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Wallet refund failed for dispute: ' . $e->getMessage());
                }
            }

            $dispute->update([
                'status' => 'refunded',
            ]);

            try {
                \Illuminate\Support\Facades\DB::table('agent_activity_logs')->insert([
                    'agent_type' => 'admin_manual',
                    'action' => 'dispute_refund',
                    'description' => "Refund initiated for dispute #{$id}",
                    'metadata' => json_encode(['dispute_id' => $id, 'employer_id' => $employerId, 'admin_id' => auth()->id()]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
            }

            return back()->with('success', 'Refund initiated successfully.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Dispute refund failed: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Refund failed: ' . $e->getMessage()]);
        }
    }
}
