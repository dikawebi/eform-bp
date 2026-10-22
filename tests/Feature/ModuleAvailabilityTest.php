<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ModuleAvailability;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuleAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('admin');

        return $user;
    }

    private function member(): User
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('employee');
        Employee::factory()->create(['user_id' => $user->id, 'active' => true]);

        return $user->refresh();
    }

    public function test_admin_can_view_and_update_module_settings(): void
    {
        $this->actingAs($this->admin())->get(route('settings.modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Modules/Index')
                ->has('modules', 7));

        $this->actingAs($this->admin())->put(route('settings.modules.update'), [
            'modules' => [
                'leave' => ['is_enabled' => false, 'message' => 'Tutup sementara.'],
                'travel' => ['is_enabled' => true, 'message' => null],
            ],
        ])->assertRedirect();

        $this->assertFalse(ModuleAvailability::isEnabled('leave'));
        $this->assertSame('Tutup sementara.', ModuleAvailability::message('leave'));
        $this->assertTrue(ModuleAvailability::isEnabled('travel'));
        $this->assertDatabaseHas('activity_log', ['description' => 'module.updated']);
    }

    public function test_non_admin_is_forbidden_from_module_settings(): void
    {
        $member = $this->member();

        $this->actingAs($member)->get(route('settings.modules.index'))->assertForbidden();
        $this->actingAs($member)->put(route('settings.modules.update'), [
            'modules' => ['leave' => ['is_enabled' => false]],
        ])->assertForbidden();
        $this->assertTrue(ModuleAvailability::isEnabled('leave'));
    }

    public function test_disabled_module_blocks_member_but_not_manager(): void
    {
        ModuleAvailability::updateOrCreate(['module' => 'leave'], ['is_enabled' => false, 'maintenance_message' => 'Tutup sementara.']);

        $this->actingAs($this->member())->get(route('leaves.index'))
            ->assertStatus(503)
            ->assertInertia(fn (Assert $page) => $page
                ->component('ModuleMaintenance')
                ->where('label', 'Cuti/Izin')
                ->where('message', 'Tutup sementara.'));

        $this->actingAs($this->member())->post(route('leaves.store'), [])->assertStatus(503);

        $this->actingAs($this->admin())->get(route('leaves.index'))->assertOk();
    }

    public function test_menu_hides_disabled_module_for_member(): void
    {
        ModuleAvailability::updateOrCreate(['module' => 'it_request'], ['is_enabled' => false, 'maintenance_message' => null]);

        $this->actingAs($this->member())->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('menus', fn ($menus): bool => collect($menus)->every(fn ($menu): bool => ($menu['url'] ?? '') !== '/it-requests')));

        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('menus', fn ($menus): bool => collect($menus)->contains(fn ($menu): bool => ($menu['url'] ?? '') === '/it-requests')));
    }

    public function test_modules_enabled_by_default_without_configuration(): void
    {
        $this->assertTrue(ModuleAvailability::isEnabled('leave'));
        $this->actingAs($this->member())->get(route('leaves.index'))->assertOk();
    }
}
