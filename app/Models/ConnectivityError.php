<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConnectivityError extends Model
{
    public $timestamps = false;

    protected $fillable = ['http_status', 'endpoint', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
