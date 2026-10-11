<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('veille:collect --scheduled')->everyMinute()->withoutOverlapping(2);
Schedule::command('veille:prune')->dailyAt('03:00')->timezone('Europe/Paris')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
