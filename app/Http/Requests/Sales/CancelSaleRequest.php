<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class CancelSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.cancel') || $this->user()?->hasRole('Owner');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A cancellation reason is required.',
            'reason.min' => 'The cancellation reason must be at least 5 characters.',
        ];
    }
}
