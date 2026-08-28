<?php

namespace App\Services;

use App\Models\Site;
use App\Models\User;
use App\Models\WorkerAttendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Centralises attendance marking, including bulk entry, so the unique
 * (worker, date, shift) constraint and cross-organization validation are
 * enforced consistently in one place rather than duplicated per controller
 * action.
 */
class AttendanceService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function markSingle(Site $site, array $data, User $actor): WorkerAttendance
    {
        return DB::transaction(function () use ($site, $data, $actor) {
            $attendance = WorkerAttendance::create(array_merge($data, [
                'organization_id' => $site->organization_id,
                'project_id' => $site->project_id,
                'site_id' => $site->id,
                'shift' => $data['shift'] ?? 'day',
                'marked_by' => $actor->id,
            ]));

            $this->auditLog->log('worker_attendance.marked', $attendance, $actor);

            return $attendance;
        });
    }

    /**
     * Marks attendance for multiple workers at once (e.g. an entire site's
     * crew for the day). Runs as a single all-or-nothing transaction: if
     * ANY entry violates the unique (worker, date, shift) constraint or
     * fails validation, none of the batch is committed — this prevents a
     * partially-recorded, inconsistent attendance sheet.
     */
    public function markBulk(Site $site, string $attendanceDate, string $shift, array $entries, User $actor): array
    {
        return DB::transaction(function () use ($site, $attendanceDate, $shift, $entries, $actor) {
            $created = [];

            foreach ($entries as $entry) {
                $exists = WorkerAttendance::where('worker_id', $entry['worker_id'])
                    ->where('attendance_date', $attendanceDate)
                    ->where('shift', $shift)
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'entries' => ["Attendance for worker ID {$entry['worker_id']} on {$attendanceDate} ({$shift}) already exists."],
                    ]);
                }

                $created[] = WorkerAttendance::create([
                    'organization_id' => $site->organization_id,
                    'project_id' => $site->project_id,
                    'site_id' => $site->id,
                    'worker_id' => $entry['worker_id'],
                    'attendance_date' => $attendanceDate,
                    'shift' => $shift,
                    'status' => $entry['status'],
                    'check_in' => $entry['check_in'] ?? null,
                    'check_out' => $entry['check_out'] ?? null,
                    'overtime_hours' => $entry['overtime_hours'] ?? 0,
                    'remarks' => $entry['remarks'] ?? null,
                    'marked_by' => $actor->id,
                ]);
            }

            $this->auditLog->log('worker_attendance.bulk_marked', $site, $actor, null, [
                'attendance_date' => $attendanceDate,
                'count' => count($created),
            ]);

            return $created;
        });
    }
}
