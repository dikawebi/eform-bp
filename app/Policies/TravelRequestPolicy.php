<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\EmployeeVisibility;

/**
 * Authorization perjalanan dinas (PRD §4, §5.2, §6, §11).
 * Mirror LeaveRequestPolicy.
 *
 * - view: own (miliknya) vs any (travel.view.all).
 * - update: hanya draft/returned + owner (approved immutable).
 * - submit/cancel: owner + state yang diizinkan.
 * - Tanpa hapus fisik: delete selalu false.
 *
 * Owner = created_by user ATAU employee terhubung (user_id) agar tetap
 * benar walau link user↔employee berubah.
 */
class TravelRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('travel.view.own') || $user->can('travel.view.all') || $user->can('travel.view.subordinates');
    }

    public function view(User $user, TravelRequest $travel): bool
    {
        return ($user->can('travel.view.all') || ($user->can('travel.view.own') && $this->isOwner($user, $travel)))
            || ($user->can('travel.view.subordinates') && app(EmployeeVisibility::class)->canViewEmployee($user, $travel->employee_id));
    }

    public function create(User $user): bool
    {
        return $user->can('travel.create.own');
    }

    /**
     * Update hanya draft/returned + owner (PRD §6.5: approved tidak
     * boleh diedit langsung; perubahan via returned/revision flow).
     */
    public function update(User $user, TravelRequest $travel): bool
    {
        if (! in_array($travel->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
            return false;
        }

        return $this->isOwner($user, $travel);
    }

    /**
     * Submit: owner + draft/returned. Cek state di sini agar ID/status
     * dari frontend tidak dipercaya tanpa pengecekan (PRD §11).
     */
    public function submit(User $user, TravelRequest $travel): bool
    {
        if (! in_array($travel->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
            return false;
        }

        return $this->isOwner($user, $travel);
    }

    /**
     * Cancel: owner + masih di tahap awal (draft/submitted/returned).
     */
    public function cancel(User $user, TravelRequest $travel): bool
    {
        if (! in_array($travel->status, [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::Returned], true)) {
            return false;
        }

        return $this->isOwner($user, $travel);
    }

    /**
     * Larangan hapus fisik transaksi dari UI biasa.
     */
    public function delete(User $user, TravelRequest $travel): bool
    {
        return false;
    }

    public function can_edit(User $user, TravelRequest $travel): bool
    {
        return $this->update($user, $travel);
    }

    public function can_submit(User $user, TravelRequest $travel): bool
    {
        return $this->submit($user, $travel);
    }

    public function can_cancel(User $user, TravelRequest $travel): bool
    {
        return $this->cancel($user, $travel);
    }

    public function upload(User $user, TravelRequest $travel): bool
    {
        return app(AttachmentPolicy::class)->upload($user, $travel);
    }

    public function processAdvance(User $user, TravelRequest $travel): bool
    {
        $user->loadMissing('employee');

        return $user->active && $user->employee?->active === true
            && $user->can('advance.process.travel')
            && in_array($travel->status, [RequestStatus::Approved, RequestStatus::Processing], true);
    }

    protected function isOwner(User $user, TravelRequest $travel): bool
    {
        if ($travel->created_by !== null && (int) $travel->created_by === (int) $user->getKey()) {
            return true;
        }

        $employeeId = $travel->employee_id;

        if ($employeeId === null) {
            return false;
        }

        return Employee::query()
            ->whereKey($employeeId)
            ->where('user_id', $user->getKey())
            ->exists();
    }
}
