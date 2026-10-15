<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.update');
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        return [
            'name' => ['required', 'string', 'max:190'],
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($productId)],
            'has_variants' => ['nullable', 'boolean'],
            'variation_type' => ['nullable', 'string', 'max:50'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'category_name' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            // Variant parameters
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
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
            'sku.unique' => 'This SKU is already assigned to another product.',
        ];
    }
}
