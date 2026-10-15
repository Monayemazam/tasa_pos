<?php

namespace App\Http\Requests\Expenses;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category && $this->user()->can('update', $category);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof ExpenseCategory ? $category->id : $category;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('expense_categories', 'name')->ignore($categoryId),
            ],
            'pl_group' => [
                'sometimes',
                'required',
                'string',
                Rule::in(array_keys(ExpenseCategory::PL_GROUPS)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'pl_group' => 'P&L group mapping',
        ];
    }
}
