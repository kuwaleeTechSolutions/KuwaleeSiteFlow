<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('share', $this->route('document'));
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }
}
