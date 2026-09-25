<?php

namespace Tests\Feature;

use App\Data\Approval\ApprovalViewData;
use App\Enums\RequestStatus;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\MedicalClaim;
use App\Models\User;
use App\Services\Medical\SubmitMedicalClaim;
use Database\Seeders\ApprovalWorkflowSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MedicalClaimAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesPermissionsSeeder::class, ApprovalWorkflowSeeder::class]);
    }

    public function test_claim_calculates_server_total_and_accepts_self_spouse_and_child(): void
    {
        [$user, $employee] = $this->employee('employee');
        $response = $this->actingAs($user)->post(route('medical-claims.store'), [
            'benefit_types' => ['rawat_jalan', 'obat_vitamin'],
            'total_amount' => '0.01',
            'items' => [
                $this->item('self', '100.10', null, $employee->name),
                $this->item('spouse', '200.20', null, 'Istri Bebas'),
                $this->item('child', '300.30', null, 'Anak Bebas'),
            ],
        ]);

        $response->assertRedirect();
        $claim = MedicalClaim::query()->latest('id')->firstOrFail();
        $this->assertSame('600.60', (string) $claim->total_amount);
        $this->assertSame(['rawat_jalan', 'obat_vitamin'], $claim->benefit_types);
        $this->assertSame('rawat_jalan', $claim->benefit_type);
        $this->assertSame($employee->id, $claim->employee_id);
    }

    public function test_invalid_relationship_and_future_treatment_are_rejected(): void
    {
        [$user] = $this->employee('employee');
        $payload = ['benefit_type' => 'rawat_jalan', 'items' => [$this->item('parent', '10')]];
        $this->actingAs($user)->post(route('medical-claims.store'), $payload)->assertSessionHasErrors('items.0.relationship');

        $payload['items'][0] = $this->item('self', '10', now()->addDay()->toDateString());
        $this->actingAs($user)->post(route('medical-claims.store'), $payload)->assertSessionHasErrors('items.0.treatment_date');

        $payload['items'][0] = $this->item('spouse', '10', null, '');
        $this->actingAs($user)->post(route('medical-claims.store'), $payload)->assertSessionHasErrors('items.0.patient_name');
    }

    public function test_owner_can_see_own_amount_and_download_own_private_medical_attachment(): void
    {
        Storage::fake('eform-private');
        [$user] = $this->employee('employee');
        $this->actingAs($user)->post(route('medical-claims.store'), [
            'benefit_type' => 'rawat_jalan', 'items' => [$this->item('self', '123.45', null, $this->employeeName($user))],
        ])->assertRedirect();
        $claim = MedicalClaim::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('medical-claims.attachments.store', $claim), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
            'document_type' => 'receipt',
        ])->assertRedirect();
        $attachment = Attachment::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->get(route('medical-claims.show', $claim))->assertInertia(fn (Assert $page) => $page
            ->where('claim.total_amount', '123.45')
            ->has('claim.attachments', 1));
        $this->actingAs($user)->get(route('attachments.download', $attachment))->assertOk();
        $this->assertTrue(Storage::disk('eform-private')->exists($attachment->stored_path));
        $this->assertDatabaseHas('activity_log', ['subject_id' => $claim->id, 'description' => 'medical.attachment_uploaded']);
    }

    public function test_receipt_uploaded_with_new_draft_is_private_and_authorized(): void
    {
        Storage::fake('eform-private');
        [$user, $employee] = $this->employee('employee');
        $response = $this->actingAs($user)->post(route('medical-claims.store'), [
            'benefit_types' => ['rawat_jalan'],
            'items' => [$this->item('self', '123.45', null, $employee->name)],
            'receipt' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
        ]);
        $claim = MedicalClaim::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('medical-claims.show', $claim));
        $attachment = $claim->attachments()->firstOrFail();
        $this->assertSame('receipt', $attachment->document_type);
        $this->assertSame($user->id, $attachment->uploaded_by);
        $this->assertNotNull($attachment->sha256_hash);
        $this->assertTrue(Storage::disk('eform-private')->exists($attachment->stored_path));
        $this->assertDatabaseHas('activity_log', ['subject_id' => $claim->id, 'description' => 'medical.attachment_uploaded']);
        $this->actingAs($user)->get(route('attachments.download', $attachment))->assertOk();

        [$other] = $this->employee('employee');
        $this->actingAs($other)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_invalid_receipt_cannot_create_medical_draft_or_private_file(): void
    {
        Storage::fake('eform-private');
        [$user, $employee] = $this->employee('employee');
        $this->actingAs($user)->post(route('medical-claims.store'), [
            'benefit_types' => ['rawat_jalan'],
            'items' => [$this->item('self', '123.45', null, $employee->name)],
            'receipt' => UploadedFile::fake()->create('bukti.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('receipt');

        $this->assertDatabaseCount('medical_claims', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('eform-private')->allFiles());
    }

    public function test_client_total_and_overflow_are_not_accepted(): void
    {
        [$user] = $this->employee('employee');
        $item = $this->item('self', '999999999999.99');
        $response = $this->actingAs($user)->post(route('medical-claims.store'), [
            'benefit_type' => 'rawat_jalan', 'total_amount' => '1', 'items' => [$item, $item],
        ]);
        $response->assertSessionHasErrors('items.1.amount');
        $this->assertDatabaseCount('medical_claims', 0);
    }

    public function test_attachment_document_allowlist_comes_from_config(): void
    {
        config(['eform.medical.allowed_documents' => ['receipt']]);
        [$user] = $this->employee('employee');
        $claim = new MedicalClaim(['benefit_type' => 'rawat_jalan']);
        $claim->forceFill(['claim_number' => 'KLAIM-CONFIG-001', 'employee_id' => Employee::where('user_id', $user->id)->value('id'), 'status' => 'draft', 'created_by' => $user->id])->save();

        $this->actingAs($user)->post(route('medical-claims.attachments.store', $claim), ['file' => UploadedFile::fake()->create('doctor.pdf', 10, 'application/pdf'), 'document_type' => 'doctor_letter'])->assertSessionHasErrors('document_type');
    }

    public function test_returned_submit_audit_records_actual_source_status(): void
    {
        config(['eform.medical.required_documents' => []]);
        [$user, $employee] = $this->employee('employee');
        [$hrga] = $this->employee('hrga');
        $hrga->givePermissionTo('approval.act');
        [$hrgaManager] = $this->employee('hrga_manager');
        $hrgaManager->givePermissionTo('approval.act');
        $claim = new MedicalClaim(['benefit_type' => 'rawat_jalan']);
        $claim->forceFill(['claim_number' => 'KLAIM-RETURNED-001', 'employee_id' => $employee->id, 'employee_name' => $employee->name, 'status' => RequestStatus::Returned, 'created_by' => $user->id])->save();
        $claim->items()->create(['patient_name' => $employee->name, 'relationship' => 'self', 'treatment_date' => now()->subDay(), 'facility_name' => 'Klinik', 'amount' => 10]);

        SubmitMedicalClaim::run($claim, $user);

        $this->assertDatabaseHas('activity_log', ['subject_id' => $claim->id, 'description' => 'medical.submitted']);
        $this->assertSame('returned', data_get(
            Activity::where('subject_id', $claim->id)->where('description', 'medical.submitted')->latest('id')->first()->properties,
            'from'
        ));
    }

    public function test_custom_payment_permissions_support_maker_checker_and_audit_metadata(): void
    {
        [$owner, $employee] = $this->employee('employee');
        $processor = User::factory()->create(['active' => true]);
        $processor->givePermissionTo(Permission::findByName('medical.payment.process'));
        Employee::factory()->create(['user_id' => $processor->id, 'active' => true]);
        $completer = User::factory()->create(['active' => true]);
        $completer->givePermissionTo(Permission::findByName('medical.payment.complete'));
        Employee::factory()->create(['user_id' => $completer->id, 'active' => true]);
        $claim = new MedicalClaim(['benefit_type' => 'rawat_jalan']);
        $claim->forceFill(['claim_number' => 'KLAIM-PAYMENT-001', 'employee_id' => $employee->id, 'status' => RequestStatus::Approved, 'total_amount' => 123.45, 'created_by' => $owner->id])->save();
        $claim->approvalRequests()->create(['step_order' => 1, 'step_code' => 'medical', 'approver_role' => 'hrga', 'approver_user_id' => $processor->id, 'status' => 'approved', 'chain_generation' => 'payment-test']);

        $this->actingAs($processor)->post(route('medical-claims.payment', $claim))->assertRedirect();
        $this->actingAs($processor)->post(route('medical-claims.complete', $claim), ['payment_reference' => 'PAY-001', 'payment_date' => now()->toDateString()])->assertForbidden();
        $this->actingAs($completer)->post(route('medical-claims.complete', $claim), ['payment_reference' => 'PAY-001', 'payment_date' => now()->toDateString()])->assertRedirect();
        $this->assertDatabaseHas('medical_claims', ['id' => $claim->id, 'payment_reference' => 'PAY-001', 'payment_processed_by' => $processor->id, 'status' => 'completed']);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $claim->id, 'description' => 'medical.completed']);
        $properties = Activity::where('subject_id', $claim->id)->where('description', 'medical.completed')->latest('id')->firstOrFail()->properties->toArray();
        $this->assertArrayNotHasKey('amount', $properties);
        $this->assertArrayNotHasKey('payment_reference', $properties);
        $this->assertNotEmpty($properties['payment_reference_hash']);

        $second = new MedicalClaim(['benefit_type' => 'rawat_jalan']);
        $second->forceFill(['claim_number' => 'KLAIM-PAYMENT-002', 'employee_id' => $employee->id, 'status' => RequestStatus::PaymentProcessing, 'total_amount' => 50, 'payment_processed_by' => $processor->id, 'created_by' => $owner->id])->save();
        $this->actingAs($completer)->post(route('medical-claims.complete', $second), ['payment_reference' => 'bad ref', 'payment_date' => now()->toDateString()])->assertSessionHasErrors('payment_reference');
        $this->actingAs($completer)->post(route('medical-claims.complete', $second), ['payment_reference' => 'pay-001', 'payment_date' => now()->toDateString()])->assertSessionHasErrors('payment_reference');
    }

    public function test_medical_approval_dto_exposes_claim_number_for_display(): void
    {
        [$owner, $employee] = $this->employee('employee');
        [$finance] = $this->employee('finance');
        $claim = new MedicalClaim(['benefit_type' => 'rawat_jalan']);
        $claim->forceFill(['claim_number' => 'KLAIM-DTO-001', 'employee_id' => $employee->id, 'status' => RequestStatus::Submitted, 'created_by' => $owner->id])->save();
        $approval = $claim->approvalRequests()->create(['step_order' => 1, 'step_code' => 'hrga', 'approver_role' => 'hrga', 'approver_user_id' => $finance->id, 'status' => 'pending', 'chain_generation' => 'dto-test']);

        $dto = ApprovalViewData::make($approval->fresh(), $finance);

        $this->assertSame('KLAIM-DTO-001', $dto['approvable']['claim_number']);
    }

    public function test_aggregate_approval_dto_redacts_sensitive_comments_and_amount(): void
    {
        [$owner, $employee] = $this->employee('employee');
        [$finance] = $this->employee('finance');
        $claim = new MedicalClaim(['benefit_type' => 'rawat_jalan']);
        $claim->forceFill(['claim_number' => 'KLAIM-REDACT-001', 'employee_id' => $employee->id, 'status' => RequestStatus::Submitted, 'total_amount' => 999, 'created_by' => $owner->id])->save();
        $approval = $claim->approvalRequests()->create(['step_order' => 1, 'step_code' => 'hrga', 'approver_role' => 'hrga', 'approver_user_id' => $finance->id, 'status' => 'pending', 'comments' => 'RAHASIA', 'chain_generation' => 'redact-test']);
        $approval->actions()->create(['actor_id' => $finance->id, 'action' => 'return', 'comments' => 'RAHASIA']);

        $dto = ApprovalViewData::make($approval->fresh(), $finance);

        $this->assertNull($dto['comments']);
        $this->assertSame([], $dto['actions']);
        $this->assertArrayNotHasKey('total_amount', $dto['approvable']);

        $ownerDto = ApprovalViewData::make($approval->fresh(), $owner);
        $this->assertSame('RAHASIA', $ownerDto['comments']);
    }

    private function employee(string $role): array
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'active' => true]);

        return [$user, $employee];
    }

    private function item(string $relationship, string $amount, ?string $date = null, ?string $patientName = null, ?int $dependentId = null): array
    {
        return [
            'patient_name' => $patientName ?? 'Pasien '.$relationship,
            'relationship' => $relationship,
            'treatment_date' => $date ?? now()->subDay()->toDateString(),
            'facility_name' => 'Klinik Test',
            'diagnosis_code' => 'SENSITIVE-123',
            'amount' => $amount,
            'dependent_id' => $dependentId,
        ];
    }

    private function employeeName(User $user): string
    {
        return Employee::where('user_id', $user->id)->value('name');
    }
}
