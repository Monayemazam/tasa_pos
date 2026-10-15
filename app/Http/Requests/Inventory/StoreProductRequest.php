<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:products,sku', 'unique:product_variants,sku'],
            'has_variants' => ['nullable', 'boolean'],
            'variation_type' => ['nullable', 'string', 'max:50'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'category_name' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'opening_stock' => ['nullable', 'integer', 'min:0'],
            'opening_date' => ['nullable', 'date'],
            'opening_note' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            // Variant parameters
            'variants' => ['nullable', 'array'],
            'variants.*.color_name' => ['nullable', 'string', 'max:100'],
            'variants.*.attribute_value' => ['nullable', 'string', 'max:100'],
            'variants.*.sku' => ['nullable', 'string', 'max:50'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.opening_stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.min_stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'selling_price.min' => 'Selling price cannot be negative.',
            'cost_price.min' => 'Cost price cannot be negative.',
            'min_stock.min' => 'Minimum stock cannot be negative.',
            'opening_stock.min' => 'Opening stock cannot be negative.',
            'sku.unique' => 'This SKU is already assigned to another product.',
        ];
    }
}
