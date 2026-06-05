<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPromptTemplate extends Model
{
    protected $fillable = [
        'agent_name',
        'tier',
        'label',
        'system_prompt',
        'version',
        'is_active',
        'updated_by',
        'previous_prompt',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAgent($query, string $agentName)
    {
        return $query->where('agent_name', $agentName);
    }

    public function scopeForTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    /**
     * Deactivate all other templates for the same agent+tier
     * and activate this one.
     */
    public function makeActiveExclusive(): void
    {
        static::where('agent_name', $this->agent_name)
            ->where('tier', $this->tier)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        $this->update(['is_active' => true]);
    }

    /**
     * Save a new version of an existing template.
     * Archives current prompt to previous_prompt before overwriting.
     */
    public function saveNewVersion(string $newPrompt, int $editorId): void
    {
        $this->update([
            'previous_prompt' => $this->system_prompt,
            'system_prompt' => $newPrompt,
            'version' => $this->version + 1,
            'updated_by' => $editorId,
            'is_active' => true,
        ]);
    }

    /**
     * Roll back to the previous prompt version.
     */
    public function rollback(): void
    {
        if (empty($this->previous_prompt)) {
            throw new \RuntimeException('No previous version to roll back to.');
        }

        $current = $this->system_prompt;

        $this->update([
            'system_prompt' => $this->previous_prompt,
            'previous_prompt' => $current,
            'version' => $this->version - 1,
        ]);
    }
}