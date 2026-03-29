<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send scheduled push notifications at 8 AM IST daily
Schedule::command('notifications:send')
    ->dailyAt('08:00')
    ->timezone('Asia/Kolkata');
