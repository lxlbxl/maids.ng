<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiAuditLog extends Model
{
    protected $table = 'api_audit_logs';
    protected $guarded = [];

    protected $casts = [
        'request_body' => 'array',
        'response_body' => 'array',
        'created_at' => 'datetime',
    ];
}
