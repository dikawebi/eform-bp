<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\Settlement;
use App\Models\User;

class SettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settlement.view.own') || $user->can('settlement.view.all');
    }

    public function create(User $user): bool
    {
        return $user->can('settlement.create.own');
    }

    public function view(User $user, Settlement $settlement): bool
    {
        return $user->can('settlement.view.all') || ($user->can('settlement.view.own') && $this->owner($user, $settlement));
    }

    public function update(User $user, Settlement $settlement): bool
    {
        return $settlement->isEditable() && $this->owner($user, $settlement);
    }

    public function submit(User $user, Settlement $settlement): bool
    {
        return $settlement->isEditable() && $this->owner($user, $settlement);
    }

    public function cancel(User $user, Settlement $settlement): bool
    {
        return in_array($settlement->status, [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::Returned], true) && $this->owner($user, $settlement);
    }

    public function upload(User $user, Settlement $settlement): bool
    {
        return $settlement->isEditable() && ($user->can('attachment.upload.all') || ($user->can('attachment.upload.own') && $this->owner($user, $settlement)));
    }

    public function complete(User $user, Settlement $settlement): bool
    {
        return $settlement->status === RequestStatus::PaymentProcessing && $user->can('settlement.complete');
    }

    private function owner(User $user, Settlement $settlement): bool
    {
        return (int) $settlement->created_by === (int) $user->id || Employee::query()->whereKey($settlement->employee_id)->where('user_id', $user->id)->exists();
    }
}
