<?php

namespace App\Http\Requests\Payroll;

use App\Models\SalaryMonth;
use Illuminate\Foundation\Http\FormRequest;

class GenerateMonthlySheetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SalaryMonth::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string'],
        ];
    }
}
