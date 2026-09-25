<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MedicalClaim;
use App\Models\User;
use App\Support\MedicalConfigValidator;
use Database\Seeders\ApprovalWorkflowSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MedicalClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesPermissionsSeeder::class, ApprovalWorkflowSeeder::class]);
    }

    public function test_medical_claim_create_explains_when_login_has_no_linked_employee(): void
    {
        $employeeUser = User::factory()->create(['active' => true]);
        $employeeUser->assignRole('employee');

        $this->actingAs($employeeUser)->get(route('medical-claims.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MedicalClaims/Unlinked')
                ->where('canManageEmployees', false));

        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('medical-claims.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MedicalClaims/Unlinked')
                ->where('canManageEmployees', true));
    }

    public function test_medical_claim_create_receives_read_only_employee_identity(): void
    {
        [$user, $employee] = $this->actor('employee');

        $this->actingAs($user)->get(route('medical-claims.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MedicalClaims/Create')
                ->where('meta.employee.employee_number', $employee->employee_number)
                ->where('meta.employee.name', $employee->name)
                ->where('meta.employee.department', $employee->department)
                ->where('meta.employee.job_title', $employee->job_title));
    }

    public function test_finance_and_auditor_do_not_receive_medical_detail_or_attachment_data(): void
    {
        [$owner, $employee] = $this->actor('employee');
        $claim = new MedicalClaim(['benefit_type' => 'rawat_jalan']);
        $claim->forceFill(['claim_number' => 'KLAIM-PRIVACY-001', 'employee_id' => $employee->id, 'employee_name' => $employee->name,
            'employee_number' => $employee->employee_number, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $owner->id])->save();
        $claim->items()->create(['patient_name' => 'Rahasia Pasien', 'relationship' => 'self', 'facility_name' => 'Klinik Rahasia', 'treatment_date' => now()->subDay(), 'diagnosis_code' => 'DX-SECRET', 'amount' => 100]);
        $claim->attachments()->create(['document_type' => 'receipt', 'original_name' => 'secret.pdf', 'stored_path' => 'medical/secret.pdf', 'mime_type' => 'application/pdf', 'file_size' => 10, 'uploaded_by' => $owner->id]);

        [$finance] = $this->actor('finance');
        $this->actingAs($finance)->get(route('medical-claims.show', $claim))->assertInertia(fn (Assert $page) => $page
            ->where('claim.claim_number', 'KLAIM-PRIVACY-001')->where('claim.benefit_type', 'medical_claim')->where('claim.total_amount', '100.00')->has('claim.items', 0)->has('claim.attachments', 0)->has('activities', 0)->has('timeline', 0));

        [$auditor] = $this->actor('auditor');
        $this->actingAs($auditor)->get(route('medical-claims.show', $claim))->assertInertia(fn (Assert $page) => $page
            ->where('claim.benefit_type', 'medical_claim')->where('claim.total_amount', null)->has('claim.items', 0)->has('claim.attachments', 0)->has('activities', 0)->where('claim.employee', null));
    }

    public function test_invalid_required_documents_configuration_fails_validation(): void
    {
        config(['eform.medical.allowed_documents' => ['receipt'], 'eform.medical.required_documents' => ['doctor_letter']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('required_documents must be a subset');
        MedicalConfigValidator::validate();
    }

    public function test_finance_and_auditor_cannot_download_medical_attachment(): void
    {
        Storage::fake('eform-private');
        [$owner, $employee] = $this->actor('employee');
        $claim = new MedicalClaim(['benefit_type' => 'rawat_jalan']);
        $claim->forceFill(['claim_number' => 'KLAIM-DOWNLOAD-001', 'employee_id' => $employee->id, 'status' => 'approved', 'created_by' => $owner->id])->save();
        $attachment = $claim->attachments()->create(['document_type' => 'receipt', 'original_name' => 'secret.pdf', 'stored_path' => 'medical/secret.pdf', 'mime_type' => 'application/pdf', 'file_size' => 10, 'uploaded_by' => $owner->id]);
        Storage::disk('eform-private')->put($attachment->stored_path, 'secret');

        foreach (['finance', 'auditor'] as $role) {
            [$user] = $this->actor($role);
            $this->actingAs($user)->get(route('attachments.download', $attachment))->assertForbidden();
        }
    }

    private function actor(string $role): array
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'active' => true]);

        return [$user, $employee];
    }
}
