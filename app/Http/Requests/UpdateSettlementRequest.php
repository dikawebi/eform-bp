<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateSettlementRequest extends StoreSettlementRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('settlement')) ?? false;
    }

    public function rules(): array
    {
        return ['items' => ['required', 'array', 'min:1'], 'items.*.transaction_date' => ['required', 'date'], 'items.*.description' => ['required', 'string', 'max:255'], 'items.*.category' => ['required', Rule::in(['transport', 'hotel', 'meal', 'other'])], 'items.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'], 'items.*.receipt_no' => ['nullable', 'string', 'max:100']];
    }
}
