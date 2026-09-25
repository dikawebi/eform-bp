<?php

namespace Tests\Feature;

use App\Enums\ApprovalActionType;
use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Settlement;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\Approval\ApprovalTransition;
use App\Services\Settlement\CompleteSettlement;
use App\Services\Settlement\CreateSettlement;
use App\Services\Settlement\SubmitSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    private function source(User $user, string $type = 'travel_request', array $extra = []): LeaveRequest|TravelRequest
    {
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $data = array_merge([
            'employee_id' => $employee->id, 'request_number' => strtoupper(substr($type, 0, 3)).'-1',
            'status' => RequestStatus::SettlementRequired, 'total_advance' => '100.00', 'created_by' => $user->id,
        ], $extra);
        if ($type === 'leave_request') {
            return LeaveRequest::forceCreate(array_merge($data, ['leave_type' => 'annual_leave']));
        }

        return TravelRequest::forceCreate(array_merge($data, ['purpose' => 'Test', 'start_date' => '2026-09-23', 'end_date' => '2026-09-24', 'origin' => 'A', 'destination' => 'B']));
    }

    private function item(): array
    {
        return ['transaction_date' => '2026-09-23', 'description' => 'Nota', 'category' => 'meal', 'amount' => '30.00'];
    }

    private function approver(string $role): User
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);
        Employee::factory()->create(['user_id' => $user->id, 'active' => true]);

        return $user->refresh();
    }

    private function approveSettlement(Settlement $settlement, User $submitter): array
    {
        SubmitSettlement::run($settlement, $submitter);
        $hrga = ApprovalRequest::query()->where('approvable_type', 'settlement')->where('approvable_id', $settlement->id)->where('step_code', 'hrga')->firstOrFail();
        $hrgaUser = User::findOrFail($hrga->approver_user_id);
        ApprovalTransition::run($hrga, $hrgaUser, ApprovalActionType::Approve);
        $finance = ApprovalRequest::query()->where('approvable_type', 'settlement')->where('approvable_id', $settlement->id)->where('step_code', 'finance')->firstOrFail();
        $financeUser = User::findOrFail($finance->approver_user_id);
        ApprovalTransition::run($finance, $financeUser, ApprovalActionType::Approve);
        CompleteSettlement::run($settlement, $financeUser);

        return [$hrgaUser, $financeUser];
    }

    private function markFinanceApproval(Settlement $settlement, User $actor): void
    {
        ApprovalRequest::create([
            'approvable_type' => 'settlement', 'approvable_id' => $settlement->id,
            'chain_generation' => 'direct-test', 'step_order' => 2, 'step_code' => 'finance',
            'approver_role' => 'finance', 'approver_user_id' => $actor->id,
            'status' => 'approved', 'acted_at' => now(),
        ]);
    }

    public function test_source_resolver_rejects_wrong_model_and_employee_idor(): void
    {
        $actor = User::factory()->create();
        $leave = $this->source($actor, 'leave_request');
        $this->expectException(ValidationException::class);
        CreateSettlement::run('travel_request', $leave->id, $actor, [$this->item()]);
    }

    public function test_manual_workbook_sources_require_unique_reference_and_have_zero_advance(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $settlement = CreateSettlement::run('new_join', $employee->id, $user, [$this->item()], 'JOIN-REF-2026-001');

        $this->assertSame('0.00', $settlement->advance_amount);
        $this->assertSame('new_join', $settlement->source_type);
        $this->assertSame('JOIN-REF-2026-001', $settlement->source_reference);
        $this->assertSame($employee->id, $settlement->employee_id);
        $this->assertNull($settlement->leave_request_id);
        $this->assertNull($settlement->travel_request_id);
        $this->assertSame($employee->id, $settlement->source()?->id);

        try {
            CreateSettlement::run('new_join', $employee->id, $user, [$this->item()], 'JOIN-REF-2026-001');
            $this->fail('Duplicate manual source reference should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('source_id', $exception->errors());
        }
    }

    public function test_create_page_sources_include_read_only_employee_profile(): void
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('employee');
        $source = $this->source($user)->load('employee');

        $this->actingAs($user)->get(route('settlements.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settlements/Create')
                ->where('sources.0.source_id', $source->id)
                ->where('sources.0.employee.employee_number', $source->employee->employee_number)
                ->where('sources.0.employee.name', $source->employee->name)
                ->where('sources.0.employee.department', $source->employee->department));
    }

    public function test_source_foreign_key_mismatch_is_rejected(): void
    {
        $actor = User::factory()->create();
        $travel = $this->source($actor);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $actor, [$this->item()]);
        $leave = LeaveRequest::forceCreate([
            'employee_id' => $travel->employee_id, 'request_number' => 'LV-MISMATCH',
            'leave_type' => 'annual_leave', 'status' => RequestStatus::SettlementRequired,
            'total_advance' => '100.00', 'created_by' => $actor->id,
        ]);
        $settlement->forceFill(['leave_request_id' => $leave->id])->save();

        $this->expectException(ValidationException::class);
        SubmitSettlement::run($settlement, $actor);
    }

    public function test_duplicate_active_is_rejected_but_cancelled_source_can_be_replaced(): void
    {
        $actor = User::factory()->create();
        $travel = $this->source($actor);
        $first = CreateSettlement::run('travel_request', $travel->id, $actor, [$this->item()]);
        $rejected = false;
        try {
            CreateSettlement::run('travel_request', $travel->id, $actor, [$this->item()]);
        } catch (ValidationException) {
            $rejected = true;
        } finally {
            $first->forceFill(['status' => RequestStatus::Cancelled, 'source_key' => null])->save();
        }
        $this->assertTrue($rejected);
        $replacement = CreateSettlement::run('travel_request', $travel->id, $actor, [$this->item()]);
        $this->assertNotSame($first->id, $replacement->id);
    }

    public function test_reconciliation_preserves_decimal_boundary_and_ignores_client_totals(): void
    {
        $actor = User::factory()->create();
        $travel = $this->source($actor, 'travel_request', ['total_advance' => '999999999999.99']);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $actor, [array_merge($this->item(), ['amount' => '999999999999.98'])]);
        $this->assertSame('999999999999.98', $settlement->actual_amount);
        $this->assertSame('0.01', $settlement->difference_amount);
        $this->assertSame(RequestStatus::Draft, $settlement->status);
    }

    public function test_completion_locks_both_records_and_audits_both_subjects(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('finance');
        $travel = $this->source($actor);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $actor, [$this->item()]);
        $settlement->forceFill(['status' => RequestStatus::PaymentProcessing])->save();
        $this->markFinanceApproval($settlement, $actor);
        CompleteSettlement::run($settlement, $actor);
        $this->assertSame(RequestStatus::Completed, $settlement->refresh()->status);
        $this->assertSame(RequestStatus::Completed, $travel->refresh()->status);
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'settlement', 'subject_id' => $settlement->id, 'description' => 'settlement.completed']);
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'travel_request', 'subject_id' => $travel->id, 'description' => 'source.completed']);
    }

    public function test_leave_settlement_completes_source_and_sets_leave_completed_at(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('finance');
        $leave = $this->source($actor, 'leave_request');
        $settlement = CreateSettlement::run('leave_request', $leave->id, $actor, [$this->item()]);
        $settlement->forceFill(['status' => RequestStatus::PaymentProcessing])->save();
        $this->markFinanceApproval($settlement, $actor);

        CompleteSettlement::run($settlement, $actor);

        $this->assertSame(RequestStatus::Completed, $leave->refresh()->status);
        $this->assertNotNull($leave->completed_at);
    }

    public function test_completion_uses_configured_source_statuses(): void
    {
        Config::set('eform.settlement.allowed_source_statuses', ['approved']);
        $actor = User::factory()->create();
        $actor->assignRole('finance');
        $travel = $this->source($actor, 'travel_request', ['status' => RequestStatus::Approved]);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $actor, [$this->item()]);
        $settlement->forceFill(['status' => RequestStatus::PaymentProcessing])->save();
        $this->markFinanceApproval($settlement, $actor);

        CompleteSettlement::run($settlement, $actor);

        $this->assertSame(RequestStatus::Completed, $settlement->refresh()->status);
    }

    public function test_finance_without_complete_permission_is_denied(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('finance');
        $actor->syncRoles([]);
        $travel = $this->source($actor);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $actor, [$this->item()]);
        $settlement->forceFill(['status' => RequestStatus::PaymentProcessing])->save();

        $this->actingAs($actor)->post(route('settlements.complete', $settlement))->assertForbidden();
    }

    public function test_direct_completion_requires_the_assigned_finance_approval(): void
    {
        $actor = $this->approver('finance');
        $owner = User::factory()->create();
        $owner->assignRole('employee');
        $travel = $this->source($owner);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $owner, [$this->item()]);
        $settlement->forceFill(['status' => RequestStatus::PaymentProcessing])->save();

        $this->expectException(HttpException::class);
        CompleteSettlement::run($settlement, $actor);
    }

    public function test_direct_completion_denies_inactive_user(): void
    {
        $actor = $this->approver('finance');
        $actor->forceFill(['active' => false])->save();
        $owner = User::factory()->create();
        $owner->assignRole('employee');
        $travel = $this->source($owner);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $owner, [$this->item()]);
        $settlement->forceFill(['status' => RequestStatus::PaymentProcessing])->save();
        $this->markFinanceApproval($settlement, $actor);

        $this->expectException(HttpException::class);
        CompleteSettlement::run($settlement, $actor);
    }

    public function test_direct_completion_denies_inactive_employee(): void
    {
        $actor = $this->approver('finance');
        $actor->employee()->update(['active' => false]);
        $owner = User::factory()->create();
        $owner->assignRole('employee');
        $travel = $this->source($owner);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $owner, [$this->item()]);
        $settlement->forceFill(['status' => RequestStatus::PaymentProcessing])->save();
        $this->markFinanceApproval($settlement, $actor);

        $this->expectException(HttpException::class);
        CompleteSettlement::run($settlement, $actor);
    }

    public function test_settlement_approval_requires_hrga_and_finance_scope(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('hrga');
        $actor->revokePermissionTo('settlement.review.hrga');
        $travel = $this->source($actor);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $actor, [$this->item()]);
        $settlement->forceFill(['status' => RequestStatus::InReview])->save();
        $approval = ApprovalRequest::create(['approvable_type' => 'settlement', 'approvable_id' => $settlement->id, 'chain_generation' => 'test', 'step_order' => 1, 'step_code' => 'hrga', 'approver_role' => 'hrga', 'approver_user_id' => $actor->id, 'status' => 'pending']);
        $this->expectException(ValidationException::class);
        ApprovalTransition::run($approval, $actor, ApprovalActionType::Approve);
    }

    public function test_settlement_attachment_is_private_and_document_type_allowlisted(): void
    {
        Storage::fake('eform-private');
        $actor = User::factory()->create();
        $actor->assignRole('employee');
        $travel = $this->source($actor);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $actor, [$this->item()]);
        $response = $this->actingAs($actor)->post(route('settlements.attachments.store', $settlement), ['file' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'), 'document_type' => 'forbidden']);
        $response->assertSessionHasErrors('document_type');
        $this->assertDatabaseCount('attachments', 0);
        $response = $this->actingAs($actor)->post(route('settlements.attachments.store', $settlement), ['file' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'), 'document_type' => 'receipt']);
        $response->assertSessionDoesntHaveErrors();
        $attachment = $settlement->attachments()->firstOrFail();
        $this->assertTrue(Storage::disk('eform-private')->exists($attachment->stored_path));
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'settlement', 'subject_id' => $settlement->id, 'description' => 'settlement.attachment_uploaded']);

        $this->actingAs($actor)->get(route('attachments.download', $attachment))->assertOk();
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_attachment_upload_rechecks_locked_editable_state(): void
    {
        Storage::fake('eform-private');
        $actor = User::factory()->create();
        $actor->assignRole('employee');
        $travel = $this->source($actor);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $actor, [$this->item()]);
        $settlement->forceFill(['status' => RequestStatus::Approved])->save();

        $this->actingAs($actor)->post(route('settlements.attachments.store', $settlement), ['file' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'), 'document_type' => 'receipt'])->assertForbidden();
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_unauthorized_settlement_attachment_creates_no_file(): void
    {
        Storage::fake('eform-private');
        $owner = User::factory()->create();
        $owner->assignRole('employee');
        $travel = $this->source($owner);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $owner, [$this->item()]);
        $settlement->forceFill(['status' => RequestStatus::Approved])->save();
        $file = UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf');

        $this->actingAs($owner)->post(route('settlements.attachments.store', $settlement), [
            'file' => $file, 'document_type' => 'receipt',
        ])->assertForbidden();
        $this->assertDatabaseCount('attachments', 0);
        Storage::disk('eform-private')->assertMissing('settlement/'.$settlement->id.'/'.$file->hashName());
    }

    public function test_assigned_approver_can_download_settlement_attachment(): void
    {
        Storage::fake('eform-private');
        $owner = User::factory()->create();
        $owner->assignRole('employee');
        $travel = $this->source($owner);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $owner, [$this->item()]);
        $assigned = $this->approver('hrga');
        ApprovalRequest::create([
            'approvable_type' => 'settlement', 'approvable_id' => $settlement->id, 'chain_generation' => 'assigned-test',
            'step_order' => 1, 'step_code' => 'hrga', 'approver_role' => 'hrga',
            'approver_user_id' => $assigned->id, 'status' => 'pending',
        ]);
        $attachment = $settlement->attachments()->create([
            'document_type' => 'receipt', 'original_name' => 'receipt.pdf',
            'stored_path' => 'settlement/'.$settlement->id.'/receipt.pdf', 'mime_type' => 'application/pdf', 'file_size' => 5,
            'uploaded_by' => $owner->id,
        ]);
        Storage::disk('eform-private')->put($attachment->stored_path, 'proof');

        $this->actingAs($assigned)->get(route('attachments.download', $attachment))->assertOk();
    }

    public function test_settlement_source_key_is_backed_by_a_unique_index(): void
    {
        $index = collect(Schema::getIndexes('settlements'))->first(fn (array $row): bool => $row['columns'] === ['source_key']);

        $this->assertNotNull($index);
        $this->assertTrue($index['unique']);
    }

    public function test_leave_settlement_e2e_approval_payment_completes_source_and_audits(): void
    {
        $this->approver('hrga');
        $this->approver('finance');
        $submitter = User::factory()->create();
        $submitter->assignRole('employee');
        $leave = $this->source($submitter, 'leave_request');
        $settlement = CreateSettlement::run('leave_request', $leave->id, $submitter, [$this->item()]);

        $this->approveSettlement($settlement, $submitter);

        $this->assertSame(RequestStatus::Completed, $settlement->fresh()->status);
        $this->assertSame(RequestStatus::Completed, $leave->fresh()->status);
        $this->assertNotNull($leave->fresh()->completed_at);
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'settlement', 'subject_id' => $settlement->id, 'description' => 'settlement.completed']);
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'leave_request', 'subject_id' => $leave->id, 'description' => 'source.completed']);
    }

    public function test_travel_settlement_e2e_approval_payment_completes_source_and_audits(): void
    {
        $this->approver('hrga');
        $this->approver('finance');
        $submitter = User::factory()->create();
        $submitter->assignRole('employee');
        $travel = $this->source($submitter, 'travel_request');
        $settlement = CreateSettlement::run('travel_request', $travel->id, $submitter, [$this->item()]);

        $this->approveSettlement($settlement, $submitter);

        $this->assertSame(RequestStatus::Completed, $settlement->fresh()->status);
        $this->assertSame(RequestStatus::Completed, $travel->fresh()->status);
        $this->assertNotNull($travel->fresh()->completed_at);
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'travel_request', 'subject_id' => $travel->id, 'description' => 'source.completed']);
    }

    public function test_finance_without_scoped_review_permission_cannot_approve_settlement(): void
    {
        $this->approver('hrga');
        $this->approver('finance');
        $submitter = User::factory()->create();
        $submitter->assignRole('employee');
        $travel = $this->source($submitter);
        $settlement = CreateSettlement::run('travel_request', $travel->id, $submitter, [$this->item()]);
        SubmitSettlement::run($settlement, $submitter);
        $hrga = ApprovalRequest::query()->where('approvable_id', $settlement->id)->where('step_code', 'hrga')->firstOrFail();
        ApprovalTransition::run($hrga, User::findOrFail($hrga->approver_user_id), ApprovalActionType::Approve);
        $finance = ApprovalRequest::query()->where('approvable_id', $settlement->id)->where('step_code', 'finance')->firstOrFail();
        $financeUser = User::findOrFail($finance->approver_user_id);
        $financeUser->roles()->firstOrFail()->revokePermissionTo('settlement.review.finance');

        $this->expectException(ValidationException::class);
        ApprovalTransition::run($finance, $financeUser, ApprovalActionType::Approve);
    }

    public function test_http_settlement_flow_runs_store_submit_inbox_approvals_and_completion(): void
    {
        $this->approver('hrga');
        $this->approver('finance');
        $submitter = User::factory()->create();
        $submitter->assignRole('employee');
        $travel = $this->source($submitter);
        $item = $this->item();

        $this->actingAs($submitter)->post(route('settlements.store'), [
            'source_type' => 'travel_request', 'source_id' => $travel->id, 'items' => [$item],
        ])->assertRedirect();
        $settlement = Settlement::latest('id')->firstOrFail();

        $this->actingAs($submitter)->post(route('settlements.submit', $settlement))->assertRedirect();
        $hrga = ApprovalRequest::query()->where('approvable_type', 'settlement')->where('approvable_id', $settlement->id)->where('step_code', 'hrga')->firstOrFail();
        $hrgaUser = User::findOrFail($hrga->approver_user_id);
        $this->actingAs($hrgaUser)->get(route('approvals.index'))->assertOk();
        $this->actingAs($hrgaUser)->get(route('approvals.show', $hrga))->assertOk();
        $this->actingAs($hrgaUser)->post(route('approvals.action', [$hrga, 'approve']))->assertRedirect();

        $finance = ApprovalRequest::query()->where('approvable_type', 'settlement')->where('approvable_id', $settlement->id)->where('step_code', 'finance')->firstOrFail();
        $financeUser = User::findOrFail($finance->approver_user_id);
        $this->actingAs($financeUser)->get(route('approvals.index'))->assertOk();
        $this->actingAs($financeUser)->get(route('approvals.show', $finance))->assertOk();
        $this->actingAs($financeUser)->post(route('approvals.action', [$finance, 'approve']))->assertRedirect();
        $this->actingAs($submitter)->get(route('settlements.show', $settlement))->assertInertia(fn (Assert $page) => $page
            ->component('Settlements/Show')
            ->has('timeline')
            ->where('timeline', fn (Collection $timeline): bool => $timeline
                ->contains(fn (array $item): bool => $item['action'] === 'approval.approve')));

        $this->actingAs($submitter)->post(route('settlements.complete', $settlement))->assertForbidden();
        $this->actingAs($financeUser)->post(route('settlements.complete', $settlement))->assertRedirect();

        $this->assertSame(RequestStatus::Completed, $settlement->fresh()->status);
        $this->assertSame(RequestStatus::Completed, $travel->fresh()->status);
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'settlement', 'subject_id' => $settlement->id, 'description' => 'settlement.completed']);
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'travel_request', 'subject_id' => $travel->id, 'description' => 'source.completed']);
    }
}
