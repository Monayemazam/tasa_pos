<?php

namespace App\Http\Requests\Payroll;

use App\Models\SalaryMonth;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSalaryMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', SalaryMonth::class) ?? false;
    }

    public function rules(): array
    {
        $isCreating = ! $this->route('salaryMonth');

        return [
            'employee_id' => [$isCreating ? 'required' : 'nullable', 'exists:employees,id'],
            'period' => [$isCreating ? 'required' : 'nullable', 'string'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'deduction' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
