<?php

namespace App\Http\Requests;

use App\Models\ItItemOption;
use App\Models\ItRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ItRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('it_request');

        return $request instanceof ItRequest ? $this->user()->can('update', $request) : $this->user()->can('create', ItRequest::class);
    }

    public function rules(): array
    {
        $devices = ItItemOption::query()->where('kind', 'device')->where('is_active', true)->pluck('code')->all();
        $accessories = ItItemOption::query()->where('kind', 'accessory')->where('is_active', true)->pluck('code')->all();
        // Kode lama yang sudah nonaktif tetap valid untuk draf yang sudah tersimpan.
        $existing = $this->route('it_request');
        if ($existing instanceof ItRequest) {
            if ($existing->device_type) {
                $devices[] = $existing->device_type;
            }
            foreach ((array) $existing->accessories_json as $code) {
                $accessories[] = $code;
            }
            $devices = array_values(array_unique($devices));
            $accessories = array_values(array_unique($accessories));
        }
        $priorities = (array) config('eform.it_request.priorities', ['normal', 'high', 'critical']);

        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'is_new_employee' => ['nullable', 'boolean'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'recipient_id_number' => ['nullable', 'string', 'max:50'],
            'recipient_site' => ['nullable', 'string', 'max:100'],
            'recipient_cost_code' => ['nullable', 'string', 'max:50'],
            'recipient_position' => ['nullable', 'string', 'max:100'],
            'recipient_effective_date' => ['nullable', 'date_format:Y-m-d'],
            'request_type' => ['required', 'string', Rule::in(['new_item', 'replacement'])],
            'replacement_reason' => ['nullable', 'string', Rule::in(['not_suitable', 'damaged', 'other'])],
            'replacement_note' => ['nullable', 'string', 'max:2000'],
            'device_type' => ['nullable', 'string', Rule::in($devices)],
            'special_specification' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'software_standard' => ['nullable', 'array', 'max:20'],
            'software_standard.*' => ['string', 'max:100'],
            'software_optional' => ['nullable', 'array', 'max:20'],
            'software_optional.*.name' => ['required_with:software_optional', 'string', 'max:100'],
            'software_optional.*.license' => ['required_with:software_optional', 'string', Rule::in(['existing', 'new'])],
            'accessories' => ['nullable', 'array', 'max:30'],
            'accessories.*' => ['string', 'max:100'],
            'accessory_other_note' => ['nullable', 'string', 'max:500'],
            'needed_date' => ['nullable', 'date_format:Y-m-d'],
            'priority' => ['nullable', 'string', Rule::in($priorities)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            if (($data['request_type'] ?? null) === 'replacement' && empty($data['replacement_reason'])) {
                $validator->errors()->add('replacement_reason', 'Alasan penggantian wajib diisi.');
            }

            if (($data['replacement_reason'] ?? null) === 'other' && empty(trim((string) ($data['replacement_note'] ?? '')))) {
                $validator->errors()->add('replacement_note', 'Keterangan penggantian wajib diisi untuk alasan lainnya.');
            }

            if (! empty($data['special_specification'])) {
                if (empty(trim((string) ($data['description'] ?? '')))) {
                    $validator->errors()->add('description', 'Deskripsi perangkat wajib diisi untuk kebutuhan khusus.');
                }
                if (empty(trim((string) ($data['purpose'] ?? '')))) {
                    $validator->errors()->add('purpose', 'Tujuan penggunaan wajib diisi untuk kebutuhan khusus.');
                }
            }

            if (! empty($data['is_new_employee'])) {
                foreach (['recipient_name' => 'Nama penerima', 'recipient_site' => 'Site penerima', 'recipient_cost_code' => 'Cost code penerima'] as $field => $label) {
                    if (empty(trim((string) ($data[$field] ?? '')))) {
                        $validator->errors()->add($field, "{$label} wajib diisi untuk penerima baru.");
                    }
                }
            } elseif (empty($data['employee_id'])) {
                $validator->errors()->add('employee_id', 'Penerima dari master karyawan wajib dipilih.');
            }

            $accessories = (array) ($data['accessories'] ?? []);
            $noteRequired = ItItemOption::query()->where('kind', 'accessory')->whereIn('code', $accessories)->where('requires_note', true)->exists();
            if ($noteRequired && empty(trim((string) ($data['accessory_other_note'] ?? '')))) {
                $validator->errors()->add('accessory_other_note', 'Keterangan tambahan wajib diisi untuk opsi yang dipilih.');
            }
        });
    }
}
