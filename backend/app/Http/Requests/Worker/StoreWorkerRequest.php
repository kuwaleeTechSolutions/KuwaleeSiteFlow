<?php

namespace App\Http\Requests\Worker;

use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Worker::class);
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'worker_code' => [
                'required', 'string', 'max:60',
                Rule::unique('workers', 'worker_code')->where('organization_id', $organizationId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'trade' => ['nullable', 'string', 'max:100'],
            'skill_category' => ['nullable', 'string', 'max:100'],
            'daily_wage' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'joining_date' => ['nullable', 'date'],
            'status' => [Rule::in(['active', 'inactive'])],
        ];
    }
}
