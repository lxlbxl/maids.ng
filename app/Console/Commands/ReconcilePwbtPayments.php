<?php

namespace App\Console\Commands;

use App\Models\MatchingFeePayment;
use App\Services\FlutterwavePwbtService;
use Illuminate\Console\Command;

/**
 * Backstop reconciliation for Flutterwave Pay-with-Bank-Transfer payments.
 *
 * We cannot rely on the Flutterwave webhook: the same Flutterwave account is
 * shared across multiple Digital20 brands and it has a single webhook URL that
 * may not point at Maids.ng. In-chat payments are pulled live by the CS agent
 * via /payments/verify-pwbt; this command covers everything else (website PWBT,
 * customers who never say "done", etc.) by polling every recent pending PWBT
 * payment straight from the Flutterwave API.
 */
class ReconcilePwbtPayments extends Command
{
    protected $signature = 'payments:reconcile-pwbt {--hours=48 : Look back this many hours} {--id= : Reconcile a single payment id}';

    protected $description = 'Poll Flutterwave for pending PWBT payments and settle any that have been paid';

    public function handle(FlutterwavePwbtService $pwbt): int
    {
        $query = MatchingFeePayment::query()
            ->where('gateway', 'flutterwave')
            ->whereNotNull('tx_ref')
            ->whereIn('status', ['pending', 'processing']);

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        } else {
            $query->where('created_at', '>=', now()->subHours((int) $this->option('hours')));
        }

        $payments = $query->orderBy('id')->get();

        if ($payments->isEmpty()) {
            $this->info('No pending PWBT payments to reconcile.');
            return self::SUCCESS;
        }

        $paid = 0;
        $tally = [];
        foreach ($payments as $payment) {
            $result = $pwbt->reconcile($payment);
            $tally[$result['status']] = ($tally[$result['status']] ?? 0) + 1;
            if (! empty($result['paid'])) {
                $paid++;
                $this->line("  ✓ payment #{$payment->id} ({$payment->tx_ref}) — confirmed");
            } elseif ($result['status'] === 'amount_mismatch') {
                $this->warn("  ! payment #{$payment->id} ({$payment->tx_ref}) — amount mismatch "
                    . "(paid {$result['paid_amount']}, expected {$result['expected_amount']})");
            }
        }

        $this->info(sprintf(
            'Reconciled %d pending PWBT payment(s): %d newly confirmed. [%s]',
            $payments->count(),
            $paid,
            collect($tally)->map(fn ($n, $s) => "$s:$n")->implode(' ')
        ));

        return self::SUCCESS;
    }
}
