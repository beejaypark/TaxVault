<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:send-emails')
    ->dailyAt('08:00')
    ->timezone(config('notifications.timezone'))
    ->withoutOverlapping();

Schedule::command('tax-news:sync')
    ->dailyAt('07:30')
    ->timezone(config('notifications.timezone'))
    ->withoutOverlapping();

Schedule::command('income:generate-recurring')
    ->dailyAt('00:10')
    ->timezone(config('notifications.timezone'))
    ->withoutOverlapping();
