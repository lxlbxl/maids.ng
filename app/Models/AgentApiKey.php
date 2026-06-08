<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $name
 * @property string $key
 * @property array  $scopes
 * @property string $agent_type
 * @property bool   $is_active
 * @property string $last_used_at
 * @property string $expires_at
 */
class AgentApiKey extends Model
{
    protected $fillable = [
        'name', 'key', 'scopes', 'agent_type',
        'is_active', 'last_used_at', 'expires_at',
    ];

    protected $casts = [
        'scopes'   => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at'    => 'datetime',
    ];

    public function hasScope(string $scope): bool
    {
        if (empty($this->scopes)) {
            return true;
        }
        return in_array($scope, $this->scopes, true) || in_array('*', $this->scopes, true);
    }

    public function markUsed(): void
    {
        $this->updateQuietly(['last_used_at' => now()]);
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    public static function findByKey(string $key): ?self
    {
        $hash = hash('sha256', $key);

        return static::where('key', $hash)->first();
    }

    public static function generateKey(string $name, ?string $agentType = null, ?array $scopes = null): self
    {
        $plain = 'mng_sk_'.bin2hex(random_bytes(32));
        $hash  = hash('sha256', $plain);

        $record = static::create([
            'name'       => $name,
            'key'        => $hash,
            'scopes'     => $scopes ?? ['*'],
            'agent_type' => $agentType,
        ]);

        $record->plain_key = $plain;

        return $record;
    }
}
