<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Boq\CreateBoqRevisionRequest;
use App\Http\Resources\BoqItemResource;
use App\Models\BoqItem;
use App\Models\Project;
use App\Services\BoqItemService;
use Illuminate\Http\Request;

class BoqItemController extends Controller
{
    public function __construct(private readonly BoqItemService $boqService)
    {
    }

    /**
     * Returns the CURRENT EFFECTIVE BOQ for the project — one row per
     * item_number, resolved to its latest revision — annotated with live
     * completed/remaining quantity and value (brief §19).
     */
    public function index(Request $request, Project $project)
    {
        $this->authorize('viewAny', [BoqItem::class, $project]);

        $items = $this->boqService->currentItemsForProject($project);

        return response()->json(['success' => true, 'data' => BoqItemResource::collection($items)]);
    }

    /**
     * Creates a new BOQ revision. This is the ONLY way BOQ item data
     * changes — never a PUT/PATCH on an existing item (BoqItem::updating()
     * throws to guarantee this at the model layer too).
     */
    public function createRevision(CreateBoqRevisionRequest $request, Project $project)
    {
        $revision = $this->boqService->createRevision($project, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => "BOQ revision #{$revision->revision_number} created successfully.",
            'data' => [
                'revision_number' => $revision->revision_number,
                'items' => BoqItemResource::collection($this->boqService->currentItemsForProject($project)),
            ],
        ], 201);
    }
}
