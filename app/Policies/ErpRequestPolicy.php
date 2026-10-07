<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\ErpRequest;
use App\Models\User;
use App\Services\EmployeeVisibility;

class ErpRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('erp_request.view.own') || $user->can('erp_request.view.all') || $user->can('erp_request.view.subordinates');
    }

    public function view(User $user, ErpRequest $request): bool
    {
        return $this->viewOwn($user, $request)
            || $user->can('erp_request.view.all')
            || ($user->can('erp_request.view.subordinates') && $request->employee_id && app(EmployeeVisibility::class)->canViewEmployee($user, $request->employee_id));
    }

    public function viewOwn(User $user, ErpRequest $request): bool
    {
        return $user->can('erp_request.view.own') && $this->owner($user, $request);
    }

    public function create(User $user): bool
    {
        return $user->can('erp_request.create.own');
    }

    public function update(User $user, ErpRequest $request): bool
    {
        return $request->isEditable() && $this->owner($user, $request);
    }

    public function submit(User $user, ErpRequest $request): bool
    {
        return $request->isEditable() && $this->owner($user, $request);
    }

    public function cancel(User $user, ErpRequest $request): bool
    {
        return in_array($request->status, [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::Returned], true)
            && $this->owner($user, $request);
    }

    public function upload(User $user, ErpRequest $request): bool
    {
        return $request->isEditable() && ($this->owner($user, $request) || $user->can('erp_request.review'));
    }

    private function owner(User $user, ErpRequest $request): bool
    {
        return (int) $request->created_by === (int) $user->id
            || ($request->employee_id && Employee::whereKey($request->employee_id)->where('user_id', $user->id)->exists());
    }
}
