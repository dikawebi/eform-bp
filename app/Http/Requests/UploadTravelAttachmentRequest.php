<?php

namespace App\Http\Requests;

use App\Models\TravelRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadTravelAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $travel = $this->route('travel');

        // Route model binding must resolve the travel parent. Do not grant
        // access merely because the caller has a broad view permission.
        if ($travel instanceof TravelRequest && $this->user()?->can('upload', $travel)) {
            return true;
        }

        abort(403);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'document_type' => ['required', 'string', Rule::in(['invitation', 'supporting_document', 'receipt', 'other'])],
        ];
    }
}
