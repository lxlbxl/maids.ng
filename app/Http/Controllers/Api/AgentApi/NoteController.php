<?php

namespace App\Http\Controllers\Api\AgentApi;

use App\Http\Controllers\Api\ApiController;
use App\Models\AgentNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type'         => 'required|string|max:100',
            'entity_id'           => 'required|integer',
            'note'                => 'required|string|max:5000',
            'action_taken'        => 'nullable|string|max:100',
            'outcome'             => 'nullable|string|max:50',
            'next_action'         => 'nullable|string|max:255',
            'next_action_due_at'  => 'nullable|date',
            'metadata'            => 'nullable|array',
        ]);

        $note = AgentNote::create(array_merge($validated, [
            'agent_type'    => $request->agent_api_key->agent_type ?? null,
            'agent_user_id' => null,
        ]));

        return $this->success($note, 'Note logged', [], 201);
    }

    public function index(string $entityType, int $entityId): JsonResponse
    {
        $notes = AgentNote::forEntity($entityType, $entityId);

        return $this->success($notes);
    }
}
