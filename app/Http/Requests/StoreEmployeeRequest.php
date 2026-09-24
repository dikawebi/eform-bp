<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employee.manage') ?? false;
    }

    /**
     * Normalisasi NIK (B2): trim agar " NIK-1 " tidak lolos unique.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('employee_number') && is_string($this->input('employee_number'))) {
            $this->merge(['employee_number' => trim($this->input('employee_number'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:employees,user_id'],
            'employee_number' => ['required', 'string', 'max:32', Rule::unique('employees', 'employee_number')],
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:100'],
            'level' => ['required', 'string', 'max:100'],
            'job_title' => ['required', 'string', 'max:150'],
            'roster' => ['nullable', 'string', 'max:50'],
            'employment_status' => ['required', 'string', Rule::in(['permanent', 'contract', 'probation', 'resigned', 'terminated'])],
            'poh_status' => ['required', 'string', Rule::in(['local', 'non_local'])],
            'poh_city' => ['nullable', 'string', 'max:100'],
            'poh_province' => ['nullable', 'string', 'max:100'],
            'supervisor_id' => ['nullable', 'integer', 'exists:employees,id'],
            'hod_id' => ['nullable', 'integer', 'exists:employees,id'],
            'is_project_based' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'joined_at' => ['nullable', 'date_format:Y-m-d'],
            'ended_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joined_at'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // B6: user_id hanya boleh di-set admin.
            if ($this->filled('user_id') && $this->input('user_id') !== null) {
                $user = $this->user();
                if ($user === null || ! $user->hasRole('admin')) {
                    $validator->errors()->add('user_id', 'Hanya admin yang dapat menghubungkan user.');
                }
            }

            // B3: supervisor/HOD harus aktif.
            foreach (['supervisor_id', 'hod_id'] as $field) {
                $value = $this->input($field);
                if ($value !== null && $value !== '' && ! Employee::isActiveLeader((int) $value)) {
                    $validator->errors()->add($field, 'Atasan yang dipilih harus karyawan aktif.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_number.unique' => 'NIK sudah terdaftar.',
            'user_id.unique' => 'User sudah terhubung ke karyawan lain.',
            'supervisor_id.exists' => 'Supervisor tidak ditemukan.',
            'hod_id.exists' => 'HOD tidak ditemukan.',
        ];
    }
}
