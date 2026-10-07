<?php

namespace App\Http\Requests;

use App\Models\ItRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadItAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('it_request');

        return $request instanceof ItRequest && $this->user()->can('upload', $request);
    }

    public function rules(): array
    {
        $allowedDocuments = config('eform.it_request.allowed_documents', []);

        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:' . config('eform.it_request.max_file_size_kb', 5120)],
            'document_type' => ['required', 'string', Rule::in($allowedDocuments)],
        ];
    }
}
