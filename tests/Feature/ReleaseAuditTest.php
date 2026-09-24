<?php

namespace Tests\Feature;

use App\Enums\ApprovalStepStatus;
use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\AdvanceProcessing;
use App\Services\Approval\ApproveApprovalRequest;
use App\Services\Settlement\CreateSettlement;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ReleaseAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_reports_require_view_and_export_is_separate(): void
    {
        $viewOnly = User::factory()->create();
        $viewOnly->givePermissionTo('report.view');
        $this->actingAs($viewOnly)->get(route('reports.index'))->assertOk();

        $exportOnly = User::factory()->create();
        $exportOnly->givePermissionTo('report.export');
        $this->actingAs($exportOnly)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($viewOnly)->get(route('reports.index', ['export' => 1]))->assertForbidden();
    }

    public function test_report_export_contains_all_rows_using_chunked_query(): void
    {
        [$user, $employee] = $this->actor('hrga');
        $now = now();
        $rows = [];
        for ($i = 1; $i <= 1001; $i++) {
            $rows[] = [
                'request_number' => sprintf('DINAS-REPORT-%04d', $i), 'employee_id' => $employee->id,
                'purpose' => 'Audit', 'start_date' => '2026-09-01', 'end_date' => '2026-09-02',
                'origin' => 'A', 'destination' => 'B', 'status' => RequestStatus::Completed->value,
                'total_advance' => '10.00', 'submitted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('travel_requests')->insert($rows);

        $response = $this->actingAs($user)->get(route('reports.index', ['module' => 'travel', 'export' => 1]));
        $response->assertOk();
        $this->assertSame(1002, substr_count($response->streamedContent(), "\n"));
    }

    public function test_report_page_is_database_paginated_and_keeps_filters(): void
    {
        [$user, $employee] = $this->actor('hrga');
        $now = now();
        $rows = [];
        for ($i = 1; $i <= 1001; $i++) {
            $rows[] = [
                'request_number' => sprintf('DINAS-PAGE-%04d', $i), 'employee_id' => $employee->id,
                'purpose' => 'Audit', 'start_date' => '2026-09-01', 'end_date' => '2026-09-02',
                'origin' => 'A', 'destination' => 'B', 'status' => RequestStatus::Completed->value,
                'total_advance' => '10.00', 'submitted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('travel_requests')->insert($rows);

        $this->actingAs($user)->get(route('reports.index', ['module' => 'travel', 'status' => RequestStatus::Completed->value, 'per_page' => 25, 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.total', 1001)
                ->where('report.completed', 1001)
                ->has('report.data', 25)
                ->where('report.data.0.status', RequestStatus::Completed->value));
    }

    public function test_audit_timeline_uses_entity_policy_for_on_behalf_and_cross_owner_access(): void
    {
        [$creator, $target] = $this->actor('admin');
        [$other, $otherEmployee] = $this->actor('employee');
        $leave = $this->approvedLeave($target, $creator, '0.00');
        $travel = new TravelRequest;
        $travel->forceFill([
            'request_number' => 'DINAS-TIMELINE-001', 'employee_id' => $target->id, 'purpose' => 'Audit',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-02', 'origin' => 'A', 'destination' => 'B',
            'status' => RequestStatus::Draft, 'created_by' => $creator->id,
        ])->save();
        $claim = new MedicalClaim(['benefit_type' => 'rawat_jalan']);
        $claim->forceFill(['claim_number' => 'KLAIM-TIMELINE-001', 'employee_id' => $target->id, 'status' => RequestStatus::Draft, 'created_by' => $creator->id])->save();

        foreach ([['leave', $leave], ['travel', $travel], ['medical', $claim]] as [$module, $model]) {
            $this->actingAs($creator)->get(route('audit.timeline', [$module, $model->id]))->assertOk();
        }

        $foreignLeave = $this->approvedLeave($target, $creator, '0.00');
        $this->actingAs($other)->get(route('audit.timeline', ['leave', $foreignLeave->id]))->assertForbidden();
    }

    public function test_advance_requires_active_operational_actor_and_audits_paid_once(): void
    {
        [$hrga, $employee] = $this->actor('hrga');
        $leave = $this->approvedLeave($employee, $hrga, '25.00');

        $processed = AdvanceProcessing::run($leave, $hrga);
        $this->assertSame(RequestStatus::SettlementRequired, $processed->status);
        $this->assertSame(1, Activity::where('subject_type', $leave->getMorphClass())->where('subject_id', $leave->id)->where('description', 'advance.paid')->count());
        $this->assertDatabaseHas('activity_log', ['description' => 'advance.processing']);
        $this->assertDatabaseHas('activity_log', ['description' => 'advance.settlement_required']);

        $hrga->forceFill(['active' => false])->save();
        $next = $this->approvedLeave($employee, $hrga, '25.00');
        $this->expectException(AuthorizationException::class);
        AdvanceProcessing::run($next, $hrga);
    }

    public function test_approval_to_advance_to_eligible_travel_settlement_is_atomic(): void
    {
        [$owner, $employee] = $this->actor('employee');
        [$hrga, $hrgaEmployee] = $this->actor('hrga');
        $travel = new TravelRequest;
        $travel->forceFill([
            'request_number' => 'DINAS-E2E-001', 'employee_id' => $employee->id, 'purpose' => 'Audit',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-02', 'origin' => 'A', 'destination' => 'B',
            'status' => RequestStatus::InReview, 'total_advance' => '100.00', 'created_by' => $owner->id,
            'employee_snapshot_json' => ['approval_snapshot' => []],
        ])->save();
        $approval = ApprovalRequest::create([
            'approvable_type' => $travel->getMorphClass(), 'approvable_id' => $travel->id, 'chain_generation' => 'e2e',
            'step_order' => 1, 'step_code' => 'hrga', 'approver_role' => 'hrga', 'approver_user_id' => $hrga->id,
            'status' => ApprovalStepStatus::Pending,
        ]);

        ApproveApprovalRequest::run($approval, $hrga);
        $travel = $travel->refresh();
        $this->assertSame(RequestStatus::Approved, $travel->status);
        AdvanceProcessing::run($travel, $hrga);
        $this->assertSame(RequestStatus::SettlementRequired, $travel->fresh()->status);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $owner);
        $this->assertSame('100.00', (string) $settlement->advance_amount);
        $this->assertSame($employee->id, $settlement->employee_id);
        $this->assertTrue($hrgaEmployee->fresh()->active);
    }

    public function test_auditor_report_and_dashboard_do_not_expose_medical_detail(): void
    {
        [$owner, $employee] = $this->actor('employee');
        $claim = new MedicalClaim(['benefit_type' => 'rawat_jalan']);
        $claim->forceFill(['claim_number' => 'KLAIM-AUDIT-001', 'employee_id' => $employee->id, 'employee_name' => $employee->name, 'status' => RequestStatus::Completed, 'total_amount' => 900, 'submitted_at' => now(), 'created_by' => $owner->id])->save();
        $claim->items()->create(['patient_name' => 'Secret', 'relationship' => 'self', 'treatment_date' => '2026-09-01', 'facility_name' => 'Secret Clinic', 'diagnosis_code' => 'SECRET', 'amount' => 900]);
        [$auditor] = $this->actor('auditor');

        $this->actingAs($auditor)->get(route('reports.index', ['module' => 'medical']))->assertInertia(fn (Assert $page) => $page
            ->where('report.data.0.employee_id', null)->where('report.data.0.amount', null));
        $this->actingAs($auditor)->get(route('reports.dashboard'))->assertJsonPath('summary.medical.completed', 1);
    }

    public function test_finance_cannot_process_advance_and_zero_advance_completes(): void
    {
        [$finance, $employee] = $this->actor('finance');
        $leave = $this->approvedLeave($employee, $finance, '25.00');
        try {
            AdvanceProcessing::run($leave, $finance);
            $this->fail('Finance must not process advance.');
        } catch (AuthorizationException) {
            $this->assertSame(RequestStatus::Approved, $leave->fresh()->status);
        }

        [$hrga, $hrgaEmployee] = $this->actor('hrga');
        $zero = $this->approvedLeave($hrgaEmployee, $hrga, '0.00');
        $result = AdvanceProcessing::run($zero, $hrga);
        $this->assertSame(RequestStatus::Completed, $result->status);
        $this->assertSame(1, Activity::where('subject_type', $zero->getMorphClass())->where('subject_id', $zero->id)->where('description', 'advance.paid')->count());
    }

    public function test_medical_edit_dto_is_whitelisted(): void
    {
        [$owner, $employee] = $this->actor('employee');
        $claim = new MedicalClaim(['benefit_type' => 'rawat_jalan']);
        $claim->forceFill(['claim_number' => 'KLAIM-EDIT-001', 'employee_id' => $employee->id, 'status' => RequestStatus::Draft, 'created_by' => $owner->id])->save();
        $claim->items()->create(['patient_name' => $employee->name, 'relationship' => 'self', 'treatment_date' => '2026-09-01', 'facility_name' => 'Klinik', 'diagnosis_code' => 'SECRET', 'amount' => 10]);

        $this->actingAs($owner)->get(route('medical-claims.edit', $claim))->assertInertia(fn (Assert $page) => $page
            ->has('claim.items.0')
            ->missing('claim.items.0.created_at')
            ->missing('claim.items.0.diagnosis_code')
            ->where('claim.items.0.patient_name', $employee->name));
    }

    public function test_leave_attachment_is_private_and_scoped_to_owner_or_assignee(): void
    {
        Storage::fake('eform-private');
        [$owner, $employee] = $this->actor('employee');
        $leave = $this->approvedLeave($employee, $owner, '0.00');
        $leave->forceFill(['status' => RequestStatus::Draft])->save();

        $file = UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf');
        $this->actingAs($owner)->post(route('leaves.attachments.store', $leave), ['file' => $file, 'document_type' => 'supporting_document'])->assertRedirect();
        $attachment = $leave->attachments()->firstOrFail();
        $this->assertStringStartsWith('leave/', $attachment->stored_path);
        Storage::disk('eform-private')->assertExists($attachment->stored_path);

        [$other] = $this->actor('employee');
        $this->actingAs($other)->get(route('attachments.download', $attachment))->assertForbidden();

        [$auditor] = $this->actor('auditor');
        ApprovalRequest::create(['approvable_type' => $leave->getMorphClass(), 'approvable_id' => $leave->id, 'chain_generation' => 'audit', 'step_order' => 1, 'step_code' => 'audit', 'approver_role' => 'auditor', 'approver_user_id' => $auditor->id, 'status' => ApprovalStepStatus::Pending]);
        $this->actingAs($auditor)->get(route('attachments.download', $attachment))->assertOk();
        $leave->forceFill(['status' => RequestStatus::Approved])->save();
        $this->actingAs($owner)->post(route('leaves.attachments.store', $leave), ['file' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'), 'document_type' => 'receipt'])->assertForbidden();
    }

    private function actor(string $role): array
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'active' => true]);

        return [$user, $employee];
    }

    private function approvedLeave(Employee $employee, User $creator, string $advance): LeaveRequest
    {
        $leave = new LeaveRequest;
        $leave->forceFill(['request_number' => 'CUTI-AUDIT-'.uniqid(), 'employee_id' => $employee->id, 'leave_type' => 'annual_leave', 'status' => RequestStatus::Approved, 'total_advance' => $advance, 'created_by' => $creator->id, 'updated_by' => $creator->id, 'approved_at' => now()])->save();

        return $leave;
    }
}
