<?php

namespace Tests\Feature;

use App\Enums\ApprovalStepStatus;
use App\Enums\RequestStatus;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\ErpRequest;
use App\Models\ItRequest;
use App\Models\SitePmGmAssignment;
use App\Models\User;
use Database\Seeders\ApprovalWorkflowSeeder;
use Database\Seeders\ItItemOptionSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ItErpRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesPermissionsSeeder::class, ApprovalWorkflowSeeder::class, ItItemOptionSeeder::class]);
    }

    public function test_it_request_validates_replacement_and_special_specification_rules(): void
    {
        [$user, $employee] = $this->employee('employee');

        $base = $this->itPayload($employee);
        $this->actingAs($user)->post(route('it-requests.store'), [...$base, 'request_type' => 'replacement'])
            ->assertSessionHasErrors('replacement_reason');

        $this->actingAs($user)->post(route('it-requests.store'), [...$base, 'request_type' => 'replacement', 'replacement_reason' => 'other'])
            ->assertSessionHasErrors('replacement_note');

        $this->actingAs($user)->post(route('it-requests.store'), [...$base, 'special_specification' => true])
            ->assertSessionHasErrors(['description', 'purpose']);

        $this->actingAs($user)->post(route('it-requests.store'), [...$base, 'accessories' => ['other']])
            ->assertSessionHasErrors('accessory_other_note');
    }

    public function test_it_request_submit_builds_chain_and_skips_coo_ceo_without_special_spec(): void
    {
        [$user, $employee, $hod, $it, $pm] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), $this->itPayload($employee))->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::InReview, $itRequest->refresh()->status);

        $steps = $itRequest->approvalRequests()->orderBy('step_order')->get();
        $this->assertSame(['hod', 'it', 'pm_gm', 'coo_ceo'], $steps->pluck('step_code')->all());
        $this->assertSame([$hod->id, $it->id, $pm->id, null], $steps->pluck('approver_user_id')->map(fn ($id) => $id === null ? null : (int) $id)->all());
        $this->assertSame(ApprovalStepStatus::Pending, $steps[0]->status);
        $this->assertSame(ApprovalStepStatus::Skipped, $steps[3]->status);
        $this->assertTrue(Activity::where('subject_type', 'it_request')->where('subject_id', $itRequest->id)->where('description', 'it.submitted')->exists());
    }

    public function test_it_request_with_special_specification_keeps_coo_ceo_step(): void
    {
        [$user, $employee] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'special_specification' => true, 'description' => 'Butuh GPU', 'purpose' => 'CAD'])->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();
        $coo = $itRequest->approvalRequests()->where('step_code', 'coo_ceo')->firstOrFail();
        $this->assertNotSame(ApprovalStepStatus::Skipped, $coo->status);
        $this->assertNotNull($coo->approver_user_id);
    }

    public function test_it_request_submit_requires_site_pm_gm_mapping(): void
    {
        [$user, $employee] = $this->employee('employee');
        $employee->forceFill(['site' => 'Site-Tanpa-Mapping'])->save();

        $this->actingAs($user)->post(route('it-requests.store'), $this->itPayload($employee))->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasErrors('site');
    }

    public function test_erp_request_submit_builds_chain_with_erp_reviewer(): void
    {
        [$user, $employee, $hod, $reviewer, $it, $pm] = $this->erpActors();

        $this->actingAs($user)->post(route('erp-requests.store'), $this->erpPayload($employee))->assertRedirect();
        $erp = ErpRequest::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('erp-requests.submit', $erp))->assertSessionHasNoErrors();

        $steps = $erp->approvalRequests()->orderBy('step_order')->get();
        $this->assertSame(['hod', 'erp_review', 'it', 'pm_gm'], $steps->pluck('step_code')->all());
        $this->assertSame([$hod->id, $reviewer->id, $it->id, $pm->id], $steps->pluck('approver_user_id')->map(fn ($id) => (int) $id)->all());
        $this->assertTrue(Activity::where('subject_type', 'erp_request')->where('subject_id', $erp->id)->where('description', 'erp.submitted')->exists());
    }

    public function test_erp_modify_role_requires_existing_username(): void
    {
        [$user, $employee] = $this->employee('employee');

        $this->actingAs($user)->post(route('erp-requests.store'), [...$this->erpPayload($employee), 'action_type' => 'modify_role'])
            ->assertSessionHasErrors('existing_erp_username');
    }

    public function test_non_owner_cannot_view_or_update_it_request(): void
    {
        [$user, $employee] = $this->employee('employee');
        [$other] = $this->employee('employee');

        $this->actingAs($user)->post(route('it-requests.store'), $this->itPayload($employee))->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();

        $this->actingAs($other)->get(route('it-requests.show', $itRequest))->assertForbidden();
        $this->actingAs($other)->put(route('it-requests.update', $itRequest), $this->itPayload($employee))->assertForbidden();
    }

    public function test_it_attachment_is_private_to_owner_and_assignee(): void
    {
        Storage::fake('eform-private');
        [$user, $employee] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), $this->itPayload($employee))->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('it-requests.attachments.store', $itRequest), [
            'file' => UploadedFile::fake()->create('justifikasi.pdf', 10, 'application/pdf'),
            'document_type' => 'justification',
        ])->assertRedirect();
        $attachment = Attachment::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->get(route('attachments.download', $attachment))->assertOk();

        [$stranger] = $this->employee('employee');
        $this->actingAs($stranger)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_it_device_is_optional_and_unknown_codes_rejected(): void
    {
        [$user, $employee] = $this->employee('employee');

        $payload = $this->itPayload($employee);
        unset($payload['device_type']);
        $this->actingAs($user)->post(route('it-requests.store'), $payload)->assertRedirect();
        $this->assertNull(ItRequest::query()->latest('id')->firstOrFail()->device_type);

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'device_type' => 'photon_torpedo'])
            ->assertSessionHasErrors('device_type');
    }

    public function test_it_master_options_managed_via_ui_and_deactivation_keeps_history(): void
    {
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('admin');
        [$user] = $this->employee('employee');

        $this->actingAs($admin)->post(route('master.it-items.store'), [
            'code' => 'vr_headset', 'label' => 'VR Headset', 'kind' => 'accessory',
        ])->assertRedirect();
        $this->assertDatabaseHas('it_item_options', ['code' => 'vr_headset', 'is_active' => true]);

        $option = \App\Models\ItItemOption::where('code', 'laptop')->firstOrFail();
        $this->actingAs($admin)->delete(route('master.it-items.destroy', $option))->assertRedirect();
        $this->assertFalse($option->fresh()->is_active);

        // Opsi nonaktif hilang dari meta form, tetapi draf lama yang memakainya tetap valid.
        $this->actingAs($user)->get(route('it-requests.create'))->assertOk();
        $deviceCodes = collect(\App\Http\Controllers\ItRequestController::itemOptions('device'))->pluck('value')->all();
        $accessoryCodes = collect(\App\Http\Controllers\ItRequestController::itemOptions('accessory'))->pluck('value')->all();
        $this->assertNotContains('laptop', $deviceCodes);
        $this->assertContains('vr_headset', $accessoryCodes);

        $this->actingAs($user)->post(route('master.it-items.store'), ['code' => 'x', 'label' => 'X', 'kind' => 'device'])
            ->assertForbidden();
    }

    public function test_employee_without_onbehalf_cannot_file_for_another_employee(): void
    {
        [$user] = $this->employee('employee');
        [, $otherEmployee] = $this->employee('employee');

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($otherEmployee), 'employee_id' => $otherEmployee->id])
            ->assertSessionHasErrors('employee_id');
    }

    /**
     * @return array{0:User,1:Employee,2:User,3:User,4:User}
     */
    private function itActors(): array
    {
        [$user, $employee] = $this->employee('employee');
        [$hodUser, $hodEmployee] = $this->employee('hod');
        [$itUser] = $this->employee('it');
        [$pmUser] = $this->employee('project_manager');
        [$cooUser] = $this->employee('coo_ceo');
        $employee->forceFill(['site' => 'Site-UT', 'hod_id' => $hodEmployee->id])->save();
        SitePmGmAssignment::create(['site' => 'Site-UT', 'pm_gm_user_id' => $pmUser->id, 'is_active' => true]);

        return [$user, $employee, $hodUser, $itUser, $pmUser, $cooUser];
    }

    /**
     * @return array{0:User,1:Employee,2:User,3:User,4:User,5:User}
     */
    private function erpActors(): array
    {
        [$user, $employee] = $this->employee('employee');
        [$hodUser, $hodEmployee] = $this->employee('hod');
        [$reviewerUser] = $this->employee('erp_reviewer');
        [$itUser] = $this->employee('it');
        [$pmUser] = $this->employee('project_manager');
        $employee->forceFill(['site' => 'Site-ERP', 'hod_id' => $hodEmployee->id])->save();
        SitePmGmAssignment::create(['site' => 'Site-ERP', 'pm_gm_user_id' => $pmUser->id, 'is_active' => true]);

        return [$user, $employee, $hodUser, $reviewerUser, $itUser, $pmUser];
    }

    private function employee(string $role): array
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'active' => true]);

        return [$user, $employee];
    }

    private function itPayload(Employee $employee): array
    {
        return [
            'employee_id' => $employee->id,
            'request_type' => 'new_item',
            'device_type' => 'laptop',
            'needed_date' => now()->addWeek()->toDateString(),
            'priority' => 'normal',
        ];
    }

    private function erpPayload(Employee $employee): array
    {
        return [
            'employee_id' => $employee->id,
            'action_type' => 'new_account',
            'business_purpose' => 'Input PO pembelian solar site.',
            'modules' => ['supply_chain_procurement'],
        ];
    }
}
