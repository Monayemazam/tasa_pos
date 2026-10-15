<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.create') || $this->user()?->hasRole('Owner');
    }

    public function rules(): array
    {
        return [
            'idempotency_token' => ['required', 'string', 'max:36'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'customer_alt_phone' => ['nullable', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'customer_address' => ['required', 'string', 'min:10', 'max:500'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
            'courier_mode' => ['required', 'string', 'in:pathao,none'],
            'city_id' => ['required_if:courier_mode,pathao', 'nullable', 'integer'],
            'zone_id' => ['required_if:courier_mode,pathao', 'nullable', 'integer'],
            'area_id' => ['required_if:courier_mode,pathao', 'nullable', 'integer'],
            'city_name' => ['nullable', 'string', 'max:100'],
            'zone_name' => ['nullable', 'string', 'max:100'],
            'area_name' => ['nullable', 'string', 'max:100'],
            'item_weight' => ['required_if:courier_mode,pathao', 'nullable', 'numeric', 'min:0.01'],
            'delivery_type' => ['nullable', 'integer', 'in:48,12'],
            'special_instruction' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.is_custom' => ['nullable', 'boolean'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.variant_name' => ['nullable', 'string', 'max:100'],
            'items.*.variant_sku' => ['nullable', 'string', 'max:50'],
            'items.*.name' => ['required', 'string', 'max:190'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', 'in:flat,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,cod,bkash,bank_transfer'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_note' => ['nullable', 'string', 'max:255'],
            'sale_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_phone.regex' => 'Customer phone must be an 11-digit Bangladeshi number starting with 01 (e.g. 01712345678).',
            'customer_alt_phone.regex' => 'Alternate phone must be an 11-digit Bangladeshi number starting with 01 (e.g. 01712345678).',
            'customer_address.min' => 'Customer address must be at least 10 characters long.',
            'city_id.required_if' => 'Please select a City when sending via Pathao courier.',
            'zone_id.required_if' => 'Please select a Zone when sending via Pathao courier.',
            'area_id.required_if' => 'Please select an Area when sending via Pathao courier.',
            'item_weight.required_if' => 'Package weight is required when sending via Pathao courier.',
            'items.min' => 'At least one item must be added to the cart.',
        ];
    }
}
