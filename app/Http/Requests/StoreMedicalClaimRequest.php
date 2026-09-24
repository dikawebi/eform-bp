<?php

namespace App\Http\Requests;

class StoreMedicalClaimRequest extends MedicalClaimRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'receipt' => [
                'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp',
                'extensions:pdf,jpg,jpeg,png,webp',
                'max:'.config('eform.medical.max_file_size_kb', 5120),
            ],
        ];
    }
}
