<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employee.manage') ?? false;
    }

    /**
     * Normalisasi NIK (B2).
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
        $employeeId = $this->route('employee')?->getKey();

        return [
            'user_id' => [
                'nullable', 'integer', 'exists:users,id',
                Rule::unique('employees', 'user_id')->ignore($employeeId),
            ],
            'employee_number' => [
                'required', 'string', 'max:32',
                Rule::unique('employees', 'employee_number')->ignore($employeeId),
            ],
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
            // B5: active TIDAK via update umum; nonaktifkan hanya via destroy.
            'joined_at' => ['nullable', 'date_format:Y-m-d'],
            'ended_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joined_at'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Employee|null $employee */
            $employee = $this->route('employee');
            $employeeId = $employee?->getKey();

            if ($employeeId === null) {
                return;
            }

            if (
                $this->input('supervisor_id') !== null
                && (int) $this->input('supervisor_id') === (int) $employeeId
            ) {
                $validator->errors()->add('supervisor_id', 'Supervisor tidak boleh diri sendiri.');
            }

            if (
                $this->input('hod_id') !== null
                && (int) $this->input('hod_id') === (int) $employeeId
            ) {
                $validator->errors()->add('hod_id', 'HOD tidak boleh diri sendiri.');
            }

            // B6: user_id hanya boleh diubah admin (nilai sama = lolos).
            $newUserId = $this->input('user_id');
            $oldUserId = $employee?->user_id;
            $userChanged = ((int) ($newUserId ?? 0) !== (int) ($oldUserId ?? 0))
                || (($newUserId === null) !== ($oldUserId === null));
            // Normalisasi: null vs '' dianggap sama.
            if (($newUserId === '' || $newUserId === null) && ($oldUserId === null)) {
                $userChanged = false;
            }
            if ($userChanged) {
                $user = $this->user();
                if ($user === null || ! $user->hasRole('admin')) {
                    $validator->errors()->add('user_id', 'Hanya admin yang dapat mengubah user terhubung.');
                }
            }

            // B3: supervisor/HOD harus aktif + ancestor walk max depth 10.
            foreach (['supervisor_id', 'hod_id'] as $field) {
                $value = $this->input($field);
                if ($value === null || $value === '') {
                    continue;
                }

                if (! Employee::isActiveLeader((int) $value)) {
                    $validator->errors()->add($field, 'Atasan yang dipilih harus karyawan aktif.');

                    continue;
                }

                if (! Employee::isActiveLeaderInDepartment((int) $value, (string) $this->input('department', ''))) {
                    $validator->errors()->add($field, 'Supervisor dan HOD harus berasal dari departemen yang sama.');
                    continue;
                }

                if (Employee::wouldCreateCycle((int) $employeeId, (int) $value, 10)) {
                    $validator->errors()->add($field, 'Penugasan atasan membentuk cycle hierarki.');
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
