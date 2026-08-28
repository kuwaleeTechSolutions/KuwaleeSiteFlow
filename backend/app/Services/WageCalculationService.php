<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Models\WageComputation;
use App\Models\WorkerAttendance;
use Illuminate\Support\Facades\DB;

/**
 * Computes wage totals for every worker who has attendance under a given
 * project within a date range. All monetary math uses BCMath (arbitrary
 * precision decimal strings), NEVER native float arithmetic, to avoid
 * IEEE-754 rounding drift in payroll figures (brief §21/§28).
 *
 * Each run inserts new WageComputation rows rather than upserting, so a
 * prior computation remains available for audit even after attendance is
 * corrected and wages are regenerated for the same period.
 */
class WageCalculationService
{
    private const SCALE = 2;

    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function computeForProject(Project $project, string $periodStart, string $periodEnd, User $actor): array
    {
        // Standard hours/day and overtime multiplier are organization-
        // configurable (falls back to sane defaults) rather than hardcoded,
        // since wage policies vary by contractor.
        $standardHoursPerDay = (string) ($project->organization?->setting('standard_hours_per_day', 8) ?? 8);
        $overtimeMultiplier = (string) ($project->organization?->setting('overtime_multiplier', 1.5) ?? 1.5);

        $workerIds = WorkerAttendance::where('project_id', $project->id)
            ->whereBetween('attendance_date', [$periodStart, $periodEnd])
            ->distinct()
            ->pluck('worker_id');

        return DB::transaction(function () use ($project, $periodStart, $periodEnd, $workerIds, $standardHoursPerDay, $overtimeMultiplier, $actor) {
            $computations = [];

            foreach ($workerIds as $workerId) {
                $attendanceRows = WorkerAttendance::where('project_id', $project->id)
                    ->where('worker_id', $workerId)
                    ->whereBetween('attendance_date', [$periodStart, $periodEnd])
                    ->get();

                $worker = $attendanceRows->first()->worker;
                $dailyWage = (string) $worker->daily_wage;

                // Days present: full day = 1.0, half day = 0.5, absent = 0.
                $daysPresent = '0';
                $overtimeHours = '0';

                foreach ($attendanceRows as $row) {
                    $dayValue = match ($row->status) {
                        'present' => '1',
                        'half_day' => '0.5',
                        default => '0',
                    };
                    $daysPresent = bcadd($daysPresent, $dayValue, self::SCALE);
                    $overtimeHours = bcadd($overtimeHours, (string) $row->overtime_hours, self::SCALE);
                }

                $baseWageTotal = bcmul($dailyWage, $daysPresent, self::SCALE);

                // Overtime rate = (daily_wage / standard_hours_per_day) * multiplier
                $hourlyRate = bcdiv($dailyWage, $standardHoursPerDay, 4);
                $overtimeRate = bcmul($hourlyRate, $overtimeMultiplier, 4);
                $overtimeTotal = bcmul($overtimeRate, $overtimeHours, self::SCALE);

                $grossTotal = bcadd($baseWageTotal, $overtimeTotal, self::SCALE);

                $computations[] = WageComputation::create([
                    'organization_id' => $project->organization_id,
                    'worker_id' => $workerId,
                    'project_id' => $project->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'days_present' => $daysPresent,
                    'overtime_hours' => $overtimeHours,
                    'base_wage_total' => $baseWageTotal,
                    'overtime_total' => $overtimeTotal,
                    'gross_total' => $grossTotal,
                    'generated_by' => $actor->id,
                    'generated_at' => now(),
                ]);
            }

            $this->auditLog->log('wage_computation.generated', $project, $actor, null, [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'worker_count' => count($computations),
            ]);

            return $computations;
        });
    }
}
