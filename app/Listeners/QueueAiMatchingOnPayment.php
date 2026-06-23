<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Models\AiMatchingQueue;
use App\Models\EmployerPreference;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class QueueAiMatchingOnPayment implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct()
    {
    }

    public function handle(PaymentConfirmed $event): void
    {
        if (!in_array($event->type, ['matching_fee', 'guarantee_match'])) {
            return;
        }

        $employerId = $event->user->id;

        $preference = EmployerPreference::where('employer_id', $employerId)
            ->whereIn('matching_status', ['paid', 'guarantee_paid'])
            ->latest()
            ->first();

        if (!$preference) {
            Log::warning('PaymentConfirmed: No eligible preference found for employer', [
                'employer_id' => $employerId,
                'payment_type' => $event->type,
            ]);
            return;
        }

        $jobType = $preference->selected_maid_id ? 'direct_selection' : 'auto_match';

        AiMatchingQueue::create([
            'job_type' => $jobType,
            'employer_id' => $employerId,
            'maid_id' => $preference->selected_maid_id,
            'preference_id' => $preference->id,
            'priority' => $event->type === 'guarantee_match' ? 3 : 5,
            'status' => 'pending',
            'max_attempts' => 3,
            'retry_delay_minutes' => 5,
            'payload' => [
                'payment_reference' => $event->reference,
                'payment_amount' => $event->amount,
                'payment_type' => $event->type,
                'preference_id' => $preference->id,
                'selected_maid_id' => $preference->selected_maid_id,
            ],
            'context_snapshot' => [
                'help_types' => $preference->help_types,
                'location' => $preference->location,
                'state' => $preference->state,
                'budget_min' => $preference->budget_min,
                'budget_max' => $preference->budget_max,
                'schedule' => $preference->schedule,
            ],
        ]);

        Log::info('AiMatchingQueue job created after payment', [
            'employer_id' => $employerId,
            'preference_id' => $preference->id,
            'job_type' => $jobType,
            'payment_type' => $event->type,
        ]);
    }
}
