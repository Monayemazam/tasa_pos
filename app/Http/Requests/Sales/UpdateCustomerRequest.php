<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.update') || $this->user()?->hasRole('Owner');
    }

    public function rules(): array
    {
        $customerId = $this->route('customer') instanceof \App\Models\Customer
            ? $this->route('customer')->id
            : $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required',
                'string',
                'regex:/^01[3-9]\d{8}$/',
                Rule::unique('customers', 'phone')->ignore($customerId),
            ],
            'alt_phone' => ['nullable', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'address' => ['required', 'string', 'min:10', 'max:500'],
            'pathao_city_id' => ['nullable', 'integer'],
            'pathao_zone_id' => ['nullable', 'integer'],
            'pathao_area_id' => ['nullable', 'integer'],
            'city_name' => ['nullable', 'string', 'max:100'],
            'zone_name' => ['nullable', 'string', 'max:100'],
            'area_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
