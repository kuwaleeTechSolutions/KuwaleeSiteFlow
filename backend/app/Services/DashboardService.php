<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\ComplianceItem;
use App\Models\DailyReport;
use App\Models\MaterialStock;
use App\Models\Measurement;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    public function forUser(User $user): array
    {
        if ($user->hasOrgWideVisibility()) {
            return $this->organizationDashboard($user);
        }

        if ($user->roles()->where('slug', 'site_supervisor')->exists()) {
            return $this->siteSupervisorDashboard($user);
        }

        return $this->assignedProjectDashboard($user);
    }

    public function forProject(Project $project): array
    {
        $bills = Bill::where('project_id', $project->id)
            ->where('status', '!=', 'cancelled')
            ->get(['net_payable', 'status']);

        $netPayable = $this->sumDecimal($bills->pluck('net_payable')->all(), 2);
        $paid = $this->sumDecimal(
            Bill::where('project_id', $project->id)
                ->where('status', '!=', 'cancelled')
                ->with('payments:id,bill_id,amount')
                ->get()
                ->flatMap(fn (Bill $bill) => $bill->payments->pluck('amount'))
                ->all(),
            2
        );

        return [
            'project' => [
                'id' => $project->uuid,
                'project_code' => $project->project_code,
                'project_name' => $project->project_name,
                'status' => $project->status,
                'contract_value' => (string) $project->contract_value,
            ],
            'delivery' => [
                'sites' => $project->sites()->count(),
                'daily_reports_total' => DailyReport::where('project_id', $project->id)->count(),
                'daily_reports_pending_review' => DailyReport::where('project_id', $project->id)
                    ->where('status', 'submitted')->count(),
                'measurements_pending_approval' => Measurement::where('project_id', $project->id)
                    ->where('status', 'submitted')->count(),
            ],
            'financial' => [
                'net_payable' => $netPayable,
                'paid_amount' => $paid,
                'outstanding_amount' => bcsub($netPayable, $paid, 2),
                'uncertified_bills' => $bills->whereIn('status', ['draft', 'submitted'])->count(),
            ],
            'risk' => [
                'low_stock_items' => MaterialStock::where('project_id', $project->id)
                    ->whereHas('material', fn (Builder $q) => $q->whereColumn('material_stocks.quantity_on_hand', '<=', 'materials.minimum_stock'))
                    ->count(),
                'compliance_expiring_or_expired' => ComplianceItem::whereIn('status', ['expiring', 'expired'])
                    ->where(function (Builder $q) use ($project) {
                        $q->where(function (Builder $q2) use ($project) {
                            $q2->where('related_entity_type', 'project')
                                ->where('related_entity_id', $project->id);
                        })->orWhereNull('related_entity_type');
                    })->count(),
            ],
        ];
    }

    private function organizationDashboard(User $user): array
    {
        $projects = Project::query()->orderBy('project_name')->get();

        return [
            'dashboard_type' => 'organization',
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'projects_total' => $projects->count(),
                'projects_active' => $projects->where('status', 'active')->count(),
                'pending_daily_report_reviews' => DailyReport::where('status', 'submitted')->count(),
                'pending_measurement_approvals' => Measurement::where('status', 'submitted')->count(),
                'compliance_expiring_or_expired' => ComplianceItem::whereIn('status', ['expiring', 'expired'])->count(),
            ],
            'projects' => $projects->map(fn (Project $project) => $this->forProject($project))->values(),
        ];
    }

    private function assignedProjectDashboard(User $user): array
    {
        $projects = Project::whereHas('assignedUsers', fn (Builder $q) => $q->where('users.id', $user->id))
            ->orderBy('project_name')->get();

        return [
            'dashboard_type' => 'assigned_projects',
            'generated_at' => now()->toIso8601String(),
            'projects' => $projects->map(fn (Project $project) => $this->forProject($project))->values(),
        ];
    }

    private function siteSupervisorDashboard(User $user): array
    {
        $sites = \App\Models\Site::whereHas('assignedUsers', fn (Builder $q) => $q->where('users.id', $user->id))
            ->with('project:id,uuid,project_name')->orderBy('site_name')->get();

        return [
            'dashboard_type' => 'assigned_sites',
            'generated_at' => now()->toIso8601String(),
            'sites' => $sites->map(fn ($site) => [
                'id' => $site->uuid,
                'site_name' => $site->site_name,
                'project' => ['id' => $site->project->uuid, 'project_name' => $site->project->project_name],
                'daily_reports_pending' => DailyReport::where('site_id', $site->id)
                    ->whereIn('status', ['draft', 'returned'])->count(),
                'measurements_pending' => Measurement::where('site_id', $site->id)
                    ->whereIn('status', ['draft', 'rejected'])->count(),
            ])->values(),
        ];
    }

    private function sumDecimal(array $values, int $scale): string
    {
        return array_reduce(
            $values,
            fn (string $carry, $value) => bcadd($carry, (string) $value, $scale),
            '0'
        );
    }
}
