<?php

namespace App\Console\Commands;

use App\Services\ComplianceAlertService;
use Illuminate\Console\Command;

class ScanComplianceExpiry extends Command
{
    protected $signature = 'compliance:scan-expiry';

    protected $description = 'Scan all compliance items for approaching/passed expiry and send threshold alerts.';

    public function handle(ComplianceAlertService $alertService): int
    {
        $notifiedCount = $alertService->scan();

        $this->info("Compliance expiry scan complete. {$notifiedCount} notification(s) sent.");

        return self::SUCCESS;
    }
}
