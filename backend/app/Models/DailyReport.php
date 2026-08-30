<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DailyReport extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'project_id', 'site_id', 'report_date', 'weather',
        'work_activities', 'work_completed', 'quantity_completed', 'unit',
        'manpower_deployed', 'equipment_used', 'material_used',
        'problems_delays', 'reason_for_delay', 'safety_incidents',
        'tomorrow_plan', 'remarks', 'created_by',
        // Workflow-controlled fields. They are only mutated by
        // DailyReportWorkflowService after policy authorization; omitting
        // them from fillable silently prevented submitted/approved/returned
        // transitions from being persisted.
        'status', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_remarks',
    ];

    protected $casts = [
        'report_date' => 'date',
        'quantity_completed' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DailyReport $report) {
            $report->uuid ??= (string) Str::uuid();
            $report->status ??= 'draft';

            // The report's organization_id/project relationship must be
            // internally consistent: the site must actually belong to the
            // stated project, and both must belong to the same org as the
            // authenticated user (already stamped by BelongsToOrganization).
            if ($report->site_id) {
                $site = Site::withoutGlobalScopes()->find($report->site_id);
                abort_if(! $site, 422, 'The selected site does not exist.');
                abort_unless(
                    (int) $site->organization_id === (int) $report->organization_id,
                    403,
                    'Site does not belong to your organization.'
                );

                if ($report->project_id) {
                    abort_unless(
                        (int) $site->project_id === (int) $report->project_id,
                        422,
                        'The selected site does not belong to the selected project.'
                    );
                } else {
                    $report->project_id = $site->project_id;
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(DailyReportPhoto::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'returned'], true);
    }
}
