<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('courier:sync-pathao')->everyThirtyMinutes();
Schedule::command('payroll:generate-monthly-sheets')->monthlyOn(1, '00:00');
