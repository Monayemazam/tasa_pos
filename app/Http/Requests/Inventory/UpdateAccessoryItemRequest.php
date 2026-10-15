<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccessoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accessories.update');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'string', 'max:20'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Accessory item name is required.',
            'unit.required' => 'Unit of measure is required.',
            'min_stock.min' => 'Minimum stock cannot be negative.',
        ];
    }
}
