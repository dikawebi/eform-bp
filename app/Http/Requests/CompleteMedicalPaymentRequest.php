<?php

namespace App\Http\Requests;

use App\Models\MedicalClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteMedicalPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $claim = $this->route('medical_claim');

        return $claim instanceof MedicalClaim && $this->user()->can('complete', $claim);
    }

    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]{2,99}$/', Rule::unique('medical_claims', 'payment_reference')],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['payment_reference' => strtoupper(trim((string) $this->input('payment_reference')))]);
    }
}
