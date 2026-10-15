<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeatherItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leather.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'colour_grade' => ['nullable', 'string', 'max:100'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Leather item name is required.',
            'min_stock.min' => 'Minimum stock cannot be negative.',
        ];
    }
}
