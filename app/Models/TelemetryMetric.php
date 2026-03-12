<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'metric_name',
        'metric_type',
        'value',
        'unit',
        'attributes',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'double',
            'attributes' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TelemetrySession::class, 'session_id', 'session_id');
    }
}
