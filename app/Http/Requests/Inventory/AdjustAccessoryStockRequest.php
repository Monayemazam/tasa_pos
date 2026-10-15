<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class AdjustAccessoryStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjustments.create');
    }

    public function rules(): array
    {
        return [
            'accessory_item_id' => ['required', 'exists:accessory_items,id'],
            'qty' => ['required', 'numeric', 'not_in:0'],
            'adjusted_on' => ['required_without:date', 'nullable', 'date'],
            'date' => ['required_without:adjusted_on', 'nullable', 'date'],
            'reason' => ['required', 'string', 'in:damaged,lost,count_correction,other'],
            'note' => ['nullable', 'string', 'max:255', 'required_if:reason,other'],
        ];
    }

    public function messages(): array
    {
        return [
            'accessory_item_id.required' => 'Accessory item is required.',
            'qty.required' => 'Adjustment quantity is required.',
            'qty.not_in' => 'Adjustment quantity cannot be 0.',
            'reason.required' => 'Adjustment reason is required.',
            'reason.in' => 'Reason must be damaged, lost, count_correction, or other.',
            'note.required_if' => 'A note is required when reason is "Other".',
        ];
    }
}
