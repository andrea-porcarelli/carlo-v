<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('stock:check-low')->cron('0 8,14,20 * * *');
Schedule::command('db:backup')->cron('0 8,10,12,14,16,18,20,22,0,2,4 * * *');
Schedule::command('mysond:op ricevute')->hourly();
//Schedule::command('mysond:verifica-ade')->dailyAt('09:00');
Schedule::command('mysond:refresh-sdi')->hourly();
//Schedule::command('ditron:close-day --source=auto')
//    ->dailyAt('23:59')
//    ->withoutOverlapping();
