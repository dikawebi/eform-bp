<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_admin_can_list_create_and_update_roles_and_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('settings.roles.index'))->assertOk();

        $this->actingAs($admin)->post(route('settings.roles.store'), [
            'name' => 'claims_reviewer',
            'permissions' => ['medical.review', 'medical.view.all'],
        ])->assertRedirect(route('settings.roles.index'));

        $role = Role::findByName('claims_reviewer', 'web');
        $this->assertTrue($role->hasPermissionTo('medical.review'));

        $this->actingAs($admin)->put(route('settings.roles.update', $role), [
            'name' => 'claims_manager',
            'permissions' => ['medical.review', 'role.manage'],
        ])->assertRedirect(route('settings.roles.index'));

        $role->refresh();
        $this->assertSame('claims_manager', $role->name);
        $this->assertTrue($role->hasPermissionTo('role.manage'));
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'description' => 'role.created',
            'causer_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'description' => 'role.updated',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_role_management_requires_server_side_permission_and_valid_permissions(): void
    {
        $employee = User::factory()->create();
        $employee->assignRole('employee');
        $role = Role::findByName('employee', 'web');

        $this->actingAs($employee)->get(route('settings.roles.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('settings.roles.store'), [
            'name' => 'not_allowed',
            'permissions' => ['does-not-exist'],
        ])->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->put(route('settings.roles.update', $role), [
            'name' => 'employee',
            'permissions' => ['does-not-exist'],
        ])->assertSessionHasErrors('permissions.0');
    }

    public function test_admin_role_cannot_lose_role_management_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $role = Role::findByName('admin', 'web');

        $this->actingAs($admin)->put(route('settings.roles.update', $role), [
            'name' => 'admin',
            'permissions' => ['dashboard.view'],
        ])->assertRedirect();

        $this->assertTrue($role->fresh()->hasPermissionTo('role.manage'));
    }
}
