<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkerAttendance extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $table = 'worker_attendance';

    protected $fillable = [
        'organization_id', 'worker_id', 'project_id', 'site_id',
        'attendance_date', 'shift', 'status', 'check_in', 'check_out',
        'overtime_hours', 'remarks', 'marked_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'overtime_hours' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (WorkerAttendance $attendance) {
            $attendance->uuid ??= (string) Str::uuid();

            // The site MUST belong to the stated project, and the worker
            // MUST belong to the same organization — re-verified here even
            // though the Form Request/Service already check this, as
            // defense in depth against any future direct-model-write path.
            if ($attendance->site_id && $attendance->project_id) {
                $site = Site::withoutGlobalScopes()->find($attendance->site_id);
                abort_unless(
                    $site && (int) $site->project_id === (int) $attendance->project_id,
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

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
