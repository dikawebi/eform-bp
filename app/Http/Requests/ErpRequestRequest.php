<?php

namespace App\Http\Requests;

use App\Models\ErpRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ErpRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('erp_request');

        return $request instanceof ErpRequest ? $this->user()->can('update', $request) : $this->user()->can('create', ErpRequest::class);
    }

    public function rules(): array
    {
        $modules = (array) config('eform.erp_request.modules', []);

        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'recipient_site' => ['nullable', 'string', 'max:100'],
            'recipient_cost_code' => ['nullable', 'string', 'max:50'],
            'recipient_position' => ['nullable', 'string', 'max:100'],
            'action_type' => ['required', 'string', Rule::in(['new_account', 'modify_role', 'reset_auth'])],
            'existing_erp_username' => ['nullable', 'string', 'max:150'],
            'business_purpose' => ['required', 'string', 'max:5000'],
            'modules' => ['nullable', 'array', 'max:20'],
            'modules.*' => ['string', Rule::in($modules)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            if (in_array($data['action_type'] ?? null, ['modify_role', 'reset_auth'], true)
                && empty(trim((string) ($data['existing_erp_username'] ?? '')))) {
                $validator->errors()->add('existing_erp_username', 'Username ERP wajib diisi untuk perubahan role atau reset akses.');
            }

            if (empty($data['employee_id']) && empty(trim((string) ($data['recipient_name'] ?? '')))) {
                $validator->errors()->add('recipient_name', 'Penerima wajib dipilih dari master atau diisi manual.');
            }
        });
    }
}
