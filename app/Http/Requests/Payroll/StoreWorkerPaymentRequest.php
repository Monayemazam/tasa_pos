<?php

namespace App\Http\Requests\Payroll;

use App\Models\WorkerPayment;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkerPayment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['required', 'exists:workers,id'],
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string', 'in:cash,bkash,bank_transfer'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
