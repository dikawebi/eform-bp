<?php

namespace App\Http\Requests;

use App\Models\MedicalClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMedicalAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $claim = $this->route('medical_claim');

        return $claim instanceof MedicalClaim && $this->user()->can('upload', $claim);
    }

    public function rules(): array
    {
        $allowedDocuments = config('eform.medical.allowed_documents', []);

        return ['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:'.config('eform.medical.max_file_size_kb', 5120)], 'document_type' => ['required', 'string', Rule::in($allowedDocuments)]];
    }
}
