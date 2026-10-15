<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccessoryPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accessories.create') || $this->user()->can('accessories.update');
    }

    public function rules(): array
    {
        return [
            'accessory_item_id' => ['required', 'exists:accessory_items,id'],
            'purchase_date' => ['required', 'date'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'rate' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,bkash,bank_transfer'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'accessory_item_id.required' => 'Accessory item is required.',
            'accessory_item_id.exists' => 'Selected accessory item is invalid.',
            'purchase_date.required' => 'Purchase date is required.',
            'qty.required' => 'Quantity is required.',
            'qty.gt' => 'Quantity must be greater than zero.',
            'rate.required' => 'Rate is required.',
            'rate.min' => 'Rate cannot be negative.',
            'paid_amount.min' => 'Paid amount cannot be negative.',
            'payment_method.in' => 'Payment method must be cash, bkash, or bank_transfer.',
        ];
    }
}
