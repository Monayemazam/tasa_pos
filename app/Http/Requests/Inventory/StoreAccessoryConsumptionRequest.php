<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccessoryConsumptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accessories.create') || $this->user()->can('accessories.update');
    }

    public function rules(): array
    {
        return [
            'accessory_item_id' => ['required', 'exists:accessory_items,id'],
            'consumption_date' => ['required', 'date'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'accessory_item_id.required' => 'Accessory item is required.',
            'accessory_item_id.exists' => 'Selected accessory item is invalid.',
            'consumption_date.required' => 'Consumption date is required.',
            'qty.required' => 'Quantity is required.',
            'qty.gt' => 'Quantity must be greater than zero.',
        ];
    }
}
