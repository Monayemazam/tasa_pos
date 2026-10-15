<?php

namespace App\Http\Requests\Expenses;

use App\Models\RecurringExpense;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecurringExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', RecurringExpense::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'in:cash,bkash,bank_transfer,other'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:255'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:28'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'expense_category_id' => 'expense category',
            'day_of_month' => 'day of month',
        ];
    }
}
