<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EquipmentUsageLog extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id', 'equipment_id', 'project_id', 'site_id',
        'usage_date', 'hours_used', 'operator_id', 'remarks', 'created_by',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'hours_used' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (EquipmentUsageLog $log) {
            $log->uuid ??= (string) Str::uuid();

            if ($log->site_id && $log->project_id) {
                $site = Site::withoutGlobalScopes()->find($log->site_id);
                abort_unless(
                    $site && (int) $site->project_id === (int) $log->project_id,
                    422,
                    'The selected site does not belong to the selected project.'
                );
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
