<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeatherConsumptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leather.create') || $this->user()->can('leather.update');
    }

    public function rules(): array
    {
        return [
            'leather_item_id' => ['required', 'exists:leather_items,id'],
            'consumption_date' => ['required', 'date'],
            'qty_sqft' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'leather_item_id.required' => 'Leather item is required.',
            'consumption_date.required' => 'Consumption date is required.',
            'qty_sqft.required' => 'Quantity in sq ft is required.',
            'qty_sqft.gt' => 'Quantity must be greater than zero.',
        ];
    }
}
