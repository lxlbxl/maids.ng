<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\AgentNote;
use App\Models\FulfillmentCase;
use App\Models\FulfillmentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FulfillmentController extends ApiController
{
    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employer_id'        => 'required|integer|exists:users,id',
            'maid_id'            => 'nullable|integer|exists:users,id',
            'preference_id'      => 'nullable|integer|exists:employer_preferences,id',
            'assignment_id'      => 'nullable|integer|exists:maid_assignments,id',
            'notes'              => 'nullable|string|max:5000',
        ]);

        try {
            $case = FulfillmentCase::create(array_merge($validated, [
                'status'         => 'active',
                'stage'          => 'salary_agreed',
                'hours_in_stage' => 0,
                'last_contact_at' => now(),
            ]));

            if (!empty($validated['notes'])) {
                FulfillmentEvent::create([
                    'fulfillment_case_id' => $case->id,
                    'event_type'          => 'case_opened',
                    'notes'               => $validated['notes'],
                    'actor_type'          => 'agent',
                ]);
            }

            return $this->success($case, 'Fulfillment case opened', [], 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to open fulfillment case: ' . $e->getMessage(), 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $case = FulfillmentCase::with(['employer', 'maid', 'events'])->findOrFail($id);

            return $this->success($case, 'Fulfillment case retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve fulfillment case: ' . $e->getMessage(), 500);
        }
    }

    public function byEmployer(int $userId): JsonResponse
    {
        try {
            $case = FulfillmentCase::where('employer_id', $userId)
                ->where('status', 'active')
                ->with(['employer', 'maid'])
                ->latest()
                ->first();

            return $this->success($case, 'Active case retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve case by employer: ' . $e->getMessage(), 500);
        }
    }

    public function scanAllActive(): JsonResponse
    {
        try {
            $cases = FulfillmentCase::where('status', 'active')
                ->where('stage', '!=', 'active')
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            return $this->success([
                'count' => $cases->count(),
                'cases' => $cases,
            ], 'All active fulfillment cases');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan active cases: ' . $e->getMessage(), 500);
        }
    }

    public function scanStalled(): JsonResponse
    {
        try {
            $cases = FulfillmentCase::where('status', 'active')
                ->where('hours_in_stage', '>', 24)
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            return $this->success([
                'count' => $cases->count(),
                'cases' => $cases,
            ], 'Stalled fulfillment cases');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan stalled cases: ' . $e->getMessage(), 500);
        }
    }

    public function scanAwaitingFirstDay(): JsonResponse
    {
        try {
            $cases = FulfillmentCase::where('status', 'active')
                ->whereIn('stage', ['salary_agreed', 'resumption_set'])
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            return $this->success([
                'count' => $cases->count(),
                'cases' => $cases,
            ], 'Cases awaiting first day');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan awaiting first day: ' . $e->getMessage(), 500);
        }
    }

    public function scanDayOneNotConfirmed(): JsonResponse
    {
        try {
            $cases = FulfillmentCase::where('status', 'active')
                ->where('stage', 'day_one')
                ->whereNull('maid_arrived_day_one')
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            return $this->success([
                'count' => $cases->count(),
                'cases' => $cases,
            ], 'Day one not confirmed cases');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan day one not confirmed: ' . $e->getMessage(), 500);
        }
    }

    public function scanReadyToActivate(): JsonResponse
    {
        try {
            $cases = FulfillmentCase::where('status', 'active')
                ->where('stage', 'day_one')
                ->where('day_one_confirmed_at', '<', now()->subDays(3))
                ->whereNotNull('day_one_confirmed_at')
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            return $this->success([
                'count' => $cases->count(),
                'cases' => $cases,
            ], 'Cases ready to activate');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan ready to activate: ' . $e->getMessage(), 500);
        }
    }

    public function updateStage(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'stage' => 'required|string|max:100',
            'notes' => 'nullable|string|max:5000',
        ]);

        try {
            $case = FulfillmentCase::findOrFail($id);
            $fromStage = $case->stage;

            $case->update([
                'stage'          => $validated['stage'],
                'hours_in_stage' => 0,
                'last_contact_at' => now(),
            ]);

            FulfillmentEvent::create([
                'fulfillment_case_id' => $case->id,
                'event_type'          => 'stage_change',
                'from_stage'          => $fromStage,
                'to_stage'            => $validated['stage'],
                'notes'               => $validated['notes'] ?? null,
                'actor_type'          => 'agent',
            ]);

            return $this->success($case->fresh(), 'Stage updated');
        } catch (\Throwable $e) {
            return $this->error('Failed to update stage: ' . $e->getMessage(), 500);
        }
    }

    public function recordSalary(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'party'  => 'required|string|in:maid,employer',
            'salary' => 'required|numeric|min:0',
        ]);

        try {
            $case = FulfillmentCase::findOrFail($id);

            if ($validated['party'] === 'maid') {
                $case->update(['maid_salary' => $validated['salary']]);
            } else {
                $case->update(['employer_salary' => $validated['salary']]);
            }

            $case->refresh();

            FulfillmentEvent::create([
                'fulfillment_case_id' => $case->id,
                'event_type'          => 'salary_recorded',
                'notes'               => "{$validated['party']} recorded salary: {$validated['salary']}",
                'actor_type'          => 'agent',
            ]);

            if ($case->maid_salary && $case->employer_salary && $case->maid_salary == $case->employer_salary) {
                $case->update([
                    'agreed_salary'       => $case->maid_salary,
                    'salary_confirmed_at' => now(),
                    'stage'               => 'salary_agreed',
                    'hours_in_stage'      => 0,
                    'last_contact_at'     => now(),
                ]);

                FulfillmentEvent::create([
                    'fulfillment_case_id' => $case->id,
                    'event_type'          => 'salary_confirmed',
                    'notes'               => "Both parties agreed on salary: {$case->maid_salary}",
                    'actor_type'          => 'system',
                ]);
            }

            return $this->success($case->fresh(), 'Salary recorded');
        } catch (\Throwable $e) {
            return $this->error('Failed to record salary: ' . $e->getMessage(), 500);
        }
    }

    public function setStartDate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'start_date'       => 'required|date',
            'start_time'       => 'nullable',
            'employer_address' => 'nullable|string|max:500',
        ]);

        try {
            $case = FulfillmentCase::findOrFail($id);
            $case->update(array_merge($validated, [
                'stage'           => 'resumption_set',
                'hours_in_stage'  => 0,
                'last_contact_at' => now(),
            ]));

            FulfillmentEvent::create([
                'fulfillment_case_id' => $case->id,
                'event_type'          => 'start_date_set',
                'notes'               => "Start date set: {$validated['start_date']}",
                'actor_type'          => 'agent',
            ]);

            return $this->success($case->fresh(), 'Start date set');
        } catch (\Throwable $e) {
            return $this->error('Failed to set start date: ' . $e->getMessage(), 500);
        }
    }

    public function confirmArrival(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'maid_arrived_day_one' => 'required|boolean',
            'notes'                => 'nullable|string|max:5000',
        ]);

        try {
            $case = FulfillmentCase::findOrFail($id);
            $case->update([
                'maid_arrived_day_one' => $validated['maid_arrived_day_one'],
                'day_one_confirmed_at' => now(),
                'stage'                => 'day_one',
                'hours_in_stage'       => 0,
                'last_contact_at'      => now(),
            ]);

            FulfillmentEvent::create([
                'fulfillment_case_id' => $case->id,
                'event_type'          => 'arrival_confirmed',
                'notes'               => $validated['notes'] ?? 'Arrival confirmed',
                'actor_type'          => 'agent',
            ]);

            return $this->success($case->fresh(), 'Arrival confirmed');
        } catch (\Throwable $e) {
            return $this->error('Failed to confirm arrival: ' . $e->getMessage(), 500);
        }
    }

    public function storeNote(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'notes'      => 'required|string|max:5000',
            'event_type' => 'nullable|string|max:100',
        ]);

        try {
            $case = FulfillmentCase::findOrFail($id);
            $case->update(['last_contact_at' => now()]);

            $event = FulfillmentEvent::create([
                'fulfillment_case_id' => $case->id,
                'event_type'          => $validated['event_type'] ?? 'note_added',
                'notes'               => $validated['notes'],
                'actor_type'          => 'agent',
            ]);

            return $this->success($event, 'Note added', [], 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to add note: ' . $e->getMessage(), 500);
        }
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        try {
            $case = FulfillmentCase::findOrFail($id);
            $case->update([
                'status'       => 'active',
                'stage'        => 'active',
                'activated_at' => now(),
                'hours_in_stage' => 0,
                'last_contact_at' => now(),
            ]);

            FulfillmentEvent::create([
                'fulfillment_case_id' => $case->id,
                'event_type'          => 'activated',
                'notes'               => 'Case activated',
                'actor_type'          => 'agent',
            ]);

            return $this->success($case->fresh(), 'Case activated');
        } catch (\Throwable $e) {
            return $this->error('Failed to activate case: ' . $e->getMessage(), 500);
        }
    }

    public function fail(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'fail_reason' => 'required|string|max:1000',
        ]);

        try {
            $case = FulfillmentCase::findOrFail($id);
            $case->update([
                'status'         => 'failed',
                'fail_reason'    => $validated['fail_reason'],
                'last_contact_at' => now(),
            ]);

            FulfillmentEvent::create([
                'fulfillment_case_id' => $case->id,
                'event_type'          => 'failed',
                'notes'               => $validated['fail_reason'],
                'actor_type'          => 'agent',
            ]);

            return $this->success($case->fresh(), 'Case marked as failed');
        } catch (\Throwable $e) {
            return $this->error('Failed to mark case as failed: ' . $e->getMessage(), 500);
        }
    }

    public function events(int $id): JsonResponse
    {
        try {
            $events = FulfillmentEvent::where('fulfillment_case_id', $id)
                ->latest()
                ->get();

            return $this->success($events, 'Fulfillment events retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve events: ' . $e->getMessage(), 500);
        }
    }
}
