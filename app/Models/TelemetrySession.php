<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelemetrySession extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'session_group_id',
        'user_email',
        'user_id',
        'account_uuid',
        'organization_id',
        'app_version',
        'terminal_type',
        'project_name',
        'billing_model',
        'hostname',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(TelemetryMetric::class, 'session_id', 'session_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TelemetryEvent::class, 'session_id', 'session_id');
    }

    public function groupedSessions(): HasMany
    {
        return $this->hasMany(self::class, 'session_group_id', 'session_group_id');
    }
}
