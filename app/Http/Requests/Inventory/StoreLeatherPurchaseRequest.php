<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeatherPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leather.create') || $this->user()->can('leather.update');
    }

    public function rules(): array
    {
        return [
            'leather_item_id' => ['required', 'exists:leather_items,id'],
            'purchase_date' => ['required', 'date'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'qty_sqft' => ['required', 'numeric', 'gt:0'],
            'rate_per_sqft' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,bkash,bank_transfer'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'leather_item_id.required' => 'Leather item is required.',
            'purchase_date.required' => 'Purchase date is required.',
            'qty_sqft.required' => 'Quantity in sq ft is required.',
            'qty_sqft.gt' => 'Quantity must be greater than zero.',
            'rate_per_sqft.required' => 'Rate per sq ft is required.',
            'rate_per_sqft.min' => 'Rate per sq ft cannot be negative.',
            'paid_amount.min' => 'Paid amount cannot be negative.',
        ];
    }
}
