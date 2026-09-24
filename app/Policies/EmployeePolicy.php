<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * Policy master Employee (PRD §4, §6, §11).
 *
 * Berbasis permission Spatie:
 *  - employee.view.any  -> lihat semua / list master
 *  - employee.view.own  -> lihat data milik sendiri (owner via user_id)
 *  - employee.manage    -> create/update/deactivate (admin/HRGA)
 *
 * Authorization selalu diperiksa di server via Policy/Gate,
 * jangan hanya menyembunyikan tombol di frontend.
 */
class EmployeePolicy
{
    /**
     * Lihat daftar master karyawan.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('employee.view.any');
    }

    /**
     * Lihat satu data karyawan: pemegang employee.view.any lolos,
     * pemegang employee.view.own hanya untuk miliknya sendiri via user_id.
     */
    public function view(User $user, ?Employee $employee = null): bool
    {
        if ($user->can('employee.view.any')) {
            return true;
        }

        if (! $user->can('employee.view.own')) {
            return false;
        }

        // Tanpa instance (mis. link "profil saya"): izin own cukup,
        // controller memfilter scope ke user bersangkutan.
        if ($employee === null) {
            return true;
        }

        // Cek own via user_id (satu NIK = satu user).
        if ($employee->user_id === null) {
            return false;
        }

        return (int) $employee->user_id === (int) $user->getKey();
    }

    /**
     * Kelola master karyawan (CRUD admin/HRGA).
     */
    public function manage(User $user, ?Employee $employee = null): bool
    {
        return $user->can('employee.manage');
    }

    /**
     * Buat data karyawan -> sama dengan manage.
     */
    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    /**
     * Ubah data karyawan -> sama dengan manage.
     */
    public function update(User $user, ?Employee $employee = null): bool
    {
        return $this->manage($user, $employee);
    }
}
