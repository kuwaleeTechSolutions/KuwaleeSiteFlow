<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\ShareDocumentRequest;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\AuditLogService;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documentService,
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * Lists documents visible to the caller. Confidentiality-tier scoping
     * is applied at the SQL level via DocumentService::scopeVisibleTo() —
     * a document that WOULD be denied by the `view` Policy never appears
     * here, so there's no "visible but inaccessible" inconsistency, and no
     * full-table scan into PHP memory is required to achieve that (brief §32).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);

        $query = Document::query()->with('project', 'site', 'uploader');
        $query = $this->documentService->scopeVisibleTo($query, $request->user());

        $documents = $query
            ->when($request->filled('project_id'), fn ($q) => $q->whereHas('project', fn ($qq) => $qq->where('uuid', $request->input('project_id'))))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return DocumentResource::collection($documents)->additional(['success' => true]);
    }

    public function show(Document $document)
    {
        $this->authorize('view', $document);

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($document->load('project', 'site', 'uploader')),
        ]);
    }

    public function store(StoreDocumentRequest $request)
    {
        $document = $this->documentService->upload($request->file('file'), $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'data' => new DocumentResource($document),
        ], 201);
    }

    /**
     * Secure, policy-gated streaming endpoint — the ONLY way a document's
     * bytes are ever served. Every request re-authorizes (auth + org match
     * + confidentiality tier + documents.download permission) and is
     * audit-logged, satisfying brief §9's "User downloaded confidential
     * document" example.
     */
    public function download(Request $request, Document $document)
    {
        $this->authorize('download', $document);

        abort_unless(Storage::disk($document->disk)->exists($document->disk_path), 404);

        $this->auditLog->log('document.downloaded', $document, $request->user());

        return Storage::disk($document->disk)->response(
            $document->disk_path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type]
        );
    }

    public function share(ShareDocumentRequest $request, Document $document)
    {
        $this->documentService->share($document, $request->validated('user_ids'), $request->user());

        return response()->json(['success' => true, 'message' => 'Document shared successfully.']);
    }

    public function destroy(Request $request, Document $document)
    {
        $this->authorize('delete', $document);

        $this->documentService->delete($document, $request->user());

        return response()->json(['success' => true, 'message' => 'Document deleted successfully.']);
    }
}
