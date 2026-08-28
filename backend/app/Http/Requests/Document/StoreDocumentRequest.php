<?php

namespace App\Http\Requests\Document;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->filled('project_id')
            ? Project::find($this->input('project_id'))
            : null;

        // If a project_id was supplied but doesn't resolve, defer to
        // validation (Rule::exists) rather than silently authorizing.
        if ($this->filled('project_id') && ! $project) {
            return true;
        }

        return $this->user()->can('upload', [\App\Models\Document::class, $project]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;
        $allowedMimes = implode(',', config('documents.allowed_mimes'));
        $maxKb = config('documents.max_size_kb');

        return [
            'file' => ['required', 'file', "mimetypes:{$allowedMimes}", "max:{$maxKb}"],
            'project_id' => [
                'nullable', 'integer',
                Rule::exists('projects', 'id')->where('organization_id', $organizationId),
            ],
            'site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')],
            'category' => ['required', Rule::in([
                'contract', 'work_order', 'purchase_order', 'drawing', 'boq_document',
                'invoice', 'bill', 'certificate', 'insurance', 'labour', 'equipment',
                'compliance', 'other',
            ])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'confidentiality_level' => [
                'required', Rule::in(['organization', 'project', 'restricted', 'management_only']),
            ],
            'expiry_date' => ['nullable', 'date', 'after:today'],
        ];
    }
}
