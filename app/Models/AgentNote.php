<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $entity_type
 * @property int    $entity_id
 * @property string $note
 * @property string $action_taken
 * @property string $outcome
 * @property string $next_action
 * @property string $next_action_due_at
 * @property string $agent_type
 * @property int    $agent_user_id
 * @property array  $metadata
 */
class AgentNote extends Model
{
    protected $fillable = [
        'entity_type', 'entity_id', 'note',
        'action_taken', 'outcome', 'next_action',
        'next_action_due_at', 'agent_type', 'agent_user_id',
        'metadata',
    ];

    protected $casts = [
        'metadata'           => 'array',
        'next_action_due_at' => 'datetime',
    ];

    public static function forEntity(string $entityType, int $entityId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->latest()
            ->get();
    }
}
