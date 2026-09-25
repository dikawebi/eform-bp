<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\MedicalClaim;
use App\Models\User;
use App\Services\EmployeeVisibility;

class MedicalClaimPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('medical.view.own') || $user->can('medical.view.all') || $user->can('medical.view.aggregate') || $user->can('medical.view.subordinates');
    }

    public function view(User $user, MedicalClaim $claim): bool
    {
        return $this->viewOwn($user, $claim) || $this->viewReview($user, $claim) || $this->viewAggregate($user, $claim)
            || ($user->can('medical.view.subordinates') && app(EmployeeVisibility::class)->canViewEmployee($user, $claim->employee_id));
    }

    public function viewOwn(User $user, MedicalClaim $claim): bool
    {
        return $user->can('medical.view.own') && $this->owner($user, $claim);
    }

    public function viewReview(User $user, MedicalClaim $claim): bool
    {
        return $user->can('medical.review') && $user->can('medical.view.sensitive');
    }

    public function viewAggregate(User $user, MedicalClaim $claim): bool
    {
        return $user->can('medical.view.aggregate') || $user->can('medical.view.all');
    }

    public function viewSensitive(User $user, MedicalClaim $claim): bool
    {
        return $user->can('medical.view.sensitive') && ($this->owner($user, $claim) || $this->viewReview($user, $claim));
    }

    public function create(User $user): bool
    {
        return $user->can('medical.create.own');
    }

    public function update(User $user, MedicalClaim $claim): bool
    {
        return $claim->isEditable() && $this->owner($user, $claim);
    }

    public function submit(User $user, MedicalClaim $claim): bool
    {
        return $claim->isEditable() && $this->owner($user, $claim);
    }

    public function cancel(User $user, MedicalClaim $claim): bool
    {
        return in_array($claim->status, [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::Returned], true) && $this->owner($user, $claim);
    }

    public function review(User $user, MedicalClaim $claim): bool
    {
        return $this->viewReview($user, $claim);
    }

    public function upload(User $user, MedicalClaim $claim): bool
    {
        return $claim->isEditable() && ($this->owner($user, $claim) || $this->viewReview($user, $claim));
    }

    public function payment(User $user, MedicalClaim $claim): bool
    {
        $user->loadMissing('employee');

        return $user->can('medical.payment.process') && $user->active && $user->employee?->active === true
            && $claim->status === RequestStatus::Approved
            && $claim->approvalRequests()->where('status', 'pending')->doesntExist()
            && $claim->approvalRequests()->where('status', 'approved')->exists();
    }

    public function complete(User $user, MedicalClaim $claim): bool
    {
        $user->loadMissing('employee');

        return $user->can('medical.payment.complete') && $user->active && $user->employee?->active === true
            && $claim->status === RequestStatus::PaymentProcessing
            && $claim->payment_processed_by !== null
            && (int) $claim->payment_processed_by !== (int) $user->id;
    }

    private function owner(User $user, MedicalClaim $claim): bool
    {
        return (int) $claim->created_by === (int) $user->id || Employee::whereKey($claim->employee_id)->where('user_id', $user->id)->exists();
    }
}
