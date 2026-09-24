<?php

namespace App\Http\Requests;

use App\Models\Settlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Settlement::class) ?? false;
    }

    public function rules(): array
    {
        return ['source_type' => ['required', Rule::in(['leave_request', 'travel_request', 'new_join', 'other'])], 'source_id' => ['required', 'integer', 'min:1'], 'source_reference' => ['required_if:source_type,new_join,other', 'nullable', 'string', 'max:100'], 'items' => ['required', 'array', 'min:1'],
            'items.*.transaction_date' => ['required', 'date'], 'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.category' => ['required', Rule::in(['transport', 'hotel', 'meal', 'other'])], 'items.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'], 'items.*.receipt_no' => ['nullable', 'string', 'max:100']];
    }
}
