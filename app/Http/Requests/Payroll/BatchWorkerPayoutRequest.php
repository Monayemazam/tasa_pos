<?php

namespace App\Http\Requests\Payroll;

use App\Models\WorkerPayment;
use Illuminate\Foundation\Http\FormRequest;

class BatchWorkerPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkerPayment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date'],
            'method' => ['nullable', 'string', 'in:cash,bkash,bank_transfer'],
            'note' => ['nullable', 'string', 'max:255'],
            'payouts' => ['required', 'array', 'min:1'],
            'payouts.*.worker_id' => ['required', 'exists:workers,id'],
            'payouts.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
