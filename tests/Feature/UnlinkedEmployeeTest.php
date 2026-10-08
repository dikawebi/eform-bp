<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ApprovalWorkflowSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnlinkedEmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolesPermissionsSeeder::class, ApprovalWorkflowSeeder::class]);
    }

    public function test_unlinked_user_sees_guidance_instead_of_empty_forms(): void
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('employee');

        $this->actingAs($user)->get(route('leaves.create'))->assertInertia(fn ($page) => $page->component('Leaves/Unlinked'));
        $this->actingAs($user)->get(route('travels.create'))->assertInertia(fn ($page) => $page->component('Travels/Unlinked'));
        $this->actingAs($user)->get(route('settlements.create'))->assertInertia(fn ($page) => $page->component('Settlements/Unlinked'));
        $this->actingAs($user)->get(route('medical-claims.create'))->assertInertia(fn ($page) => $page->component('MedicalClaims/Unlinked'));
    }

    public function test_unlinked_user_cannot_store_leave_or_travel(): void
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('employee');

        $this->actingAs($user)->post(route('leaves.store'), [
            'leave_type' => 'annual_leave',
            'reason' => 'Uji akun tanpa NIK.',
            'periods' => [['category' => 'annual_leave', 'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->addDay()->toDateString()]],
        ])->assertSessionHasErrors('employee_id');
        $this->actingAs($user)->post(route('travels.store'), [
            'purpose' => 'Uji akun tanpa NIK.',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDay()->toDateString(),
            'origin' => 'Jakarta',
            'destination' => 'Puruk Cahu',
            'items' => [['category' => 'meal', 'description' => 'Makan', 'quantity' => 1, 'unit_price' => 50000]],
        ])->assertSessionHasErrors('employee_id');
    }

    public function test_onbehalf_admin_without_link_still_gets_forms(): void
    {
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('leaves.create'))->assertInertia(fn ($page) => $page->component('Leaves/Create'));
        $this->actingAs($admin)->get(route('travels.create'))->assertInertia(fn ($page) => $page->component('Travels/Create'));
        $this->actingAs($admin)->get(route('settlements.create'))->assertOk();
    }
}
