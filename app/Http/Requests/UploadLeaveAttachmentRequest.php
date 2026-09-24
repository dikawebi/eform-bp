<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadLeaveAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $leave = $this->route('leave');

        return $leave instanceof LeaveRequest && $this->user()?->can('upload', $leave) === true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'document_type' => ['required', 'string', Rule::in(['invitation', 'supporting_document', 'receipt', 'other'])],
        ];
    }
}
