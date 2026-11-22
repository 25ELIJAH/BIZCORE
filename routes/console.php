<?php

use Illuminate\Support\Facades\Schedule;

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly database backup (needs the scheduler: `php artisan schedule:work`, or a cron entry running `schedule:run` every minute).
Schedule::command('bizcore:backup')->dailyAt('02:00')->onOneServer();
