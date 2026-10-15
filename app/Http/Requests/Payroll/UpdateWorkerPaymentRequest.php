<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('workerPayment');
        return $payment && ($this->user()?->can('update', $payment) ?? false);
    }

    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string', 'in:cash,bkash,bank_transfer'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
