<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjustments.create');
    }

    protected function prepareForValidation(): void
    {
        if ($product = $this->route('product')) {
            $this->merge([
                'item_type' => 'product',
                'item_id' => is_object($product) ? $product->id : (int) $product,
            ]);
        }
    }

    public function rules(): array
    {
        $hasProductRoute = (bool) $this->route('product');

        return [
            'item_type' => [$hasProductRoute ? 'nullable' : 'required', 'string', 'in:product,leather,accessory'],
            'item_id' => [$hasProductRoute ? 'nullable' : 'required', 'integer'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'qty' => ['required', 'numeric', 'not_in:0'],
            'adjusted_on' => ['required_without:date', 'nullable', 'date'],
            'date' => ['required_without:adjusted_on', 'nullable', 'date'],
            'reason' => ['required', 'string', 'in:damaged,lost,count_correction,other'],
            'note' => ['nullable', 'string', 'max:255', 'required_if:reason,other'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_type.required' => 'Item type is required.',
            'item_type.in' => 'Item type must be product, leather, or accessory.',
            'item_id.required' => 'An item must be selected.',
            'qty.required' => 'Adjustment quantity is required.',
            'qty.not_in' => 'Adjustment quantity cannot be 0.',
            'reason.required' => 'Adjustment reason is required.',
            'reason.in' => 'Reason must be damaged, lost, count_correction, or other.',
            'note.required_if' => 'A note is required when reason is "Other".',
        ];
    }
}
