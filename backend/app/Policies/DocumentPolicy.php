<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;

/**
 * Confidentiality-tier authorization per brief §23:
 *   organization       -> any org member with the relevant permission
 *   project            -> additionally requires project/site assignment
 *                          (falls back to org-level if the document has no
 *                          project/site at all — nothing to scope against)
 *   restricted         -> org-wide visibility OR an explicit document_shares
 *                          grant; project assignment ALONE is not enough
 *   management_only    -> org-wide visibility ONLY; an explicit share does
 *                          NOT unlock this tier (brief: "management only")
 *
 * This is the sole authorization boundary for document access — the
 * confidentiality check is evaluated here, in the Policy, so a direct
 * GET /api/documents/{uuid} guess is blocked identically to a list filter.
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->sameOrganization($user, $document)
            && $user->hasPermission('documents.view')
            && $this->hasConfidentialityAccess($user, $document);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->sameOrganization($user, $document)
            && $user->hasPermission('documents.download')
            && $this->hasConfidentialityAccess($user, $document);
    }

    public function upload(User $user, ?Project $project = null): bool
    {
        if (! $user->hasPermission('documents.upload')) {
            return false;
        }

        if ($project === null) {
            return true; // organization-level upload, not tied to a project
        }

        return ! $user->is_super_admin
            && $user->organization_id === $project->organization_id
            && ($user->hasOrgWideVisibility() || $project->isUserAssigned($user->id));
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->sameOrganization($user, $document)
            && $user->hasPermission('documents.delete')
            && ($user->hasOrgWideVisibility() || $document->uploaded_by === $user->id);
    }

    /**
     * You can only share a document you yourself are authorized to view —
     * sharing cannot be used to escalate a user's own reachable-document
     * set beyond what they're already permitted to see.
     */
    public function share(User $user, Document $document): bool
    {
        return $this->view($user, $document) && $user->hasPermission('documents.share');
    }

    private function hasConfidentialityAccess(User $user, Document $document): bool
    {
        return match ($document->confidentiality_level) {
            'organization' => true, // already org-matched by caller
            'project' => $this->hasProjectOrSiteAccess($user, $document),
            'restricted' => $user->hasOrgWideVisibility() || $document->isSharedWith($user->id),
            'management_only' => $user->hasOrgWideVisibility(),
            default => false,
        };
    }

    private function hasProjectOrSiteAccess(User $user, Document $document): bool
    {
        if ($user->hasOrgWideVisibility()) {
            return true;
        }

        if ($document->project_id === null && $document->site_id === null) {
            // 'project' confidentiality but no actual project/site scope —
            // nothing to be assigned to, so fall back to org-level access.
            return true;
        }

        if ($document->project_id && $document->project->isUserAssigned($user->id)) {
            return true;
        }

        if ($document->site_id && ($document->site->isUserAssigned($user->id) || $document->site->project->isUserAssigned($user->id))) {
            return true;
        }

        return $document->isSharedWith($user->id);
    }

    private function sameOrganization(User $user, Document $document): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $document->organization_id;
    }
}
