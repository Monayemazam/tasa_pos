<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class AddStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.update') || $this->user()->can('products.create');
    }

    public function rules(): array
    {
        return [
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'qty.required' => 'Quantity is required.',
            'qty.integer' => 'Quantity must be a whole number of pieces.',
            'qty.min' => 'Production quantity must be at least 1.',
            'date.required' => 'Production date is required.',
        ];
    }
}
