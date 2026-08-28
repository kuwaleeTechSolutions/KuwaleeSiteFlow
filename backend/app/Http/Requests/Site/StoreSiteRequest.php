<?php

namespace App\Http\Requests\Site;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\Project $project */
        $project = $this->route('project');

        return $this->user()->can('create', [Site::class, $project]);
    }

    public function rules(): array
    {
        /** @var \App\Models\Project $project */
        $project = $this->route('project');
        $organizationId = $this->user()->organization_id;

        return [
            'site_code' => [
                'required', 'string', 'max:60',
                Rule::unique('sites', 'site_code')->where('project_id', $project->id),
            ],
            'site_name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'site_manager_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'status' => [Rule::in(['active', 'inactive', 'completed'])],
        ];
    }
}
