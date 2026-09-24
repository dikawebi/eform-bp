<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\MedicalClaim;
use App\Models\MedicalDependent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class MedicalClaimRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $types = $this->input('benefit_types');
        $legacyType = $this->input('benefit_type');
        if (! is_array($types) && filled($legacyType)) {
            $types = [$legacyType];
        }
        if (is_array($types)) {
            $legacyType = reset($types) ?: null;
        }
        if (is_array($types)) {
            $this->merge(['benefit_types' => array_values($types), 'benefit_type' => $legacyType]);
        }
    }

    public function authorize(): bool
    {
        $claim = $this->route('medical_claim');

        return $claim instanceof MedicalClaim ? $this->user()->can('update', $claim) : $this->user()->can('create', MedicalClaim::class);
    }

    public function rules(): array
    {
        $benefitTypes = (array) config('eform.medical.benefit_types', []);
        $existingClaim = $this->route('medical_claim');
        if ($existingClaim instanceof MedicalClaim) {
            $legacyTypes = $existingClaim->benefit_types ?: [$existingClaim->benefit_type];
            $benefitTypes = array_values(array_unique(array_merge($benefitTypes, array_filter($legacyTypes))));
        }

        return ['benefit_type' => ['required', 'string', Rule::in($benefitTypes)], 'benefit_types' => ['required', 'array', 'min:1', 'max:10'], 'benefit_types.*' => ['required', 'string', 'distinct:strict', Rule::in($benefitTypes)], 'items' => ['required', 'array', 'min:1', 'max:30'], 'items.*.patient_name' => ['nullable', 'string', 'max:150'], 'items.*.relationship' => ['required', Rule::in(['self', 'spouse', 'child'])], 'items.*.dependent_id' => ['nullable', 'integer'], 'items.*.treatment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'items.*.facility_name' => ['required', 'string', 'max:255'], 'items.*.diagnosis_code' => ['nullable', 'string', 'max:80'], 'items.*.amount' => ['required', 'decimal:0,2', 'min:0', 'max:999999999999.99']];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $employee = Employee::where('user_id', $this->user()->id)->where('active', true)->first();
            foreach ((array) $this->input('items', []) as $i => $row) {
                if (($row['relationship'] ?? null) === 'self') {
                    if (filled($row['dependent_id'] ?? null) || (filled($row['patient_name'] ?? null) && trim((string) $row['patient_name']) !== (string) $employee?->name)) {
                        $validator->errors()->add("items.{$i}.patient_name", 'Identitas karyawan diambil dari master dan tidak dapat diubah.');
                    }
                } elseif (! $employee || ! filled($row['dependent_id'] ?? null) || ! MedicalDependent::where('employee_id', $employee->id)->whereKey($row['dependent_id'] ?? 0)->where('relationship', $row['relationship'])->where('active', true)->exists()) {
                    $validator->errors()->add("items.{$i}.dependent_id", 'Tanggungan aktif tidak ditemukan.');
                }
            }
            $total = '0';
            foreach ((array) $this->input('items', []) as $i => $row) {
                if (is_numeric($row['amount'] ?? null)) {
                    $amount = trim((string) $row['amount']);
                    if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
                        continue;
                    }
                    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
                    $amount = ltrim($whole, '0').'.'.str_pad($fraction, 2, '0');
                    $total = bcadd($total, $amount, 2);
                    if (bccomp($total, '999999999999.99', 2) > 0) {
                        $validator->errors()->add("items.{$i}.amount", 'Total klaim melebihi batas maksimum.');
                    }
                }
            }
        });
    }
}
