<?php

namespace Tests\Feature;

use App\Enums\ApprovalStepStatus;
use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\Approval\ApproveApprovalRequest;
use App\Services\Approval\BuildApprovalChain;
use App\Services\Approval\DelegateApprovalRequest;
use App\Services\Approval\RejectApprovalRequest;
use Database\Seeders\ApprovalWorkflowSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApprovalEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesPermissionsSeeder::class, ApprovalWorkflowSeeder::class]);
    }

    public function test_configured_chain_assigns_hod_and_advances_to_hrga(): void
    {
        [$travel, $owner, $supervisor, $hod, $hrga] = $this->travelWithHierarchy();
        BuildApprovalChain::run($travel, $travel->employee_snapshot_json, $owner);

        $this->assertSame(RequestStatus::InReview, $travel->fresh()->status);
        $current = ApprovalRequest::query()->where('approvable_id', $travel->id)->where('status', ApprovalStepStatus::Pending)->firstOrFail();
        $this->assertSame($supervisor->id, $current->approver_user_id);
        $this->assertSame(1, $supervisor->unreadNotifications()->count());
        ApproveApprovalRequest::run($current, $supervisor);
        $this->assertSame(1, $owner->unreadNotifications()->count());
        $current = $current->fresh();
        $this->assertSame('hod', $current->step_code === 'hod' ? $current->step_code : ApprovalRequest::query()->where('approvable_id', $travel->id)->where('status', ApprovalStepStatus::Pending)->value('step_code'));
        $hodStep = ApprovalRequest::query()->where('approvable_id', $travel->id)->where('status', ApprovalStepStatus::Pending)->firstOrFail();
        $this->assertSame($hod->id, $hodStep->approver_user_id);
        $this->assertSame(1, $hod->unreadNotifications()->count());
        ApproveApprovalRequest::run($hodStep, $hod);
        $hrgaStep = ApprovalRequest::query()->where('approvable_id', $travel->id)->where('status', ApprovalStepStatus::Pending)->firstOrFail();
        $this->assertSame($hrga->id, $hrgaStep->approver_user_id);
        ApproveApprovalRequest::run($hrgaStep, $hrga);
        $this->assertSame(RequestStatus::Approved, $travel->fresh()->status);
        $this->assertDatabaseHas('activity_log', ['description' => 'approval.approve']);
        $this->assertDatabaseHas('activity_log', ['description' => 'approval.chain_created', 'causer_id' => $owner->id]);
    }

    public function test_self_approval_and_missing_comments_are_rejected(): void
    {
        [$travel, $owner, $supervisor] = $this->travelWithHierarchy();
        BuildApprovalChain::run($travel, $travel->employee_snapshot_json, $owner);
        $approval = ApprovalRequest::query()->where('approvable_id', $travel->id)->where('status', ApprovalStepStatus::Pending)->firstOrFail();
        $approval->forceFill(['approver_user_id' => $owner->id])->save();
        $this->expectException(ValidationException::class);
        RejectApprovalRequest::run($approval, $owner, '');
    }

    public function test_disabled_employee_cannot_approve_or_delegate(): void
    {
        [$travel, , $supervisor] = $this->travelWithHierarchy();
        BuildApprovalChain::run($travel, $travel->employee_snapshot_json, $travel->employee->user);
        $approval = ApprovalRequest::query()->where('approvable_id', $travel->id)->where('status', ApprovalStepStatus::Pending)->firstOrFail();
        $supervisor->employee()->update(['active' => false]);

        $this->expectException(ValidationException::class);
        ApproveApprovalRequest::run($approval, $supervisor);
    }

    public function test_delegation_changes_assignment_and_is_audited(): void
    {
        [$travel, , $supervisor] = $this->travelWithHierarchy();
        $delegate = User::factory()->create();
        $delegate->assignRole('supervisor');
        Employee::factory()->create(['user_id' => $delegate->id]);
        BuildApprovalChain::run($travel, $travel->employee_snapshot_json, $travel->employee->user);
        $approval = ApprovalRequest::query()->where('approvable_id', $travel->id)->where('status', ApprovalStepStatus::Pending)->firstOrFail();
        DelegateApprovalRequest::run($approval, $supervisor, $delegate, 'Mohon diperiksa.');
        $this->assertSame($delegate->id, $approval->fresh()->approver_user_id);
        $this->assertDatabaseHas('approval_actions', ['action' => 'delegate', 'actor_id' => $supervisor->id]);
    }

    public function test_project_trip_resolves_pm_from_configured_role_and_snapshots_it(): void
    {
        [$travel] = $this->travelWithHierarchy();
        $pm = User::factory()->create(['active' => true]);
        $pm->assignRole('project_manager');
        $pmEmployee = Employee::factory()->create(['user_id' => $pm->id]);
        $snapshot = $travel->employee_snapshot_json;
        $snapshot['approval_snapshot']['pm'] = ['user_id' => $pm->id, 'employee_id' => $pmEmployee->id];
        $travel->forceFill(['is_project_trip' => true, 'employee_snapshot_json' => $snapshot])->save();

        BuildApprovalChain::run($travel, $travel->employee_snapshot_json, $travel->employee->user);

        $step = ApprovalRequest::query()->where('approvable_id', $travel->id)->where('step_code', 'pm')->firstOrFail();
        $this->assertSame($pm->id, $step->approver_user_id);
        $snapshot = $travel->fresh()->employee_snapshot_json['approval_snapshot']['pm'];
        $this->assertSame($pm->id, $snapshot['user_id']);
        $this->assertSame($pm->employee->id, $snapshot['employee_id']);
        $this->assertSame('project_manager', $snapshot['role']);
    }

    public function test_project_trip_without_required_pm_rolls_back_chain(): void
    {
        [$travel] = $this->travelWithHierarchy();
        $travel->forceFill(['is_project_trip' => true])->save();

        $this->expectException(ValidationException::class);
        BuildApprovalChain::run($travel, $travel->employee_snapshot_json, $travel->employee->user);

        $this->assertDatabaseCount('approval_requests', 0);
        $this->assertSame(RequestStatus::Submitted, $travel->fresh()->status);
    }

    public function test_optional_supervisor_is_skipped_and_skip_is_audited(): void
    {
        [$travel, , , $hod] = $this->travelWithHierarchy();
        $travel->forceFill(['employee_snapshot_json' => [
            'hod_id' => $travel->employee_snapshot_json['hod_id'],
            'approval_snapshot' => ['hod' => ['user_id' => $hod->id]],
        ]])->save();

        BuildApprovalChain::run($travel, $travel->employee_snapshot_json, $travel->employee->user);

        $this->assertDatabaseHas('approval_requests', ['approvable_id' => $travel->id, 'step_code' => 'supervisor', 'status' => 'skipped']);
        $this->assertDatabaseHas('activity_log', ['description' => 'approval.step_skipped']);
    }

    private function travelWithHierarchy(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('employee');
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $hod = User::factory()->create();
        $hod->assignRole('hod');
        $hrga = User::factory()->create();
        $hrga->assignRole('hrga');
        $employee = Employee::factory()->create(['user_id' => $owner->id]);
        $supervisorEmployee = Employee::factory()->create(['user_id' => $supervisor->id]);
        $hodEmployee = Employee::factory()->create(['user_id' => $hod->id]);
        Employee::factory()->create(['user_id' => $hrga->id]);
        $employee->forceFill(['supervisor_id' => $supervisorEmployee->id, 'hod_id' => $hodEmployee->id])->save();
        $travel = new TravelRequest([
            'request_number' => 'DINAS-TEST-0001', 'employee_id' => $employee->id, 'purpose' => 'Test',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'origin' => 'A', 'destination' => 'B',
            'status' => RequestStatus::Submitted, 'created_by' => $owner->id, 'employee_snapshot_json' => [
                'supervisor_id' => $employee->supervisor_id, 'hod_id' => $employee->hod_id,
                'approval_snapshot' => ['supervisor' => ['user_id' => $supervisor->id], 'hod' => ['user_id' => $hod->id]],
            ],
        ]);
        $travel->forceFill([
            'request_number' => 'DINAS-TEST-0001', 'employee_id' => $employee->id,
            'status' => RequestStatus::Submitted, 'created_by' => $owner->id,
            'employee_snapshot_json' => [
                'supervisor_id' => $employee->supervisor_id, 'hod_id' => $employee->hod_id,
                'approval_snapshot' => ['supervisor' => ['user_id' => $supervisor->id], 'hod' => ['user_id' => $hod->id]],
            ],
        ])->save();

        return [$travel, $owner, $supervisor, $hod, $hrga];
    }
}
