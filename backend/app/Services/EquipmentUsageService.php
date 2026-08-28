<?php

namespace App\Services;

use App\Models\EquipmentUsageLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EquipmentUsageService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function logUsage(Site $site, array $data, User $actor): EquipmentUsageLog
    {
        return DB::transaction(function () use ($site, $data, $actor) {
            $log = EquipmentUsageLog::create(array_merge($data, [
                'organization_id' => $site->organization_id,
                'project_id' => $site->project_id,
                'site_id' => $site->id,
                'created_by' => $actor->id,
            ]));

            $this->auditLog->log('equipment_usage_log.created', $log, $actor);

            return $log;
        });
    }
}
