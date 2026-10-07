<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Resolves the employee records a user is allowed to read. */
class EmployeeVisibility
{
    /** @return list<int> */
    public function employeeIds(User $user): array
    {
        $own = Employee::query()->where('user_id', $user->getKey())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $visible = array_fill_keys($own, true);
        $frontier = $own;

        if ($this->hasBroadAccess($user)) {
            return [];
        }

        if (! $this->hasSubordinateAccess($user)) {
            return $own;
        }

        while ($frontier !== []) {
            $children = Employee::query()
                ->where(function ($query) use ($frontier): void {
                    $query->whereIn('supervisor_id', $frontier)->orWhereIn('hod_id', $frontier);
                })
                ->pluck('id')->map(fn ($id) => (int) $id)->all();

            $frontier = [];
            foreach ($children as $id) {
                if (! isset($visible[$id])) {
                    $visible[$id] = true;
                    $frontier[] = $id;
                }
            }
        }

        return array_map('intval', array_keys($visible));
    }

    public function canViewEmployee(User $user, ?int $employeeId): bool
    {
        return $employeeId !== null && ($this->hasBroadAccess($user) || in_array($employeeId, $this->employeeIds($user), true));
    }

    public function scope(User $user, Builder $query, string $employeeColumn = 'employee_id'): Builder
    {
        if ($this->hasBroadAccess($user)) {
            return $query;
        }

        $ids = $this->employeeIds($user);

        return $query->where(function (Builder $nested) use ($user, $ids, $employeeColumn): void {
            $nested->where('created_by', $user->getKey());
            if ($ids !== []) {
                $nested->orWhereIn($employeeColumn, $ids);
            }
        });
    }

    public function hasBroadAccess(User $user): bool
    {
        return $user->can('leave.view.all') || $user->can('travel.view.all')
            || $user->can('settlement.view.all') || $user->can('medical.view.all')
            || $user->can('medical.view.aggregate')
            || $user->can('it_request.view.all') || $user->can('erp_request.view.all');
    }

    private function hasSubordinateAccess(User $user): bool
    {
        return $user->can('leave.view.subordinates') || $user->can('travel.view.subordinates')
            || $user->can('settlement.view.subordinates') || $user->can('medical.view.subordinates')
            || $user->can('it_request.view.subordinates') || $user->can('erp_request.view.subordinates');
    }
}
