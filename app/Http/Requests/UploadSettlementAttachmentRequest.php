<?php

namespace App\Http\Requests;

use App\Models\Settlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadSettlementAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $settlement = $this->route('settlement');

        return $settlement instanceof Settlement && ($this->user()?->can('upload', $settlement) ?? false);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'document_type' => ['required', 'string', Rule::in(['receipt', 'supporting_document'])],
        ];
    }
}
