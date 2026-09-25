<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    /**
     * Seed reference access/workflow data without creating accounts or employee
     * records. Role assignment is limited to facts already represented by the
     * employee master: an active employee with an active linked user is an
     * employee; an active employee referenced as supervisor/HOD is respectively
     * a supervisor/HOD. Other operational roles require explicit administration.
     */
    public function run(): void
    {
        $this->call([
            RolesPermissionsSeeder::class,
            ApprovalWorkflowSeeder::class,
        ]);

        $this->assignEmployeeLinkedRoles();
    }

    private function assignEmployeeLinkedRoles(): void
    {
        $employeeUserIds = Employee::query()
            ->where('active', true)
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($query) => $query->where('active', true))
            ->pluck('user_id');

        $supervisorUserIds = Employee::query()
            ->where('active', true)
            ->whereIn('id', Employee::query()->where('active', true)->whereNotNull('supervisor_id')->select('supervisor_id'))
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($query) => $query->where('active', true))
            ->pluck('user_id');

        $hodUserIds = Employee::query()
            ->where('active', true)
            ->whereIn('id', Employee::query()->where('active', true)->whereNotNull('hod_id')->select('hod_id'))
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($query) => $query->where('active', true))
            ->pluck('user_id');

        $this->assignRoleToUsers($employeeUserIds->all(), 'employee');
        $this->assignRoleToUsers($supervisorUserIds->all(), 'supervisor');
        $this->assignRoleToUsers($hodUserIds->all(), 'hod');
    }

    /** @param list<int> $userIds */
    private function assignRoleToUsers(array $userIds, string $role): void
    {
        User::query()->whereKey($userIds)->cursor()->each(function (User $user) use ($role): void {
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        });
    }
}
