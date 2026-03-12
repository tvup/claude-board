<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'event_name',
        'severity',
        'body',
        'attributes',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TelemetrySession::class, 'session_id', 'session_id');
    }
}
