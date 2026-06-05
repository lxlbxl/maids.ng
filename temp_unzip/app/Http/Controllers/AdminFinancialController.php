<?php
namespace App\Http\Controllers;
use Inertia\Inertia;

class AdminFinancialController extends Controller
{
    public function payments()
    {
        $payments = \App\Models\MatchingFeePayment::with('employer')
            ->latest()
            ->paginate(20);

        $totalRevenue = \App\Models\MatchingFeePayment::where('status', 'paid')->sum('amount');
        $escrowTotal = \App\Models\Booking::where('status', 'active')->sum('agreed_salary');

        // Verification revenue
        $verificationRevenue = \App\Models\StandaloneVerification::where('payment_status', 'paid')->sum('amount');
        $verificationCount = \App\Models\StandaloneVerification::count();
        $verificationPending = \App\Models\StandaloneVerification::where('payment_status', 'pending')->count();

        return Inertia::render('Admin/Financials', [
            'payments' => $payments,
            'stats' => [
                'total_revenue' => $totalRevenue,
                'escrow_balance' => $escrowTotal,
                'pending_payouts' => \App\Models\Booking::where('status', 'completed')->where('payment_status', 'pending')->count(),
                'total_payments' => \App\Models\MatchingFeePayment::count(),
                'verification_revenue' => $verificationRevenue,
                'verification_count' => $verificationCount,
                'verification_pending' => $verificationPending,
            ]
        ]);
    }

    public function earnings()
    {
        $monthlyRevenue = \App\Models\MatchingFeePayment::where('status', 'paid')
            ->selectRaw("strftime('%Y-%m', paid_at) as month, SUM(amount) as total")
            ->groupByRaw("strftime('%Y-%m', paid_at)")
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        $stats = [
            'total_revenue' => \App\Models\MatchingFeePayment::where('status', 'paid')->sum('amount'),
            'this_month' => \App\Models\MatchingFeePayment::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
            'total_bookings_value' => \App\Models\Booking::where('status', 'completed')->sum('agreed_salary'),
            'pending_payouts' => \App\Models\Booking::where('status', 'completed')->where('payment_status', 'pending')->sum('agreed_salary'),
        ];

        return Inertia::render('Admin/Earnings', [
            'monthlyRevenue' => $monthlyRevenue,
            'stats' => $stats,
        ]);
    }
}
