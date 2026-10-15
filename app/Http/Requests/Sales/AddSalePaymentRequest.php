<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class AddSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.add_payment') || $this->user()?->hasRole('Owner');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:cash,cod,bkash,bank_transfer'],
            'paid_on' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
