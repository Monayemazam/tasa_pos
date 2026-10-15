<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkerEarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        $earning = $this->route('workerEarning');
        return $earning && ($this->user()?->can('update', $earning) ?? false);
    }

    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date'],
            'period_start' => ['nullable', 'date', 'before_or_equal:work_date'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'rate' => ['required', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
