<?php

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('worker'));
    }

    public function rules(): array
    {
        /** @var \App\Models\Worker $worker */
        $worker = $this->route('worker');
        $organizationId = $this->user()->organization_id;

        return [
            'worker_code' => [
                'sometimes', 'required', 'string', 'max:60',
                Rule::unique('workers', 'worker_code')
                    ->where('organization_id', $organizationId)
                    ->ignore($worker->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'trade' => ['nullable', 'string', 'max:100'],
            'skill_category' => ['nullable', 'string', 'max:100'],
            'daily_wage' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'],
            'joining_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
