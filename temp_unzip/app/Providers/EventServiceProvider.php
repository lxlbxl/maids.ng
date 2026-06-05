<?php

namespace App\Providers;

use App\Events\AssignmentAccepted;
use App\Events\AssignmentCompleted;
use App\Events\AssignmentRejected;
use App\Events\MatchingJobCompleted;
use App\Events\SalaryOverdue;
use App\Events\SalaryPaymentProcessed;
use App\Events\WithdrawalApproved;
use App\Events\WithdrawalRequested;
use App\Listeners\CreateAssignmentFromMatch;
use App\Listeners\CreateSalarySchedule;
use App\Listeners\EscalateOverdueToAdmin;
use App\Listeners\FinalizeSalary;
use App\Listeners\NotifyAdminOfRejection;
use App\Listeners\NotifyAdminOfWithdrawalRequest;
use App\Listeners\NotifyBothPartiesOfCompletion;
use App\Listeners\NotifyEmployerOfMatching;
use App\Listeners\NotifyEmployerOfOverdue;
use App\Listeners\NotifyEmployerOfPayment;
use App\Listeners\NotifyMaidOfAssignment;
use App\Listeners\NotifyMaidOfPayment;
use App\Listeners\NotifyMaidOfWithdrawalApproval;
use App\Listeners\ProcessBankTransfer;
use App\Listeners\ProcessRefund;
use App\Listeners\TriggerReplacementSearch;
use App\Listeners\UpdateMaidAvailabilityOnAccept;
use App\Listeners\UpdateMaidAvailabilityOnComplete;
use App\Listeners\UpdateScheduleAfterPayment;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
            // Assignment Events
        AssignmentAccepted::class => [
            CreateSalarySchedule::class,
            NotifyMaidOfAssignment::class,
            UpdateMaidAvailabilityOnAccept::class,
        ],

        AssignmentRejected::class => [
            ProcessRefund::class,
            TriggerReplacementSearch::class,
            NotifyAdminOfRejection::class,
        ],

        AssignmentCompleted::class => [
            FinalizeSalary::class,
            UpdateMaidAvailabilityOnComplete::class,
            NotifyBothPartiesOfCompletion::class,
        ],

            // Salary Events
        SalaryPaymentProcessed::class => [
            NotifyMaidOfPayment::class,
            NotifyEmployerOfPayment::class,
            UpdateScheduleAfterPayment::class,
        ],

        SalaryOverdue::class => [
            EscalateOverdueToAdmin::class,
            NotifyEmployerOfOverdue::class,
        ],

            // Withdrawal Events
        WithdrawalRequested::class => [
            NotifyAdminOfWithdrawalRequest::class,
        ],

        WithdrawalApproved::class => [
            ProcessBankTransfer::class,
            NotifyMaidOfWithdrawalApproval::class,
        ],

            // AI Matching Events
        MatchingJobCompleted::class => [
            CreateAssignmentFromMatch::class,
            NotifyEmployerOfMatching::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // We explicitly register events above
    }
}
