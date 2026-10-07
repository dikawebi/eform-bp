<?php

namespace App\Http\Requests;

use App\Models\ErpRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadErpAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('erp_request');

        return $request instanceof ErpRequest && $this->user()->can('upload', $request);
    }

    public function rules(): array
    {
        $allowedDocuments = config('eform.erp_request.allowed_documents', []);

        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:' . config('eform.erp_request.max_file_size_kb', 5120)],
            'document_type' => ['required', 'string', Rule::in($allowedDocuments)],
        ];
    }
}
