<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Historical, generated-only record. Each run of WageCalculationService
 * inserts a NEW row per worker/project/period rather than overwriting a
 * prior computation — so if attendance is later corrected and wages are
 * recomputed, the earlier calculation remains available for audit ("what
 * did we think the labour cost was as of last Friday's payroll run?").
 */
class WageComputation extends Model
{
    use BelongsToOrganization;

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'worker_id', 'project_id', 'period_start',
        'period_end', 'days_present', 'overtime_hours', 'base_wage_total',
        'overtime_total', 'gross_total', 'generated_by', 'generated_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'days_present' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'base_wage_total' => 'decimal:2',
        'overtime_total' => 'decimal:2',
        'gross_total' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WageComputation $computation) {
            $computation->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Append-only by convention — no update() guard is enforced at the DB
     * layer here (unlike AuditLog) since a future correction workflow may
     * legitimately need to mark a computation as superseded; but no
     * controller or service in this codebase currently mutates an existing
     * row, only creates new ones.
     */
}
