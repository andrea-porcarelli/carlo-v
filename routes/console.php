<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('stock:check-low')->everySixHours();
Schedule::command('db:backup')->cron('0 8,10,12,14,16,18,20,22,0,2,4 * * *');
Schedule::command('mysond:op ricevute')->hourly();
