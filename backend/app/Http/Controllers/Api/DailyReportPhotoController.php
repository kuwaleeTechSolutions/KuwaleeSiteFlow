<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReport\UploadDailyReportPhotoRequest;
use App\Http\Resources\DailyReportPhotoResource;
use App\Models\DailyReport;
use App\Models\DailyReportPhoto;
use App\Services\PhotoUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DailyReportPhotoController extends Controller
{
    public function __construct(private readonly PhotoUploadService $photoUploadService)
    {
    }

    public function store(UploadDailyReportPhotoRequest $request, DailyReport $dailyReport)
    {
        $photo = $this->photoUploadService->upload(
            $dailyReport,
            $request->file('photo'),
            $request->user(),
            $request->validated('caption'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Photo uploaded successfully.',
            'data' => new DailyReportPhotoResource($photo),
        ], 201);
    }

    /**
     * Secure, policy-gated file streaming endpoint. This is the ONLY way a
     * photo's bytes are ever served — never a public/predictable URL.
     * Mirrors the document-download pattern that will be generalised for
     * the full Document vault in Phase 9 (brief §6).
     */
    public function download(Request $request, DailyReportPhoto $dailyReportPhoto)
    {
        $this->authorize('view', $dailyReportPhoto);

        abort_unless(Storage::disk($dailyReportPhoto->disk)->exists($dailyReportPhoto->disk_path), 404);

        app(\App\Services\AuditLogService::class)->log(
            'daily_report_photo.downloaded',
            $dailyReportPhoto,
            $request->user(),
        );

        return Storage::disk($dailyReportPhoto->disk)->response(
            $dailyReportPhoto->disk_path,
            $dailyReportPhoto->original_filename,
            ['Content-Type' => $dailyReportPhoto->mime_type]
        );
    }

    public function destroy(Request $request, DailyReportPhoto $dailyReportPhoto)
    {
        $this->authorize('delete', $dailyReportPhoto);

        $this->photoUploadService->delete($dailyReportPhoto, $request->user());

        return response()->json(['success' => true, 'message' => 'Photo deleted successfully.']);
    }
}
