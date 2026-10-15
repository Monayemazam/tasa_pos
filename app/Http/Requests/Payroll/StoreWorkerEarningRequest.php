<?php

namespace App\Http\Requests\Payroll;

use App\Models\WorkerEarning;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkerEarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkerEarning::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['required', 'exists:workers,id'],
            'work_date' => ['required', 'date'],
            'period_start' => ['nullable', 'date', 'before_or_equal:work_date'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'rate' => ['required', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
