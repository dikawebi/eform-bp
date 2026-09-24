<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Database\Seeders\ApprovalWorkflowSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalWorkflowManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesPermissionsSeeder::class, ApprovalWorkflowSeeder::class]);
    }

    public function test_workflow_can_be_updated_by_a_user_with_workflow_manage(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('hrga_manager');
        $workflow = ApprovalWorkflow::query()->where('code', 'travel_default')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('settings.workflows.update', $workflow), [
            'name' => 'Perjalanan Dinas — Revisi',
            'is_active' => true,
            'steps' => [
                ['step_order' => 1, 'step_code' => 'supervisor', 'approver_role' => 'supervisor', 'approver_resolver' => 'supervisor_id', 'is_required' => false, 'can_skip_if_no_supervisor' => true],
                ['step_order' => 2, 'step_code' => 'hod', 'approver_role' => 'hod', 'approver_resolver' => 'hod_id', 'is_required' => true, 'can_skip_if_no_supervisor' => false],
                ['step_order' => 3, 'step_code' => 'pm', 'approver_role' => 'project_manager', 'approver_resolver' => 'assigned_pm', 'is_required' => true, 'can_skip_if_no_supervisor' => false],
                ['step_order' => 4, 'step_code' => 'hrga', 'approver_role' => 'hrga', 'approver_resolver' => 'role_users', 'is_required' => true, 'can_skip_if_no_supervisor' => false],
            ],
        ]);

        $response->assertRedirect(route('settings.workflows.index'));
        $this->assertSame('Perjalanan Dinas — Revisi', $workflow->fresh()->name);
        $this->assertSame(4, $workflow->fresh()->steps()->count());
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => ApprovalWorkflow::class,
            'subject_id' => $workflow->id,
            'description' => 'workflow.updated',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_workflow_settings_requires_permission_and_valid_step_configuration(): void
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        $workflow = ApprovalWorkflow::query()->where('code', 'leave_default')->firstOrFail();

        $this->actingAs($user)->get(route('settings.workflows.index'))->assertForbidden();

        $manager = User::factory()->create();
        $manager->assignRole('hrga_manager');
        $response = $this->actingAs($manager)->put(route('settings.workflows.update', $workflow), [
            'name' => 'Invalid',
            'is_active' => true,
            'steps' => [
                ['step_order' => 1, 'step_code' => 'supervisor', 'approver_role' => 'supervisor', 'approver_resolver' => 'not_supported', 'is_required' => true, 'can_skip_if_no_supervisor' => true],
                ['step_order' => 3, 'step_code' => 'hod', 'approver_role' => 'hod', 'approver_resolver' => 'hod_id', 'is_required' => true, 'can_skip_if_no_supervisor' => false],
            ],
        ]);

        $response->assertSessionHasErrors(['steps.0.approver_resolver', 'steps']);
        $this->assertSame('Persetujuan Cuti/Izin', $workflow->fresh()->name);
    }

    public function test_workflow_submenu_opens_the_selected_module_configuration(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('hrga_manager');

        $this->actingAs($manager)->get(route('settings.workflows.scope', 'leave'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Workflows/Index')
                ->where('scope', 'leave')
                ->has('workflows', 1)
                ->where('workflows.0.entity_type', 'leave_request'));
    }

    public function test_updating_definition_does_not_modify_existing_approval_chain(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('hrga_manager');
        $workflow = ApprovalWorkflow::query()->where('code', 'travel_default')->firstOrFail();
        $approval = ApprovalRequest::create([
            'approvable_type' => 'travel_request', 'approvable_id' => 999,
            'chain_generation' => 'existing-generation', 'workflow_id' => $workflow->id,
            'step_order' => 1, 'step_code' => 'supervisor', 'approver_role' => 'supervisor',
            'approver_user_id' => null, 'status' => 'pending',
        ]);

        $this->actingAs($manager)->put(route('settings.workflows.update', $workflow), [
            'name' => $workflow->name,
            'is_active' => true,
            'steps' => $workflow->steps->map(fn ($step) => [
                'step_order' => $step->step_order, 'step_code' => $step->step_code,
                'approver_role' => $step->approver_role, 'approver_resolver' => $step->approver_resolver,
                'is_required' => $step->is_required, 'can_skip_if_no_supervisor' => $step->can_skip_if_no_supervisor,
            ])->all(),
        ])->assertRedirect();

        $this->assertDatabaseHas('approval_requests', [
            'id' => $approval->id, 'workflow_id' => $workflow->id,
            'chain_generation' => 'existing-generation', 'status' => 'pending',
        ]);
    }
}
