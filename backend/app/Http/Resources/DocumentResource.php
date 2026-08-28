<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            // disk_path is NEVER included here — access is always via the
            // policy-gated download route below.
            'project_id' => $this->whenLoaded('project', fn () => $this->project?->uuid),
            'site_id' => $this->whenLoaded('site', fn () => $this->site?->uuid),
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'confidentiality_level' => $this->confidentiality_level,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader ? [
                'id' => $this->uploader->uuid, 'name' => $this->uploader->name,
            ] : null),
            'download_url' => route('documents.download', $this->uuid),
            'created_at' => $this->created_at,
        ];
    }
}
