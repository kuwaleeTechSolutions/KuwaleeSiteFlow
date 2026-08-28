<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wage\GenerateWageComputationRequest;
use App\Http\Resources\WageComputationResource;
use App\Models\Project;
use App\Models\WageComputation;
use App\Services\WageCalculationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class WageComputationController extends Controller
{
    public function __construct(private readonly WageCalculationService $wageService)
    {
    }

    public function index(Request $request, Project $project)
    {
        $this->authorize('viewAny', WageComputation::class);
        $this->authorize('generateForProject', [WageComputation::class, $project]); // reuses the same project-access gate for reads

        $computations = $project->wageComputations()
            ->with('worker:id,uuid,name')
            ->when($request->filled('period_start'), fn ($q) => $q->where('period_start', '>=', $request->input('period_start')))
            ->when($request->filled('period_end'), fn ($q) => $q->where('period_end', '<=', $request->input('period_end')))
            ->orderByDesc('generated_at')
            ->paginate($request->integer('per_page', 50));

        return WageComputationResource::collection($computations)->additional(['success' => true]);
    }

    /**
     * Aggregate labour-cost summary for the project — directly answers
     * brief question #6 ("What is the labour cost of each project?").
     * Sums the MOST RECENT computation per worker within the requested
     * range rather than every historical run, to avoid double-counting
     * superseded calculations.
     */
    public function summary(Request $request, Project $project)
    {
        $this->authorize('generateForProject', [WageComputation::class, $project]);

        $latestPerWorker = $project->wageComputations()
            ->selectRaw('worker_id, MAX(id) as latest_id')
            ->when($request->filled('period_start'), fn ($q) => $q->where('period_start', '>=', $request->input('period_start')))
            ->when($request->filled('period_end'), fn ($q) => $q->where('period_end', '<=', $request->input('period_end')))
            ->groupBy('worker_id')
            ->pluck('latest_id');

        $computations = WageComputation::whereIn('id', $latestPerWorker)->get();

        // Aggregated with BCMath (not Collection::sum(), which coerces to
        // native float) to preserve exact decimal precision all the way
        // through to the summary figure, consistent with how each
        // individual computation was generated.
        $totals = $computations->reduce(function (array $carry, WageComputation $c) {
            return [
                'days_present' => bcadd($carry['days_present'], (string) $c->days_present, 2),
                'base_wage_total' => bcadd($carry['base_wage_total'], (string) $c->base_wage_total, 2),
                'overtime_total' => bcadd($carry['overtime_total'], (string) $c->overtime_total, 2),
                'gross_total' => bcadd($carry['gross_total'], (string) $c->gross_total, 2),
            ];
        }, ['days_present' => '0', 'base_wage_total' => '0', 'overtime_total' => '0', 'gross_total' => '0']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_workers' => $computations->count(),
                'total_days_present' => $totals['days_present'],
                'total_base_wage' => $totals['base_wage_total'],
                'total_overtime' => $totals['overtime_total'],
                'total_labour_cost' => $totals['gross_total'],
            ],
        ]);
    }

    public function generate(GenerateWageComputationRequest $request, Project $project)
    {
        $computations = $this->wageService->computeForProject(
            $project,
            $request->validated('period_start'),
            $request->validated('period_end'),
            $request->user(),
        );

        $collection = (new Collection($computations))->load('worker');

        return response()->json([
            'success' => true,
            'message' => count($computations).' wage computations generated.',
            'data' => WageComputationResource::collection($collection),
        ], 201);
    }
}
