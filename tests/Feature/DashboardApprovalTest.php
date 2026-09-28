<?php

namespace Tests\Feature;

use App\Enums\ApprovalStepStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_approver_dashboard_exposes_pending_approval_count(): void
    {
        $approver = User::factory()->create();
        $approver->assignRole('hod');

        ApprovalRequest::create([
            'approvable_type' => 'App\\Models\\TravelRequest',
            'approvable_id' => 1,
            'chain_generation' => '1',
            'step_order' => 1,
            'step_code' => 'hod',
            'approver_role' => 'hod',
            'approver_user_id' => $approver->id,
            'status' => ApprovalStepStatus::Pending,
        ]);

        $this->actingAs($approver)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('pendingApproval', 1));
    }

    public function test_non_approver_dashboard_does_not_expose_pending_approval_card(): void
    {
        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('pendingApproval', null));
    }
}
