<?php

namespace App\Console\Commands;

use App\Models\MatchingFeePayment;
use App\Services\FlutterwavePwbtService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cancel pending payments that point at a dead dynamic virtual account.
 *
 * Until 2026-09-16 each PWBT attempt minted a single-use account with a
 * 30-minute expiry. Those accounts are long gone, so the rows can never settle:
 * reconcile() calls verify_by_reference on every pass, Flutterwave answers 400
 * "no transaction found", and the log fills with failures forever. Flutterwave's
 * own history confirms no money was ever received against any of them.
 *
 * They are cancelled rather than deleted — a customer conversation may still
 * reference one, and the count is evidence of how long the dynamic-account flow
 * was failing.
 *
 * Safety: refuses to touch anything on the fixed account, and re-checks each row
 * against Flutterwave before cancelling unless --skip-verify is passed, so a
 * payment that did somehow settle is banked instead of written off.
 */
class CancelStalePwbtPayments extends Command
{
    protected $signature = 'payments:cancel-stale-pwbt
                            {--apply : Actually write (default is a dry run)}
                            {--skip-verify : Do not re-check each row against Flutterwave first}
                            {--reason=dynamic-account-expired : Reason recorded on the row}';

    protected $description = 'Cancel pending PWBT payments left on expired dynamic virtual accounts';

    public function handle(FlutterwavePwbtService $pwbt): int
    {
        $apply = (bool) $this->option('apply');

        $rows = MatchingFeePayment::where('status', 'pending')
            ->where('gateway', 'flutterwave')
            ->where(function ($q) {
                $q->where('account_number', '!=', FlutterwavePwbtService::STATIC_ACCOUNT_NUMBER)
                  ->orWhereNull('account_number');
            })
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to cancel — no pending rows on dynamic accounts.');
            return self::SUCCESS;
        }

        $this->line($apply ? '<fg=red>APPLYING</>' : '<fg=yellow>DRY RUN</> — pass --apply to commit.');
        $this->line("Found {$rows->count()} pending row(s) on dynamic/absent accounts.");
        $this->newLine();

        $cancelled = 0;
        $rescued   = 0;

        foreach ($rows as $row) {
            // Last chance: if this somehow did settle, bank it rather than void it.
            if (!$this->option('skip-verify')) {
                $result = $pwbt->reconcile($row);
                if (!empty($result['paid'])) {
                    $this->line("  <fg=green>RESCUED</> #{$row->id} employer {$row->employer_id} — settled, not cancelling");
                    $rescued++;
                    continue;
                }
            }

            $this->line(sprintf('  CANCEL  #%-4s employer %-5s acct %-12s created %s',
                $row->id, $row->employer_id, $row->account_number ?: '-', $row->created_at));

            if ($apply) {
                $row->update([
                    'status'           => 'cancelled',
                    'cancelled_reason' => (string) $this->option('reason'),
                ]);
                Log::info('Stale PWBT payment cancelled', [
                    'payment_id' => $row->id,
                    'employer_id' => $row->employer_id,
                    'account_number' => $row->account_number,
                    'reason' => $this->option('reason'),
                ]);
                $cancelled++;
            }
        }

        $this->newLine();
        $this->info(($apply ? "Cancelled {$cancelled}" : "Would cancel {$rows->count()}") . ' row(s).'
            . ($rescued ? "  {$rescued} rescued as paid." : ''));

        if (!$apply) {
            $this->comment('Re-run with --apply to commit.');
        }

        return self::SUCCESS;
    }
}
