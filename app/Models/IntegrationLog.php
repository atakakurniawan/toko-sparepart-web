<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IntegrationLog extends Model
{
    public const UPDATED_AT = null; // log tidak pernah diubah

    protected $fillable = [
        'service', 'method', 'endpoint', 'http_status', 'error_code',
        'duration_ms', 'request_summary', 'response_summary',
        'loggable_type', 'loggable_id',
    ];

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}
