<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\AgentNote;
use App\Models\CsCase;
use App\Models\FulfillmentCase;
use App\Models\MaidAssignment;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CsController extends ApiController
{
    public function show(int $id): JsonResponse
    {
        try {
            $case = CsCase::with(['employer', 'maid', 'tickets'])->findOrFail($id);

            return $this->success($case, 'CS case retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve CS case: ' . $e->getMessage(), 500);
        }
    }

    public function byEmployer(int $userId): JsonResponse
    {
        try {
            $case = CsCase::where('employer_id', $userId)
                ->where('status', 'active')
                ->with(['employer', 'maid', 'tickets'])
                ->latest()
                ->first();

            return $this->success($case, 'Active CS case retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve CS case by employer: ' . $e->getMessage(), 500);
        }
    }

    public function scanAtRisk(): JsonResponse
    {
        try {
            $cases = CsCase::where('health_status', 'at_risk')
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            return $this->success([
                'count' => $cases->count(),
                'cases' => $cases,
            ], 'At-risk CS cases');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan at-risk cases: ' . $e->getMessage(), 500);
        }
    }

    public function scanAppraisalDue(): JsonResponse
    {
        try {
            $cases = CsCase::where('next_appraisal_due', '<=', now())
                ->whereNotNull('next_appraisal_due')
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            return $this->success([
                'count' => $cases->count(),
                'cases' => $cases,
            ], 'Appraisal due cases');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan appraisal due: ' . $e->getMessage(), 500);
        }
    }

    public function scanNoContact30d(): JsonResponse
    {
        try {
            $cases = CsCase::where('last_contact_at', '<', now()->subDays(30))
                ->whereNotNull('last_contact_at')
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            return $this->success([
                'count' => $cases->count(),
                'cases' => $cases,
            ], 'No contact 30 days cases');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan no-contact cases: ' . $e->getMessage(), 500);
        }
    }

    public function updateHealth(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'health_status' => 'required|string|in:healthy,at_risk,critical,resolved',
        ]);

        try {
            $case = CsCase::findOrFail($id);
            $case->update(['health_status' => $validated['health_status']]);

            return $this->success($case->fresh(), 'Health status updated');
        } catch (\Throwable $e) {
            return $this->error('Failed to update health status: ' . $e->getMessage(), 500);
        }
    }

    public function storeAppraisal(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'satisfaction_score' => 'required|integer|min:1|max:5',
            'next_appraisal_due' => 'nullable|date',
        ]);

        try {
            $case = CsCase::findOrFail($id);
            $case->update([
                'satisfaction_score' => $validated['satisfaction_score'],
                'next_appraisal_due' => $validated['next_appraisal_due'] ?? now()->addDays(30),
                'last_contact_at'    => now(),
            ]);

            return $this->success($case->fresh(), 'Appraisal recorded');
        } catch (\Throwable $e) {
            return $this->error('Failed to record appraisal: ' . $e->getMessage(), 500);
        }
    }

    public function storeNote(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'note'         => 'required|string|max:5000',
            'action_taken' => 'nullable|string|max:100',
            'outcome'      => 'nullable|string|max:50',
            'next_action'  => 'nullable|string|max:255',
            'metadata'     => 'nullable|array',
        ]);

        try {
            CsCase::findOrFail($id);

            $note = AgentNote::create(array_merge($validated, [
                'entity_type'    => 'cs_case',
                'entity_id'      => $id,
                'agent_type'     => request()->agent_api_key->agent_type ?? null,
                'agent_user_id'  => null,
            ]));

            CsCase::where('id', $id)->update(['last_contact_at' => now()]);

            return $this->success($note, 'Note created', [], 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create note: ' . $e->getMessage(), 500);
        }
    }

    public function listTickets(Request $request): JsonResponse
    {
        try {
            $query = SupportTicket::query();

            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            if ($priority = $request->get('priority')) {
                $query->where('priority', $priority);
            }
            if ($type = $request->get('type')) {
                $query->where('type', $type);
            }

            $tickets = $query->with('user:id,name,phone,email')->latest()->paginate(25);

            return $this->paginated($tickets, 'Tickets retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to list tickets: ' . $e->getMessage(), 500);
        }
    }

    public function showTicket(int $id): JsonResponse
    {
        try {
            $ticket = SupportTicket::with('user:id,name,phone,email')->findOrFail($id);

            return $this->success($ticket, 'Ticket retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve ticket: ' . $e->getMessage(), 500);
        }
    }

    public function scanSlaBreached(): JsonResponse
    {
        try {
            $tickets = SupportTicket::where('status', '!=', 'resolved')
                ->where('created_at', '<', now()->subHours(48))
                ->with('user:id,name,phone,email')
                ->get();

            return $this->success([
                'count'   => $tickets->count(),
                'tickets' => $tickets,
            ], 'SLA breached tickets');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan SLA breached: ' . $e->getMessage(), 500);
        }
    }

    public function scanCriticalOpen(): JsonResponse
    {
        try {
            $tickets = SupportTicket::where('priority', 'critical')
                ->where('status', 'open')
                ->with('user:id,name,phone,email')
                ->get();

            return $this->success([
                'count'   => $tickets->count(),
                'tickets' => $tickets,
            ], 'Critical open tickets');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan critical open: ' . $e->getMessage(), 500);
        }
    }

    public function storeTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cs_case_id'      => 'required|integer|exists:cs_cases,id',
            'user_id'         => 'required|integer|exists:users,id',
            'role'            => 'required|string|max:50',
            'type'            => 'required|string|max:100',
            'description'     => 'required|string|max:5000',
            'priority'        => 'nullable|string|in:low,medium,high,critical',
            'disputed_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $ticket = SupportTicket::create([
                'cs_case_id'      => $validated['cs_case_id'],
                'user_id'         => $validated['user_id'],
                'subject'         => $validated['type'],
                'message'         => $validated['description'],
                'status'          => 'open',
                'priority'        => $validated['priority'] ?? 'medium',
                'agent_handled'   => true,
                'agent_response'  => '',
            ]);

            return $this->success($ticket, 'Ticket created', [], 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create ticket: ' . $e->getMessage(), 500);
        }
    }

    public function updateTicket(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'subject'    => 'nullable|string|max:255',
            'message'    => 'nullable|string|max:5000',
            'status'     => 'nullable|string|in:open,in_progress,resolved,closed',
            'priority'   => 'nullable|string|in:low,medium,high,critical',
            'agent_response' => 'nullable|string|max:5000',
        ]);

        try {
            $ticket = SupportTicket::findOrFail($id);
            $ticket->update($validated);

            return $this->success($ticket->fresh(), 'Ticket updated');
        } catch (\Throwable $e) {
            return $this->error('Failed to update ticket: ' . $e->getMessage(), 500);
        }
    }

    public function resolveTicket(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'resolution_notes' => 'nullable|string|max:5000',
        ]);

        try {
            $ticket = SupportTicket::findOrFail($id);
            $ticket->update([
                'status'          => 'resolved',
                'agent_response'  => $validated['resolution_notes'] ?? 'Resolved by agent',
                'agent_handled'   => true,
            ]);

            return $this->success($ticket->fresh(), 'Ticket resolved');
        } catch (\Throwable $e) {
            return $this->error('Failed to resolve ticket: ' . $e->getMessage(), 500);
        }
    }

    public function escalateTicket(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:5000',
        ]);

        try {
            $ticket = SupportTicket::findOrFail($id);
            $ticket->update([
                'priority'       => 'critical',
                'agent_response' => $validated['notes'] ?? 'Ticket escalated',
            ]);

            return $this->success($ticket->fresh(), 'Ticket escalated');
        } catch (\Throwable $e) {
            return $this->error('Failed to escalate ticket: ' . $e->getMessage(), 500);
        }
    }

    public function scanExitsRecent(): JsonResponse
    {
        try {
            $failedCases = FulfillmentCase::where('status', 'failed')
                ->where('updated_at', '>=', now()->subDays(30))
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            $cancelledAssignments = MaidAssignment::where('status', 'cancelled')
                ->where('cancelled_at', '>=', now()->subDays(30))
                ->with(['employer:id,name,phone', 'maid:id,name,phone'])
                ->get();

            return $this->success([
                'failed_cases'          => $failedCases,
                'cancelled_assignments' => $cancelledAssignments,
            ], 'Recent exits retrieved');
        } catch (\Throwable $e) {
            return $this->error('Failed to scan recent exits: ' . $e->getMessage(), 500);
        }
    }

    public function recordExit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|in:fulfillment_case,assignment',
            'entity_id'   => 'required|integer',
            'reason'      => 'nullable|string|max:1000',
        ]);

        try {
            AgentNote::create([
                'entity_type'    => 'exit',
                'entity_id'      => $validated['entity_id'],
                'note'           => "Exit recorded for {$validated['entity_type']} #{$validated['entity_id']}: " . ($validated['reason'] ?? 'No reason provided'),
                'agent_type'     => request()->agent_api_key->agent_type ?? null,
                'agent_user_id'  => null,
            ]);

            return $this->success(null, 'Exit recorded');
        } catch (\Throwable $e) {
            return $this->error('Failed to record exit: ' . $e->getMessage(), 500);
        }
    }
}
