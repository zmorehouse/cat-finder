<?php

use Illuminate\Support\Facades\Schedule;

// Check the RSPCA ACT cat/kitten listings every hour and text on new arrivals.
Schedule::command('app:check-adoptions')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
