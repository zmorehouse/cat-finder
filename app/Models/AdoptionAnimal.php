<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdoptionAnimal extends Model
{
    protected $fillable = [
        'external_id',
        'name',
        'type',
        'breed',
        'age',
        'site',
        'status',
        'url',
        'notified',
        'first_seen_at',
    ];

    protected $casts = [
        'notified' => 'boolean',
        'first_seen_at' => 'datetime',
    ];
}
