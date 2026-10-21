<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// purge abandoned chunk uploads every hour (template :25, matching the
// chunk-upload config's unlink-timestamps behaviour)
Schedule::command('uploads:clear')->cron('25 * * * *');
