<?php

namespace App\Http\Requests\Boq;

use App\Models\BoqItem;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class CreateBoqRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()->can('revise', [BoqItem::class, $project]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'effective_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_number' => ['required', 'string', 'max:60'],
            'items.*.description' => ['required', 'string', 'max:2000'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.contract_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.contract_rate' => ['required', 'numeric', 'min:0'],
        ];
    }
}
