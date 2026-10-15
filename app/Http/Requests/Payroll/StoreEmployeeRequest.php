<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Employee::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:100'],
            'monthly_salary' => ['required', 'numeric', 'min:0'],
            'joined_at' => ['nullable', 'date'],
            'left_at' => ['nullable', 'date', 'after_or_equal:joined_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
