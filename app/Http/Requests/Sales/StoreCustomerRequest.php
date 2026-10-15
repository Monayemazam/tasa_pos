<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.update')
            || $this->user()?->can('sales.create')
            || $this->user()?->hasRole('Owner');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'unique:customers,phone', 'regex:/^01[3-9]\d{8}$/'],
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

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone must be an 11-digit Bangladeshi number starting with 01 (e.g. 01712345678).',
            'phone.unique' => 'A customer with this phone number already exists.',
            'address.min' => 'Address must be at least 10 characters long.',
        ];
    }
}
