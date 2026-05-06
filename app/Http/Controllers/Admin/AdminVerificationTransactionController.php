<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StandaloneVerification;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminVerificationTransactionController extends Controller
{
    /**
     * Display all standalone verification transactions.
     */
    public function index(Request $request)
    {
        $query = StandaloneVerification::with('requester')
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('status')) {
            $query->where('verification_status', $request->input('status'));
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('maid_nin', 'like', "%{$search}%")
                    ->orWhere('maid_first_name', 'like', "%{$search}%")
                    ->orWhere('maid_last_name', 'like', "%{$search}%")
                    ->orWhere('payment_reference', 'like', "%{$search}%")
                    ->orWhere('requester_name', 'like', "%{$search}%")
                    ->orWhere('requester_email', 'like', "%{$search}%");
            });
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        $transactions = $query->paginate(20)->withQueryString();

        // Stats
        $stats = [
            'total' => StandaloneVerification::count(),
            'total_revenue' => StandaloneVerification::where('payment_status', 'paid')->sum('amount'),
            'pending_payment' => StandaloneVerification::where('payment_status', 'pending')->count(),
            'completed' => StandaloneVerification::where('verification_status', 'success')->count(),
            'failed' => StandaloneVerification::where('verification_status', 'failed')->count(),
            'pending_verification' => StandaloneVerification::where('verification_status', 'pending')->count(),
        ];

        return Inertia::render('Admin/VerificationTransactions', [
            'transactions' => $transactions,
            'stats' => $stats,
            'filters' => $request->only(['status', 'payment_status', 'search', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Display a single verification transaction detail.
     */
    public function show($id)
    {
        $verification = StandaloneVerification::with('requester')->findOrFail($id);

        $qoreData = $verification->verification_data['data'] ?? [];

        return Inertia::render('Admin/VerificationTransactionDetail', [
            'verification' => [
                'id' => $verification->id,
                'payment_reference' => $verification->payment_reference,
                'external_reference' => $verification->external_reference,
                'requester_name' => $verification->requester_name,
                'requester_email' => $verification->requester_email,
                'requester' => $verification->requester ? [
                    'id' => $verification->requester->id,
                    'name' => $verification->requester->name,
                    'email' => $verification->requester->email,
                    'phone' => $verification->requester->phone,
                ] : null,
                'maid_nin' => $verification->maid_nin,
                'maid_first_name' => $verification->maid_first_name,
                'maid_last_name' => $verification->maid_last_name,
                'maid_middle_name' => $verification->maid_middle_name,
                'maid_dob' => $verification->maid_dob,
                'maid_phone' => $verification->maid_phone,
                'maid_email' => $verification->maid_email,
                'maid_gender' => $verification->maid_gender,
                'amount' => $verification->amount,
                'gateway' => $verification->gateway,
                'payment_status' => $verification->payment_status,
                'verification_status' => $verification->verification_status,
                'confidence_score' => $verification->confidence_score,
                'name_matched' => $verification->name_matched,
                'qoreid_data' => $qoreData,
                'optional_fields' => $verification->optional_fields ? json_decode($verification->optional_fields, true) : [],
                'created_at' => $verification->created_at->toISOString(),
                'updated_at' => $verification->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Update verification status (manual override).
     */
    public function update($id, Request $request)
    {
        $validated = $request->validate([
            'verification_status' => 'nullable|in:success,failed,pending',
            'notes' => 'nullable|string|max:1000',
        ]);

        $verification = StandaloneVerification::findOrFail($id);

        if (isset($validated['verification_status'])) {
            $verification->update([
                'verification_status' => $validated['verification_status'],
            ]);
        }

        return back()->with('success', 'Verification status updated.');
    }

    /**
     * Export verification transactions as CSV.
     */
    public function export()
    {
        $transactions = StandaloneVerification::with('requester')
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="verification_transactions_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'ID',
                'Reference',
                'Requester Name',
                'Requester Email',
                'NIN',
                'First Name',
                'Last Name',
                'Amount',
                'Gateway',
                'Payment Status',
                'Verification Status',
                'Confidence',
                'Created At',
                'Updated At'
            ]);

            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->id,
                    $tx->payment_reference,
                    $tx->requester_name,
                    $tx->requester_email,
                    $tx->maid_nin,
                    $tx->maid_first_name,
                    $tx->maid_last_name,
                    $tx->amount,
                    $tx->gateway,
                    $tx->payment_status,
                    $tx->verification_status,
                    $tx->confidence_score,
                    $tx->created_at->format('Y-m-d H:i:s'),
                    $tx->updated_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Return stats for dashboard widgets.
     */
    public function stats()
    {
        return response()->json([
            'total' => StandaloneVerification::count(),
            'total_revenue' => StandaloneVerification::where('payment_status', 'paid')->sum('amount'),
            'pending_payment' => StandaloneVerification::where('payment_status', 'pending')->count(),
            'completed' => StandaloneVerification::where('verification_status', 'success')->count(),
            'failed' => StandaloneVerification::where('verification_status', 'failed')->count(),
            'pending_verification' => StandaloneVerification::where('verification_status', 'pending')->count(),
            'today_count' => StandaloneVerification::whereDate('created_at', today())->count(),
            'today_revenue' => StandaloneVerification::whereDate('created_at', today())->where('payment_status', 'paid')->sum('amount'),
            'this_week_count' => StandaloneVerification::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_week_revenue' => StandaloneVerification::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->where('payment_status', 'paid')->sum('amount'),
            'this_month_count' => StandaloneVerification::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'this_month_revenue' => StandaloneVerification::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->where('payment_status', 'paid')->sum('amount'),
        ]);
    }
}