<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Centralises the Draft -> Submitted -> (Approved | Returned) workflow so
 * status transitions are never performed ad-hoc in a controller. Every
 * transition is wrapped in a transaction and audited.
 */
class DailyReportWorkflowService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function submit(DailyReport $report, User $actor): DailyReport
    {
        abort_unless($report->isEditable(), 422, 'Only draft or returned reports can be submitted.');

        return DB::transaction(function () use ($report, $actor) {
            $oldStatus = $report->status;

            $report->update([
                'status' => 'submitted',
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                // Clear any prior review decision — this is a fresh
                // submission cycle.
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_remarks' => null,
            ]);

            $this->auditLog->log('daily_report.submitted', $report, $actor, ['status' => $oldStatus], ['status' => 'submitted']);

            return $report;
        });
    }

    public function approve(DailyReport $report, User $actor, ?string $remarks = null): DailyReport
    {
        abort_unless($report->status === 'submitted', 422, 'Only submitted reports can be approved.');

        return DB::transaction(function () use ($report, $actor, $remarks) {
            $report->update([
                'status' => 'approved',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);

            $this->auditLog->log(
                'daily_report.approved',
                $report,
                $actor,
                ['status' => 'submitted'],
                ['status' => 'approved', 'reviewed_by' => $actor->id],
            );

            return $report;
        });
    }

    public function returnForCorrection(DailyReport $report, User $actor, string $remarks): DailyReport
    {
        abort_unless($report->status === 'submitted', 422, 'Only submitted reports can be returned.');

        return DB::transaction(function () use ($report, $actor, $remarks) {
            $report->update([
                'status' => 'returned',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);

            $this->auditLog->log(
                'daily_report.returned',
                $report,
                $actor,
                ['status' => 'submitted'],
                ['status' => 'returned', 'reviewed_by' => $actor->id, 'review_remarks' => $remarks],
            );

            return $report;
        });
    }
}
