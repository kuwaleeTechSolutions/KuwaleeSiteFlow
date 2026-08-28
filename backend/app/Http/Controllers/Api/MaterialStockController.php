<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialStockResource;
use App\Models\MaterialStock;
use App\Models\Project;
use Illuminate\Http\Request;

class MaterialStockController extends Controller
{
    /**
     * Current stock balances for a project — directly answers brief
     * question #3 ("How much material has been received and consumed?")
     * via `quantity_on_hand`, and question #4 partially via the
     * `low_stock_only` filter (full high-consumption alerting is handled
     * by MaterialAlertController).
     */
    public function index(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $stocks = MaterialStock::where('project_id', $project->id)
            ->with('material', 'site')
            ->when($request->filled('site_id'), fn ($q) => $q->whereHas('site', fn ($qq) => $qq->where('uuid', $request->input('site_id'))))
            ->get()
            ->when($request->boolean('low_stock_only'), fn ($collection) => $collection->filter(fn ($stock) => $stock->isLowStock()));

        return response()->json([
            'success' => true,
            'data' => MaterialStockResource::collection($stocks->values()),
        ]);
    }
}
