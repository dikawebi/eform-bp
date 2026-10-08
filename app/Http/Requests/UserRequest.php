<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User ? $this->user()->can('update', $target) : $this->user()->can('create', User::class);
    }

    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'roles' => ['nullable', 'array', 'max:20'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'web'))],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $target = $this->route('user');
            $targetId = $target instanceof User ? (int) $target->getKey() : null;

            // Satu akun hanya untuk satu NIK; satu NIK hanya untuk satu akun.
            $employeeId = $this->input('employee_id');
            if (filled($employeeId)) {
                $takenBy = Employee::query()->whereKey($employeeId)->value('user_id');
                if ($takenBy !== null && (int) $takenBy !== (int) $targetId) {
                    $validator->errors()->add('employee_id', 'Karyawan sudah terhubung ke akun lain.');
                }
            }

            // Jangan kunci diri sendiri di luar sistem.
            if ($target instanceof User && (int) $target->getKey() === (int) $this->user()->getKey()) {
                if ($this->boolean('active') === false) {
                    $validator->errors()->add('active', 'Akun sendiri tidak dapat dinonaktifkan.');
                }
                $roles = (array) $this->input('roles', []);
                if ($this->has('roles') && ! in_array('admin', $roles, true) && $this->user()->hasRole('admin')) {
                    $validator->errors()->add('roles', 'Role admin pada akun sendiri tidak dapat dicabut.');
                }
            }
        });
    }
}
