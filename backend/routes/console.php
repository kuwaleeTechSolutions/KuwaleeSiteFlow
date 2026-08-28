<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily compliance expiry scan — flags insurance/licences/certificates
// approaching or past expiry and sends threshold alerts (60/30/15/7/expired
// days) per brief §24. Run early morning so alerts are ready when staff
// start their day (organization timezone is Asia/Kolkata for the pilot).
Schedule::command('compliance:scan-expiry')->dailyAt('06:00');

