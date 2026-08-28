<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('site'));
    }

    public function rules(): array
    {
        /** @var \App\Models\Site $site */
        $site = $this->route('site');
        $organizationId = $this->user()->organization_id;

        return [
            'site_code' => [
                'sometimes', 'required', 'string', 'max:60',
                Rule::unique('sites', 'site_code')
                    ->where('project_id', $site->project_id)
                    ->ignore($site->id),
            ],
            'site_name' => ['sometimes', 'required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'site_manager_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'completed'])],
        ];
    }
}
