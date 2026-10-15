<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalaryPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('salaryPayment');
        return $payment && ($this->user()?->can('update', $payment) ?? false);
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'required', 'string'],
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'in:salary,advance'],
            'method' => ['nullable', 'string', 'in:cash,bkash,bank_transfer'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
