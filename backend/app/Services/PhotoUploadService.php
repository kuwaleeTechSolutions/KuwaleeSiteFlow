<?php

namespace App\Services;

use App\Jobs\ProcessDailyReportPhoto;
use App\Models\DailyReport;
use App\Models\DailyReportPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles secure ingestion of daily-report photos.
 *
 * Defense in depth against malicious/mislabeled uploads:
 *  1. Form Request validates via Laravel's `mimetypes` rule (server-sniffed
 *     via finfo, not the client's extension/Content-Type).
 *  2. This service independently re-derives the MIME type from the file's
 *     actual bytes and re-checks it against the allowlist before storing.
 *  3. The file is stored under a RANDOMIZED name on the PRIVATE disk —
 *     the original filename is preserved only as display metadata.
 *  4. A queued job re-encodes the image (stripping EXIF/GPS metadata)
 *     asynchronously so the request isn't blocked on image processing.
 */
class PhotoUploadService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function upload(DailyReport $report, UploadedFile $file, User $actor, ?string $caption = null): DailyReportPhoto
    {
        $allowedMimes = config('daily_reports.allowed_photo_mimes');
        $realMimeType = $file->getMimeType(); // Symfony's finfo-backed sniff

        abort_unless(
            in_array($realMimeType, $allowedMimes, true),
            422,
            'The uploaded file type is not permitted.'
        );

        abort_if(
            $report->photos()->count() >= config('daily_reports.max_photos_per_report'),
            422,
            'Maximum number of photos for this report has been reached.'
        );

        $disk = 'private-documents';
        $extension = $this->safeExtensionFor($realMimeType);
        $randomizedName = Str::uuid()->toString().'.'.$extension;
        $storagePath = "daily-reports/{$report->id}/{$randomizedName}";

        // Store the raw stream directly — we never trust or reuse the
        // client's original filename/extension for the stored path.
        Storage::disk($disk)->put($storagePath, file_get_contents($file->getRealPath()));

        $photo = DB::transaction(function () use ($report, $disk, $storagePath, $file, $realMimeType, $caption, $actor) {
            $photo = DailyReportPhoto::create([
                'organization_id' => $report->organization_id,
                'daily_report_id' => $report->id,
                'disk' => $disk,
                'disk_path' => $storagePath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $realMimeType,
                'size' => $file->getSize(),
                'caption' => $caption,
                'exif_stripped' => false,
                'uploaded_by' => $actor->id,
            ]);

            $this->auditLog->log('daily_report_photo.uploaded', $photo, $actor, null, [
                'daily_report_id' => $report->id,
                'mime_type' => $realMimeType,
            ]);

            return $photo;
        });

        // Queued: re-encode the image to strip EXIF/GPS metadata without
        // blocking the upload response.
        ProcessDailyReportPhoto::dispatch($photo->id);

        return $photo;
    }

    public function delete(DailyReportPhoto $photo, User $actor): void
    {
        Storage::disk($photo->disk)->delete($photo->disk_path);
        $this->auditLog->log('daily_report_photo.deleted', $photo, $actor);
        $photo->delete();
    }

    private function safeExtensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            default => 'bin',
        };
    }
}
