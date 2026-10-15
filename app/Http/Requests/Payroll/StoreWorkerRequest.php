<?php

namespace App\Http\Requests\Payroll;

use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Worker::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['nullable', 'string', 'max:100'],
            'wage_basis' => ['required', 'string', 'in:per_day,per_piece,fixed_weekly'],
            'rate' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
