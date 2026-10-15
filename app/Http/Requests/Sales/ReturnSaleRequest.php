<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class ReturnSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.return') || $this->user()?->hasRole('Owner');
    }

    public function rules(): array
    {
        return [
            'return_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:0'],
            'refunded_amount' => ['nullable', 'numeric', 'min:0'],
            'restock' => ['nullable', 'boolean'],
            'return_courier_cost' => ['nullable', 'numeric', 'min:0'],
            'return_courier_payment_method' => ['nullable', 'string', 'in:cash,bkash,bank_transfer'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A return reason is required.',
            'items.required' => 'At least one item must be selected for return.',
            'items.min' => 'At least one item must be selected for return.',
        ];
    }
}
