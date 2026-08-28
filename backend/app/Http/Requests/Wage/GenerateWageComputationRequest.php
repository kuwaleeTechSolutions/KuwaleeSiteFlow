<?php

namespace App\Http\Requests\Wage;

use App\Models\Project;
use App\Models\WageComputation;
use Illuminate\Foundation\Http\FormRequest;

class GenerateWageComputationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()->can('generateForProject', [WageComputation::class, $project]);
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ];
    }
}
