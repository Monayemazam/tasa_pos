<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense && $this->user()->can('update', $expense);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_date' => ['sometimes', 'required', 'date'],
            'expense_category_id' => ['sometimes', 'required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'payment_method' => ['sometimes', 'required', 'string', 'in:cash,bkash,bank_transfer,other'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'remove_attachment' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'expense_category_id' => 'expense category',
            'expense_date' => 'expense date',
            'payment_method' => 'payment method',
        ];
    }
}
