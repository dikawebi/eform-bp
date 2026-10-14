<?php

namespace Tests\Feature;

use App\Enums\ApprovalStepStatus;
use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\ErpRequest;
use App\Models\ItRequest;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\SitePmGmAssignment;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\AdvanceProcessing;
use App\Services\Settlement\CreateSettlement;
use App\Services\Settlement\SubmitSettlement;
use Database\Seeders\ApprovalWorkflowSeeder;
use Database\Seeders\ItItemOptionSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class WithdrawRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolesPermissionsSeeder::class, ApprovalWorkflowSeeder::class, ItItemOptionSeeder::class]);
    }

    public function test_owner_can_withdraw_leave_mid_review_and_history_is_kept(): void
    {
        [$owner, $hodUser, $hrgaUser] = $this->leaveActors();

        $this->actingAs($owner)->post(route('leaves.store'), $this->leavePayload())->assertRedirect();
        $leave = LeaveRequest::query()->firstOrFail();
        $this->actingAs($owner)->post(route('leaves.submit', $leave))->assertRedirect();

        $hod = $leave->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $this->actingAs($hodUser)->post(route('approvals.action', [$hod, 'approve']))->assertRedirect();

        $this->actingAs($owner)->post(route('leaves.cancel', $leave), ['reason' => 'Jadwal berubah'])->assertRedirect();

        $this->assertSame(RequestStatus::Cancelled, $leave->refresh()->status);
        // Persetujuan HOD dipertahankan sebagai riwayat.
        $this->assertSame(ApprovalStepStatus::Approved, $leave->approvalRequests()->where('step_code', 'hod')->firstOrFail()->status);
        $hrgaStep = $leave->approvalRequests()->where('step_code', 'hrga')->firstOrFail();
        $this->assertSame(ApprovalStepStatus::Cancelled, $hrgaStep->status);
        $this->assertStringContainsString('Ditarik kembali', (string) $hrgaStep->comments);

        // Approver aktif diberi tahu dan diarahkan ke data pending.
        $this->assertTrue($hrgaUser->notifications()->where('data->event', 'approval.withdrawn')->exists());

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'leave_request', 'subject_id' => $leave->id, 'description' => 'approval.chain_withdrawn',
        ]);
        $logged = Activity::query()->where('subject_type', 'leave_request')->where('subject_id', $leave->id)->where('description', 'leave.cancelled')->firstOrFail();
        $this->assertTrue((bool) data_get($logged->properties, 'withdrawn_from_review'));
        $this->assertSame(1, (int) data_get($logged->properties, 'approved_count'));
        $this->assertSame('Jadwal berubah', data_get($logged->properties, 'reason'));
    }

    public function test_owner_can_withdraw_leave_after_full_approval(): void
    {
        [$owner, $hodUser, $hrgaUser] = $this->leaveActors();

        $this->actingAs($owner)->post(route('leaves.store'), $this->leavePayload())->assertRedirect();
        $leave = LeaveRequest::query()->firstOrFail();
        $this->actingAs($owner)->post(route('leaves.submit', $leave))->assertRedirect();

        $hod = $leave->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $this->actingAs($hodUser)->post(route('approvals.action', [$hod, 'approve']))->assertRedirect();
        $hrga = $leave->approvalRequests()->where('step_code', 'hrga')->firstOrFail();
        $this->actingAs($hrgaUser)->post(route('approvals.action', [$hrga, 'approve']))->assertRedirect();
        $this->assertSame(RequestStatus::Approved, $leave->refresh()->status);

        $this->actingAs($owner)->post(route('leaves.cancel', $leave))->assertRedirect();
        $this->assertSame(RequestStatus::Cancelled, $leave->refresh()->status);
    }

    public function test_withdraw_is_blocked_after_advance_paid(): void
    {
        [$owner, $hodUser, $hrgaUser] = $this->travelActors();

        $this->actingAs($owner)->post(route('travels.store'), $this->travelPayload())->assertRedirect();
        $travel = TravelRequest::query()->firstOrFail();
        $this->actingAs($owner)->post(route('travels.submit', $travel))->assertRedirect();

        $hod = $travel->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $this->actingAs($hodUser)->post(route('approvals.action', [$hod, 'approve']))->assertRedirect();
        $hrga = $travel->approvalRequests()->where('step_code', 'hrga')->firstOrFail();
        $this->actingAs($hrgaUser)->post(route('approvals.action', [$hrga, 'approve']))->assertRedirect();

        AdvanceProcessing::run($travel->refresh(), $hrgaUser->refresh());
        $this->assertSame(RequestStatus::SettlementRequired, $travel->refresh()->status);

        $this->actingAs($owner)->post(route('travels.cancel', $travel))->assertForbidden();
        $this->assertSame(RequestStatus::SettlementRequired, $travel->refresh()->status);
    }

    public function test_settlement_withdraw_mid_review_and_payment_blocked(): void
    {
        $owner = $this->approver('employee');
        $employee = Employee::query()->where('user_id', $owner->id)->firstOrFail();
        $this->approver('hrga');
        $this->approver('finance');

        $source = TravelRequest::forceCreate([
            'employee_id' => $employee->id, 'request_number' => 'DINAS-WD', 'purpose' => 'Test',
            'start_date' => '2026-09-23', 'end_date' => '2026-09-24', 'origin' => 'A', 'destination' => 'B',
            'status' => RequestStatus::SettlementRequired, 'total_advance' => '100.00', 'created_by' => $owner->id,
        ]);
        $settlement = CreateSettlement::run('travel_request', $source->id, $owner, [
            ['transaction_date' => '2026-09-23', 'description' => 'Nota', 'category' => 'meal', 'amount' => '30.00'],
        ]);
        SubmitSettlement::run($settlement, $owner);

        $this->actingAs($owner)->post(route('settlements.cancel', $settlement))->assertRedirect();
        $this->assertSame(RequestStatus::Cancelled, $settlement->refresh()->status);
        $this->assertNull($settlement->refresh()->source_key);

        $source2 = TravelRequest::forceCreate([
            'employee_id' => $employee->id, 'request_number' => 'DINAS-WD2', 'purpose' => 'Test',
            'start_date' => '2026-09-23', 'end_date' => '2026-09-24', 'origin' => 'A', 'destination' => 'B',
            'status' => RequestStatus::SettlementRequired, 'total_advance' => '100.00', 'created_by' => $owner->id,
        ]);
        $paid = CreateSettlement::run('travel_request', $source2->id, $owner, [
            ['transaction_date' => '2026-09-23', 'description' => 'Nota', 'category' => 'meal', 'amount' => '30.00'],
        ]);
        $paid->forceFill(['status' => RequestStatus::PaymentProcessing])->save();

        $this->actingAs($owner)->post(route('settlements.cancel', $paid))->assertForbidden();
    }

    public function test_medical_withdraw_approved_and_payment_blocked(): void
    {
        $owner = $this->approver('employee');
        $employee = Employee::query()->where('user_id', $owner->id)->firstOrFail();
        $hrga = $this->approver('hrga');

        $claim = MedicalClaim::forceCreate([
            'claim_number' => 'MED-WD', 'employee_id' => $employee->id, 'benefit_type' => 'rawat_jalan',
            'status' => RequestStatus::InReview, 'created_by' => $owner->id,
        ]);
        ApprovalRequest::forceCreate([
            'approvable_type' => 'medical_claim', 'approvable_id' => $claim->id,
            'chain_generation' => (string) Str::uuid(), 'step_order' => 1, 'step_code' => 'hrga',
            'approver_role' => 'hrga', 'approver_user_id' => $hrga->id, 'status' => ApprovalStepStatus::Pending,
        ]);

        $this->actingAs($owner)->post(route('medical-claims.cancel', $claim))->assertRedirect();
        $this->assertSame(RequestStatus::Cancelled, $claim->refresh()->status);
        $this->assertTrue($hrga->notifications()->where('data->event', 'approval.withdrawn')->exists());

        $paid = MedicalClaim::forceCreate([
            'claim_number' => 'MED-WD2', 'employee_id' => $employee->id, 'benefit_type' => 'rawat_jalan',
            'status' => RequestStatus::PaymentProcessing, 'created_by' => $owner->id,
        ]);
        $this->actingAs($owner)->post(route('medical-claims.cancel', $paid))->assertForbidden();
    }

    public function test_it_withdraw_mid_review_and_completed_blocked(): void
    {
        [$owner, $employee, $hodUser, $itUser, $pmUser] = $this->itActors();

        $this->actingAs($owner)->post(route('it-requests.store'), $this->itPayload($employee))->assertRedirect();
        $itRequest = ItRequest::query()->firstOrFail();
        $this->actingAs($owner)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();

        $hod = $itRequest->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $this->actingAs($hodUser)->post(route('approvals.action', [$hod, 'approve']))->assertRedirect();

        $this->actingAs($owner)->post(route('it-requests.cancel', $itRequest))->assertRedirect();
        $this->assertSame(RequestStatus::Cancelled, $itRequest->refresh()->status);
        $this->assertTrue($itUser->notifications()->where('data->event', 'approval.withdrawn')->exists());

        $itRequest->forceFill(['status' => RequestStatus::Completed])->save();
        $this->actingAs($owner)->post(route('it-requests.cancel', $itRequest))->assertForbidden();
    }

    public function test_erp_withdraw_mid_review(): void
    {
        [$owner, $employee, $hodUser] = $this->erpActors();

        $this->actingAs($owner)->post(route('erp-requests.store'), $this->erpPayload($employee))->assertRedirect();
        $erp = ErpRequest::query()->firstOrFail();
        $this->actingAs($owner)->post(route('erp-requests.submit', $erp))->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('erp-requests.cancel', $erp))->assertRedirect();
        $this->assertSame(RequestStatus::Cancelled, $erp->refresh()->status);
        $this->assertTrue($hodUser->notifications()->where('data->event', 'approval.withdrawn')->exists());
    }

    public function test_non_owner_cannot_withdraw(): void
    {
        [$owner] = $this->leaveActors();
        [$stranger] = $this->leaveActors();

        $this->actingAs($owner)->post(route('leaves.store'), $this->leavePayload())->assertRedirect();
        $leave = LeaveRequest::query()->firstOrFail();
        $this->actingAs($owner)->post(route('leaves.submit', $leave))->assertRedirect();

        $this->actingAs($stranger)->post(route('leaves.cancel', $leave))->assertForbidden();
        $this->assertSame(RequestStatus::InReview, $leave->refresh()->status);
    }

    /** @return array{0:User,1:User,2:User} */
    private function leaveActors(): array
    {
        $owner = $this->approver('employee');
        $hodUser = $this->approver('hod');
        $hod = Employee::query()->where('user_id', $hodUser->id)->firstOrFail();
        Employee::query()->where('user_id', $owner->id)->firstOrFail()
            ->forceFill(['poh_status' => 'non_local', 'hod_id' => $hod->id])->save();
        $hrga = $this->approver('hrga');

        return [$owner, $hodUser, $hrga];
    }

    /** @return array{0:User,1:User,2:User} */
    private function travelActors(): array
    {
        $owner = $this->approver('employee');
        $hodUser = $this->approver('hod');
        $hod = Employee::query()->where('user_id', $hodUser->id)->firstOrFail();
        Employee::query()->where('user_id', $owner->id)->firstOrFail()
            ->forceFill(['hod_id' => $hod->id])->save();
        $hrga = $this->approver('hrga');

        return [$owner, $hodUser, $hrga];
    }

    private function approver(string $role): User
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);
        Employee::factory()->create(['user_id' => $user->id, 'active' => true]);

        return $user->refresh();
    }

    private function leavePayload(): array
    {
        return [
            'leave_type' => 'annual_leave', 'reason' => 'Pulang ke rumah',
            'last_working_date' => '2026-09-20', 'onsite_date' => '2026-10-10',
            'periods' => [['category' => 'travel_home', 'start_date' => '2026-09-21', 'end_date' => '2026-09-23', 'notes' => null]],
            'cost_items' => [],
        ];
    }

    private function travelPayload(): array
    {
        return [
            'purpose' => 'Audit site', 'start_date' => '2026-10-01', 'end_date' => '2026-10-03',
            'origin' => 'Balikpapan', 'destination' => 'Site', 'is_project_trip' => false,
            'items' => [
                ['category' => 'land_transport', 'description' => 'Travel darat', 'quantity' => 2, 'unit_price' => 150000],
                ['category' => 'meal', 'description' => 'Makan', 'quantity' => 3, 'unit_price' => 50000],
            ],
        ];
    }

    /** @return array{0:User,1:Employee,2:User,3:User,4:User} */
    private function itActors(): array
    {
        $owner = $this->approver('employee');
        $employee = Employee::query()->where('user_id', $owner->id)->firstOrFail();
        $hodUser = $this->approver('hod');
        $hod = Employee::query()->where('user_id', $hodUser->id)->firstOrFail();
        $itUser = $this->approver('it');
        $pmUser = $this->approver('project_manager');
        $employee->forceFill(['site' => 'Site-WD', 'hod_id' => $hod->id])->save();
        SitePmGmAssignment::create(['site' => 'Site-WD', 'pm_gm_user_id' => $pmUser->id, 'is_active' => true]);

        return [$owner, $employee, $hodUser, $itUser, $pmUser];
    }

    /** @return array{0:User,1:Employee,2:User} */
    private function erpActors(): array
    {
        $owner = $this->approver('employee');
        $employee = Employee::query()->where('user_id', $owner->id)->firstOrFail();
        $hodUser = $this->approver('hod');
        $hod = Employee::query()->where('user_id', $hodUser->id)->firstOrFail();
        $reviewer = $this->approver('erp_reviewer');
        $itUser = $this->approver('it');
        $pmUser = $this->approver('project_manager');
        $employee->forceFill(['site' => 'Site-WDERP', 'hod_id' => $hod->id])->save();
        SitePmGmAssignment::create(['site' => 'Site-WDERP', 'pm_gm_user_id' => $pmUser->id, 'is_active' => true]);

        return [$owner, $employee, $hodUser, $reviewer, $itUser, $pmUser];
    }

    private function itPayload(Employee $employee): array
    {
        return [
            'employee_id' => $employee->id, 'request_type' => 'new_item', 'device_type' => 'laptop',
            'needed_date' => now()->addWeek()->toDateString(), 'priority' => 'normal',
        ];
    }

    private function erpPayload(Employee $employee): array
    {
        return [
            'employee_id' => $employee->id, 'action_type' => 'new_account',
            'business_purpose' => 'Input PO pembelian solar site.', 'modules' => ['supply_chain_procurement'],
        ];
    }
}
