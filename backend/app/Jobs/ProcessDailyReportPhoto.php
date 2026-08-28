<?php

namespace App\Jobs;

use App\Models\DailyReportPhoto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Re-encodes an uploaded photo to strip EXIF metadata (which commonly
 * embeds GPS coordinates and device identifiers — a privacy/security
 * concern per brief §14). Runs asynchronously via the queue so the upload
 * request is never blocked on image processing.
 *
 * Uses PHP's built-in GD extension (re-encode = automatic metadata strip)
 * rather than a hard dependency on a third-party image library, so this
 * degrades gracefully (logs a warning, leaves the original file untouched
 * with exif_stripped=false) in an environment where GD is unavailable,
 * instead of failing the whole upload.
 */
class ProcessDailyReportPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $photoId)
    {
    }

    public function handle(): void
    {
        $photo = DailyReportPhoto::find($this->photoId);

        if (! $photo) {
            return; // photo was deleted before the job ran
        }

        if (! extension_loaded('gd')) {
            Log::warning('GD extension unavailable — skipping EXIF strip for photo.', ['photo_id' => $photo->id]);
            return;
        }

        $disk = Storage::disk($photo->disk);
        $contents = $disk->get($photo->disk_path);
        $tmpPath = tempnam(sys_get_temp_dir(), 'kwl_photo_');
        file_put_contents($tmpPath, $contents);

        try {
            $image = match ($photo->mime_type) {
                'image/jpeg' => @imagecreatefromjpeg($tmpPath),
                'image/png' => @imagecreatefrompng($tmpPath),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : null,
                default => null, // e.g. HEIC — not GD-decodable; left as-is
            };

            if (! $image) {
                Log::info('Skipping EXIF strip: unsupported or undecodable format.', ['photo_id' => $photo->id]);
                return;
            }

            $reencodedPath = $tmpPath.'_clean';
            $success = match ($photo->mime_type) {
                'image/jpeg' => imagejpeg($image, $reencodedPath, 90),
                'image/png' => imagepng($image, $reencodedPath),
                'image/webp' => function_exists('imagewebp') ? imagewebp($image, $reencodedPath) : false,
                default => false,
            };
            imagedestroy($image);

            if ($success) {
                // Re-encoding via GD does not carry over the source EXIF
                // segment, so the resulting file has no GPS/device metadata.
                $disk->put($photo->disk_path, file_get_contents($reencodedPath));
                $photo->update(['exif_stripped' => true]);
                @unlink($reencodedPath);
            }
        } finally {
            @unlink($tmpPath);
        }
    }
}
