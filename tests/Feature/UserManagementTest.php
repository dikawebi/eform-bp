<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_registration_rejects_non_corporate_email(): void
    {
        $this->post(route('register'), [
            'name' => 'Tamu Luar',
            'email' => 'tamu@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'tamu@gmail.com']);
    }

    public function test_registration_accepts_corporate_email_with_employee_role(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Karyawan Baru',
            'email' => 'karyawan.baru@borneoprima.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $user = User::where('email', 'karyawan.baru@borneoprima.com')->firstOrFail();
        $this->assertTrue($user->active);
        $this->assertTrue($user->hasRole('employee'));
    }

    public function test_registration_notifies_admin_and_hrga_to_link_nik(): void
    {
        $admin = $this->userWithRole('admin');
        $hrga = $this->userWithRole('hrga');
        $employee = $this->employeeUser('employee')[0];

        $this->post(route('register'), [
            'name' => 'Pendaftar Baru',
            'email' => 'pendaftar@borneoprima.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        foreach ([$admin, $hrga] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_type' => 'user',
                'notifiable_id' => $recipient->id,
                'type' => \App\Notifications\WorkflowNotification::class,
            ]);
        }
        $notification = $admin->notifications()->latest('id')->firstOrFail();
        $this->assertStringContainsString('pendaftar@borneoprima.com', $notification->data['message'] ?? '');
        $this->assertSame(0, $employee->notifications()->count());
    }

    public function test_admin_can_create_user_with_roles_and_nik_link(): void
    {
        $admin = $this->userWithRole('admin');
        $employee = Employee::factory()->create(['user_id' => null, 'active' => true]);

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'HRGA Baru',
            'email' => 'hrga.baru@borneoprima.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['employee', 'hrga'],
            'employee_id' => $employee->id,
            'active' => true,
        ])->assertRedirect(route('settings.users.index'));

        $user = User::where('email', 'hrga.baru@borneoprima.com')->firstOrFail();
        $this->assertTrue($user->hasRole('hrga'));
        $this->assertSame($user->id, (int) $employee->fresh()->user_id);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->put(route('settings.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => ['admin'],
            'active' => false,
        ])->assertSessionHasErrors('active');

        $this->assertTrue($admin->fresh()->active);
    }

    public function test_admin_cannot_revoke_own_admin_role(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->put(route('settings.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => ['employee'],
            'active' => true,
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        [$user] = $this->employeeUser('employee');

        $this->actingAs($user)->get(route('settings.users.index'))->assertForbidden();
        $this->actingAs($user)->post(route('settings.users.store'), [
            'name' => 'X', 'email' => 'x@borneoprima.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'active' => true,
        ])->assertForbidden();
    }

    public function test_employee_link_is_exclusive_to_one_account(): void
    {
        $admin = $this->userWithRole('admin');
        [, $employee] = $this->employeeUser('employee');

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'Ganda',
            'email' => 'ganda@borneoprima.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['employee'],
            'employee_id' => $employee->id,
            'active' => true,
        ])->assertSessionHasErrors('employee_id');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function employeeUser(string $role): array
    {
        $user = $this->userWithRole($role);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'active' => true]);

        return [$user, $employee];
    }
}
