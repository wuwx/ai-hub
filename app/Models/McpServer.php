<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'endpoint',
    'transport',
    'auth_mode',
    'secret_ref',
    'headers',
    'is_active',
    'last_health_status',
    'last_health_checked_at',
])]
class McpServer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'is_active' => 'boolean',
            'last_health_checked_at' => 'datetime',
        ];
    }
}
