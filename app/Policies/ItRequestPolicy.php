<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\ItRequest;
use App\Models\User;
use App\Services\EmployeeVisibility;

class ItRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('it_request.view.own') || $user->can('it_request.view.all') || $user->can('it_request.view.subordinates');
    }

    public function view(User $user, ItRequest $request): bool
    {
        return $this->viewOwn($user, $request)
            || $user->can('it_request.view.all')
            || ($user->can('it_request.view.subordinates') && $request->employee_id && app(EmployeeVisibility::class)->canViewEmployee($user, $request->employee_id));
    }

    public function viewOwn(User $user, ItRequest $request): bool
    {
        return $user->can('it_request.view.own') && $this->owner($user, $request);
    }

    public function create(User $user): bool
    {
        return $user->can('it_request.create.own');
    }

    public function update(User $user, ItRequest $request): bool
    {
        return $request->isEditable() && $this->owner($user, $request);
    }

    public function submit(User $user, ItRequest $request): bool
    {
        return $request->isEditable() && $this->owner($user, $request);
    }

    public function cancel(User $user, ItRequest $request): bool
    {
        return in_array($request->status, [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::Returned], true)
            && $this->owner($user, $request);
    }

    public function upload(User $user, ItRequest $request): bool
    {
        return $request->isEditable() && ($this->owner($user, $request) || $user->can('it_request.review'));
    }

    private function owner(User $user, ItRequest $request): bool
    {
        return (int) $request->created_by === (int) $user->id
            || ($request->employee_id && Employee::whereKey($request->employee_id)->where('user_id', $user->id)->exists());
    }
}
