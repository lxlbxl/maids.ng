<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MatchingFeePayment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function downloadUsers()
    {
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=maids_ng_users.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $columns = ['ID', 'Name', 'Email', 'Phone', 'Location', 'Role', 'Status', 'Created At'];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            User::with('roles')->chunk(100, function($users) use ($file) {
                foreach ($users as $user) {
                    fputcsv($file, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->phone,
                        $user->location,
                        $user->roles->pluck('name')->implode(', '),
                        $user->status,
                        $user->created_at
                    ]);
                }
            });

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    public function downloadFinancials()
    {
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=maids_ng_financials.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $columns = ['ID', 'Reference', 'Employer', 'Amount', 'Status', 'Date'];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            MatchingFeePayment::with('employer')->chunk(100, function($payments) use ($file) {
                foreach ($payments as $payment) {
                    fputcsv($file, [
                        $payment->id,
                        $payment->reference,
                        $payment->employer->name ?? 'Unknown',
                        'NGN ' . number_format($payment->amount / 1, 2),
                        $payment->status,
                        $payment->paid_at ?? $payment->created_at
                    ]);
                }
            });

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
