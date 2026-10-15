<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $worker = $this->route('worker');
        return $worker && ($this->user()?->can('update', $worker) ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['nullable', 'string', 'max:100'],
            'wage_basis' => ['required', 'string', 'in:per_day,per_piece,fixed_weekly'],
            'rate' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
