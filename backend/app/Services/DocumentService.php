<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Secure document ingestion — generalizes the private-disk + randomized-
 * filename + server-MIME-sniffing pattern established for daily report
 * photos in Phase 4 (see PhotoUploadService) to arbitrary document types.
 */
class DocumentService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function upload(UploadedFile $file, array $data, User $actor): Document
    {
        $allowedMimes = config('documents.allowed_mimes');
        $realMimeType = $file->getMimeType();

        abort_unless(in_array($realMimeType, $allowedMimes, true), 422, 'The uploaded file type is not permitted.');

        $disk = 'private-documents';
        $extension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'bin';
        // Re-derive a safe extension from the sniffed MIME rather than
        // trusting the client's filename extension outright, while still
        // preserving a recognizable suffix for operators browsing storage.
        $safeExtension = $this->safeExtensionFor($realMimeType) ?? preg_replace('/[^a-zA-Z0-9]/', '', $extension);
        $randomizedName = Str::uuid()->toString().'.'.$safeExtension;
        $storagePath = "documents/{$actor->organization_id}/{$randomizedName}";

        Storage::disk($disk)->put($storagePath, file_get_contents($file->getRealPath()));

        return DB::transaction(function () use ($disk, $storagePath, $file, $realMimeType, $data, $actor) {
            $document = Document::create([
                'organization_id' => $actor->organization_id,
                'project_id' => $data['project_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'category' => $data['category'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'confidentiality_level' => $data['confidentiality_level'],
                'disk' => $disk,
                'disk_path' => $storagePath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $realMimeType,
                'size' => $file->getSize(),
                'expiry_date' => $data['expiry_date'] ?? null,
                'uploaded_by' => $actor->id,
            ]);

            $this->auditLog->log('document.uploaded', $document, $actor, null, [
                'title' => $document->title,
                'category' => $document->category,
                'confidentiality_level' => $document->confidentiality_level,
            ]);

            return $document;
        });
    }

    public function share(Document $document, array $userIds, User $actor): void
    {
        $rows = collect($userIds)->mapWithKeys(fn ($id) => [$id => ['shared_by' => $actor->id]]);
        $document->sharedWith()->syncWithoutDetaching($rows->all());

        $this->auditLog->log('document.shared', $document, $actor, null, ['user_ids' => $userIds]);
    }

    /**
     * Applies confidentiality-tier filtering AT THE SQL LEVEL so listing
     * documents never requires loading the full organization's document
     * set into PHP memory just to filter it (brief §32: avoid loading
     * thousands of records unnecessarily). This must stay logically
     * equivalent to DocumentPolicy::hasConfidentialityAccess() — the
     * Policy remains the final authority for any single-document access
     * (view/download), this is only a list-scoping optimization.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasOrgWideVisibility()) {
            return $query; // no confidentiality restriction needed
        }

        $assignedProjectIds = Project::whereHas('assignedUsers', fn ($q) => $q->where('users.id', $user->id))->pluck('id');
        $assignedSiteIds = Site::whereHas('assignedUsers', fn ($q) => $q->where('users.id', $user->id))->pluck('id');
        $sharedDocumentIds = DB::table('document_shares')->where('user_id', $user->id)->pluck('document_id');

        return $query->where(function (Builder $q) use ($assignedProjectIds, $assignedSiteIds, $sharedDocumentIds) {
            $q->where('confidentiality_level', 'organization')
                ->orWhere(function (Builder $q2) use ($assignedProjectIds, $assignedSiteIds) {
                    $q2->where('confidentiality_level', 'project')
                        ->where(function (Builder $q3) use ($assignedProjectIds, $assignedSiteIds) {
                            $q3->whereNull('project_id')->whereNull('site_id')
                                ->orWhereIn('project_id', $assignedProjectIds)
                                ->orWhereIn('site_id', $assignedSiteIds);
                        });
                })
                ->orWhere(function (Builder $q2) use ($sharedDocumentIds) {
                    $q2->where('confidentiality_level', 'restricted')
                        ->whereIn('id', $sharedDocumentIds);
                });
            // 'management_only' is deliberately excluded entirely for
            // non-org-wide users — no combination of project assignment or
            // explicit share unlocks this tier (see DocumentPolicy).
        });
    }

    public function delete(Document $document, User $actor): void
    {
        Storage::disk($document->disk)->delete($document->disk_path);
        $this->auditLog->log('document.deleted', $document, $actor);
        $document->delete();
    }

    private function safeExtensionFor(string $mimeType): ?string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            default => null,
        };
    }
}
