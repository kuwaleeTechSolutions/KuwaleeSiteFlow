<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyReportPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'caption' => $this->caption,
            // A route, never the raw storage path — the download endpoint
            // itself re-authorizes on every request (see
            // DailyReportPhotoController::download).
            'download_url' => route('daily-report-photos.download', $this->uuid),
            'uploaded_at' => $this->created_at,
        ];
    }
}
