<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\Booking;
use App\Models\EmployerPreference;
use App\Models\MaidAssignment;
use App\Models\MatchingFeePayment;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MetricsController extends ApiController
{
    public function platform(): JsonResponse
    {
        try {
            $employersRegisteredToday = User::where('created_at', '>=', now()->startOfDay())
                ->whereHas('roles', fn($q) => $q->where('name', 'employer'))
                ->count();

            $employersRegistered7d = User::where('created_at', '>=', now()->subDays(7))
                ->whereHas('roles', fn($q) => $q->where('name', 'employer'))
                ->count();

            $quizCompletedNotPaid = EmployerPreference::where('quiz_status', 'completed')
                ->where('matching_status', '!=', 'paid')
                ->count();

            $paymentsToday = MatchingFeePayment::where('created_at', '>=', now()->startOfDay())
                ->where('status', 'completed')
                ->sum('amount');

            $activeAssignments = MaidAssignment::active()->count();

            $totalMaids = User::whereHas('roles', fn($q) => $q->where('name', 'maid'))->count();
            $totalEmployers = User::whereHas('roles', fn($q) => $q->where('name', 'employer'))->count();

            $paymentsThisMonth = MatchingFeePayment::where('created_at', '>=', now()->startOfMonth())
                ->where('status', 'completed')
                ->sum('amount');

            $activeBookings = Booking::where('status', 'active')->count();

            $conversionRate = $totalEmployers > 0
                ? round(($activeAssignments / $totalEmployers) * 100, 2)
                : 0;

            return $this->success([
                'employers_registered_today' => $employersRegisteredToday,
                'employers_registered_7d'    => $employersRegistered7d,
                'quiz_completed_not_paid'    => $quizCompletedNotPaid,
                'payments_today'             => $paymentsToday,
                'active_assignments'         => $activeAssignments,
                'total_maids'                => $totalMaids,
                'total_employers'            => $totalEmployers,
                'payments_this_month'        => $paymentsThisMonth,
                'active_bookings'            => $activeBookings,
                'conversion_rate'            => $conversionRate,
            ], 'Platform metrics retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to fetch platform metrics: ' . $e->getMessage(), 500);
        }
    }

    public function agentHealth(): JsonResponse
    {
        return $this->success([
            'circuit_breakers' => [],
            'status'           => 'healthy',
        ], 'Agent health OK');
    }

    public function revenue(): JsonResponse
    {
        try {
            $matchingFees = WalletTransaction::where('transaction_type', 'credit')
                ->where('reference_type', 'preference')
                ->sum('amount');

            $escrowHeld = WalletTransaction::where('transaction_type', 'escrow_hold')
                ->where('status', 'completed')
                ->sum('amount');

            $payouts = WalletTransaction::whereIn('transaction_type', ['escrow_transfer', 'escrow_release', 'withdrawal'])
                ->where('status', 'completed')
                ->sum('amount');

            $gmv = $matchingFees + $escrowHeld;

            return $this->success([
                'gmv'           => $gmv,
                'matching_fees' => $matchingFees,
                'escrow_held'   => $escrowHeld,
                'payouts'       => $payouts,
            ], 'Revenue metrics retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to fetch revenue metrics: ' . $e->getMessage(), 500);
        }
    }

    public function funnel(): JsonResponse
    {
        return $this->success([
            'visitors'           => 0,
            'registrations'      => 0,
            'quiz_starts'        => 0,
            'quiz_completions'   => 0,
            'payments'           => 0,
            'active_assignments' => 0,
        ], 'Funnel data');
    }
}
