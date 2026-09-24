<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['medical.payment.process', 'medical.payment.complete'] as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            foreach (['finance', 'admin'] as $roleName) {
                $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
                $role?->givePermissionTo($permission);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', ['medical.payment.process', 'medical.payment.complete'])->where('guard_name', 'web')->each(function (Permission $permission): void {
            $permission->roles()->detach();
            $permission->delete();
        });
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
