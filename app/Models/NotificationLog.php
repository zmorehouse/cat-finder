<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'channel',
        'recipient',
        'status',
        'body',
        'provider_sid',
        'error',
        'animal_count',
        'animals',
    ];

    protected $casts = [
        'animals' => 'array',
    ];
}
