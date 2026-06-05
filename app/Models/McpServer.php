<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpServer extends Model
{
    protected $table = 'mcp_servers';

    protected $fillable = [
        'name',
        'base_url',
        'auth_token',
        'status',
        'last_ping_at',
    ];

    protected $dates = [
        'last_ping_at',
        'created_at',
        'updated_at',
    ];

    public $timestamps = true;
}
?>
