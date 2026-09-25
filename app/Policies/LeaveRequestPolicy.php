<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\EmployeeVisibility;

/**
 * Authorization cuti/izin (PRD §4, §6, §11).
 *
 * - view: own (miliknya) vs any (leave.view.all).
 * - update: hanya draft/returned + owner (approved immutable).
 * - submit/cancel: owner + state yang diizinkan.
 * - Tanpa hapus fisik: delete selalu false.
 *
 * Owner = created_by user ATAU employee terhubung (user_id) agar tetap
 * benar walau link user↔employee berubah.
 */
class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave.view.own') || $user->can('leave.view.all') || $user->can('leave.view.subordinates');
    }

    public function view(User $user, LeaveRequest $leave): bool
    {
        return ($user->can('leave.view.all') || ($user->can('leave.view.own') && $this->isOwner($user, $leave)))
            || ($user->can('leave.view.subordinates') && app(EmployeeVisibility::class)->canViewEmployee($user, $leave->employee_id));
    }

    public function create(User $user): bool
    {
        return $user->can('leave.create.own');
    }

    /**
     * Update hanya draft/returned + owner (PRD §6.5: approved tidak
     * boleh diedit langsung; perubahan via returned/revision flow).
     */
    public function update(User $user, LeaveRequest $leave): bool
    {
        if (! in_array($leave->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
            return false;
        }

        return $this->isOwner($user, $leave);
    }

    /**
     * Submit: owner + draft/returned. Cek state di sini agar ID/status
     * dari frontend tidak dipercaya tanpa pengecekan (PRD §11).
     */
    public function submit(User $user, LeaveRequest $leave): bool
    {
        if (! in_array($leave->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
            return false;
        }

        return $this->isOwner($user, $leave);
    }

    /**
     * Cancel: owner + masih di tahap awal (draft/submitted/returned).
     */
    public function cancel(User $user, LeaveRequest $leave): bool
    {
        if (! in_array($leave->status, [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::Returned], true)) {
            return false;
        }

        return $this->isOwner($user, $leave);
    }

    /**
     * Larangan hapus fisik transaksi dari UI biasa.
     */
    public function delete(User $user, LeaveRequest $leave): bool
    {
        return false;
    }

    public function processAdvance(User $user, LeaveRequest $leave): bool
    {
        $user->loadMissing('employee');

        return $user->active && $user->employee?->active === true
            && $user->can('advance.process.leave')
            && in_array($leave->status, [RequestStatus::Approved, RequestStatus::Processing], true);
    }

    public function upload(User $user, LeaveRequest $leave): bool
    {
        return app(AttachmentPolicy::class)->uploadLeave($user, $leave);
    }

    protected function isOwner(User $user, LeaveRequest $leave): bool
    {
        if ($leave->created_by !== null && (int) $leave->created_by === (int) $user->getKey()) {
            return true;
        }

        $employeeId = $leave->employee_id;

        if ($employeeId === null) {
            return false;
        }

        return Employee::query()
            ->whereKey($employeeId)
            ->where('user_id', $user->getKey())
            ->exists();
    }
}
