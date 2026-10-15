<?php

namespace App\Http\Requests\Payroll;

use App\Models\SalaryPayment;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SalaryPayment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'period' => ['required', 'string'],
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'in:salary,advance'],
            'method' => ['nullable', 'string', 'in:cash,bkash,bank_transfer'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
