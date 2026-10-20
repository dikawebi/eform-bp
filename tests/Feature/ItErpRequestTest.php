<?php

namespace Tests\Feature;

use App\Data\Approval\ApprovalViewData;
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
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_it_request_with_special_specification_defers_coo_ceo_to_it_decision(): void
    {
        [$user, $employee] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'special_specification' => true, 'description' => 'Butuh GPU', 'purpose' => 'CAD'])->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();
        $coo = $itRequest->approvalRequests()->where('step_code', 'coo_ceo')->firstOrFail();
        // Step dipertahankan tetapi TANPA approver: IT yang memutuskan nanti.
        $this->assertNotSame(ApprovalStepStatus::Skipped, $coo->status);
        $this->assertNull($coo->approver_user_id);
        $this->assertTrue(Activity::where('subject_type', 'it_request')->where('subject_id', $itRequest->id)->where('description', 'approval.step_deferred')->exists());
    }

    public function test_it_approve_without_escalation_decision_is_rejected(): void
    {
        [$user, $employee, $hodUser, $itUser] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'special_specification' => true, 'description' => 'Butuh GPU', 'purpose' => 'CAD'])->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();

        $hod = $itRequest->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $this->actingAs($hodUser)->post(route('approvals.action', [$hod, 'approve']))->assertRedirect();

        $it = $itRequest->approvalRequests()->where('step_code', 'it')->firstOrFail();
        $this->actingAs($itUser)->get(route('approvals.show', $it))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('availableActions.needsEscalationDecision', true)->has('availableActions.eligibleEscalateUsers'));
        $this->actingAs($itUser)->post(route('approvals.action', [$it, 'approve']))
            ->assertSessionHasErrors('escalation');
    }

    public function test_it_escalation_none_skips_coo_and_completes(): void
    {
        [$user, $employee, $hodUser, $itUser, $pmUser] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'special_specification' => true, 'description' => 'Butuh GPU', 'purpose' => 'CAD'])->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();

        $hod = $itRequest->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $this->actingAs($hodUser)->post(route('approvals.action', [$hod, 'approve']))->assertRedirect();

        $it = $itRequest->approvalRequests()->where('step_code', 'it')->firstOrFail();
        $this->actingAs($itUser)->post(route('approvals.action', [$it, 'approve']), ['escalation' => 'none'])->assertRedirect();

        $this->assertSame(ApprovalStepStatus::Skipped, $itRequest->approvalRequests()->where('step_code', 'coo_ceo')->firstOrFail()->status);
        $this->assertTrue(Activity::where('subject_type', 'it_request')->where('subject_id', $itRequest->id)->where('description', 'approval.escalation_decided')->exists());

        $pm = $itRequest->approvalRequests()->where('step_code', 'pm_gm')->firstOrFail();
        $this->actingAs($pmUser)->post(route('approvals.action', [$pm, 'approve']))->assertRedirect();

        $this->assertSame(RequestStatus::Completed, $itRequest->refresh()->status);
        $this->assertNotNull($itRequest->completed_at);
    }

    public function test_it_escalation_to_coo_assigns_target_and_completes(): void
    {
        [$user, $employee, $hodUser, $itUser, $pmUser, $cooUser] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'special_specification' => true, 'description' => 'Butuh GPU', 'purpose' => 'CAD'])->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();

        $hod = $itRequest->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $this->actingAs($hodUser)->post(route('approvals.action', [$hod, 'approve']))->assertRedirect();

        $it = $itRequest->approvalRequests()->where('step_code', 'it')->firstOrFail();
        $this->actingAs($itUser)->post(route('approvals.action', [$it, 'approve']), [
            'escalation' => 'coo_ceo', 'escalate_role' => 'coo', 'escalate_user_id' => $cooUser->id,
            'comments' => 'GPU diperlukan untuk pemodelan CAD; tanpa GPU proyek tertunda.',
        ])->assertRedirect();

        $coo = $itRequest->approvalRequests()->where('step_code', 'coo_ceo')->firstOrFail();
        $this->assertSame((int) $cooUser->id, (int) $coo->approver_user_id);
        $this->assertSame('coo', $coo->approver_role);
        // Catatan eskalasi tersimpan pada tahap COO agar tampil menonjol.
        $this->assertSame('GPU diperlukan untuk pemodelan CAD; tanpa GPU proyek tertunda.', $coo->comments);

        $pm = $itRequest->approvalRequests()->where('step_code', 'pm_gm')->firstOrFail();
        $this->actingAs($pmUser)->post(route('approvals.action', [$pm, 'approve']))->assertRedirect();

        $coo = $itRequest->approvalRequests()->where('step_code', 'coo_ceo')->firstOrFail();
        $this->assertSame(ApprovalStepStatus::Pending, $coo->status);
        $this->actingAs($cooUser)->post(route('approvals.action', [$coo, 'approve']))->assertRedirect();

        $this->assertSame(RequestStatus::Completed, $itRequest->refresh()->status);
        $this->assertNotNull($itRequest->completed_at);
    }

    public function test_it_escalation_to_ceo_assigns_ceo_role_and_exposes_highlighted_note(): void
    {
        [$user, $employee, $hodUser, $itUser, $pmUser, , $ceoUser] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'special_specification' => true, 'description' => 'Butuh GPU', 'purpose' => 'CAD'])->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();

        $hod = $itRequest->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $this->actingAs($hodUser)->post(route('approvals.action', [$hod, 'approve']))->assertRedirect();

        $it = $itRequest->approvalRequests()->where('step_code', 'it')->firstOrFail();
        $this->actingAs($itUser)->post(route('approvals.action', [$it, 'approve']), [
            'escalation' => 'coo_ceo', 'escalate_role' => 'ceo', 'escalate_user_id' => $ceoUser->id,
            'comments' => 'Investasi strategis untuk lini produksi baru.',
        ])->assertRedirect();

        $step = $itRequest->approvalRequests()->where('step_code', 'coo_ceo')->firstOrFail();
        $this->assertSame((int) $ceoUser->id, (int) $step->approver_user_id);
        $this->assertSame('ceo', $step->approver_role);

        $pm = $itRequest->approvalRequests()->where('step_code', 'pm_gm')->firstOrFail();
        $this->actingAs($pmUser)->post(route('approvals.action', [$pm, 'approve']))->assertRedirect();

        $step = $itRequest->approvalRequests()->where('step_code', 'coo_ceo')->firstOrFail();
        $this->actingAs($ceoUser)->get(route('approvals.show', $step))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('approval.escalation.role', 'ceo')
                ->where('approval.escalation.note', 'Investasi strategis untuk lini produksi baru.'));

        $this->actingAs($ceoUser)->post(route('approvals.action', [$step, 'approve']))->assertRedirect();
        $this->assertSame(RequestStatus::Completed, $itRequest->refresh()->status);
    }

    public function test_it_escalation_requires_role_and_note(): void
    {
        [$user, $employee, $hodUser, $itUser, , $cooUser] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'special_specification' => true, 'description' => 'Butuh GPU', 'purpose' => 'CAD'])->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();

        $hod = $itRequest->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $this->actingAs($hodUser)->post(route('approvals.action', [$hod, 'approve']))->assertRedirect();

        $it = $itRequest->approvalRequests()->where('step_code', 'it')->firstOrFail();
        // Tanpa role tujuan.
        $this->actingAs($itUser)->post(route('approvals.action', [$it, 'approve']), [
            'escalation' => 'coo_ceo', 'escalate_user_id' => $cooUser->id, 'comments' => 'Perlu GPU.',
        ])->assertSessionHasErrors('escalate_role');
        // Tanpa catatan eskalasi.
        $this->actingAs($itUser)->post(route('approvals.action', [$it, 'approve']), [
            'escalation' => 'coo_ceo', 'escalate_role' => 'coo', 'escalate_user_id' => $cooUser->id,
        ])->assertSessionHasErrors('comments');
        // Role target tidak cocok (COO dipilih sebagai CEO).
        $this->actingAs($itUser)->post(route('approvals.action', [$it, 'approve']), [
            'escalation' => 'coo_ceo', 'escalate_role' => 'ceo', 'escalate_user_id' => $cooUser->id, 'comments' => 'Perlu GPU.',
        ])->assertSessionHasErrors('escalate_user_id');
    }

    public function test_it_escalation_to_ineligible_user_is_rejected(): void
    {
        [$user, $employee, $hodUser, $itUser, $pmUser] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'special_specification' => true, 'description' => 'Butuh GPU', 'purpose' => 'CAD'])->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();

        $hod = $itRequest->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $this->actingAs($hodUser)->post(route('approvals.action', [$hod, 'approve']))->assertRedirect();

        $it = $itRequest->approvalRequests()->where('step_code', 'it')->firstOrFail();
        // PM bukan pemegang role coo/ceo sehingga tidak eligible sebagai target.
        $this->actingAs($itUser)->post(route('approvals.action', [$it, 'approve']), [
            'escalation' => 'coo_ceo', 'escalate_role' => 'coo', 'escalate_user_id' => $pmUser->id, 'comments' => 'Perlu GPU.',
        ])->assertSessionHasErrors('escalate_user_id');
    }

    public function test_it_approval_summary_exposes_description_and_purpose(): void
    {
        [$user, $employee] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'special_specification' => true, 'description' => 'Butuh GPU', 'purpose' => 'CAD', 'accessories' => ['acc_1'], 'software_standard' => ['MS Office'], 'software_optional' => [['name' => 'AutoCAD', 'license' => 'new']]])->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();

        $approval = $itRequest->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $summary = ApprovalViewData::make($approval, $user)['approvable'];

        $this->assertSame('it_request', $summary['type']);
        $this->assertSame('Butuh GPU', $summary['description']);
        $this->assertSame('CAD', $summary['purpose']);
        $this->assertSame(['hod', 'it', 'pm_gm', 'coo_ceo'], array_column($summary['chain'], 'step_code'));
        $this->assertSame('laptop', $summary['form']['device_type']);
        $this->assertSame('Butuh GPU', $summary['form']['description']);
        $this->assertSame('Laptop', $summary['form_meta']['option_labels']['laptop']);
        $this->assertSame('Laptop', $summary['device_label']);
        $this->assertSame([['code' => 'acc_1', 'label' => 'Monitor 20 inci']], $summary['accessories']);
        $this->assertSame(['MS Office'], $summary['software_standard']);
        $this->assertSame([['name' => 'AutoCAD', 'license' => 'new']], $summary['software_optional']);
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

        // Nonaktifkan via update: hilang dari form tetapi riwayat tetap terbaca.
        $option = \App\Models\ItItemOption::where('code', 'laptop')->firstOrFail();
        $this->actingAs($admin)->put(route('master.it-items.update', $option), [
            'label' => 'Laptop', 'requires_note' => false, 'sort_order' => 0, 'is_active' => false,
        ])->assertRedirect();
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

    public function test_master_option_delete_is_blocked_when_used(): void
    {
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('admin');
        [$user, $employee] = $this->employee('employee');

        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'device_type' => 'laptop', 'accessories' => ['acc_1']])->assertRedirect();
        $option = \App\Models\ItItemOption::where('code', 'laptop')->firstOrFail();

        $this->actingAs($admin)->delete(route('master.it-items.destroy', $option))->assertSessionHasErrors('code');
        $this->assertDatabaseHas('it_item_options', ['code' => 'laptop']);

        $unused = \App\Models\ItItemOption::where('code', 'desktop')->firstOrFail();
        $this->actingAs($admin)->delete(route('master.it-items.destroy', $unused))->assertRedirect();
        $this->assertDatabaseMissing('it_item_options', ['code' => 'desktop']);
    }

    public function test_completed_it_request_print_renders_save_to_pdf_document(): void
    {
        [$user, $employee, $hodUser, $itUser, $pmUser] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), $this->itPayload($employee))->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();

        foreach (['hod' => $hodUser, 'it' => $itUser, 'pm_gm' => $pmUser] as $step => $actor) {
            $approval = $itRequest->approvalRequests()->where('step_code', $step)->firstOrFail();
            $this->actingAs($actor)->post(route('approvals.action', [$approval, 'approve']))->assertRedirect();
        }
        $this->assertSame(RequestStatus::Completed, $itRequest->refresh()->status);

        // Hanya IT yang dapat membuka dokumen Save to PDF; pemohon biasa ditolak.
        $this->actingAs($user)->get(route('it-requests.print', $itRequest))->assertForbidden();
        $this->actingAs($itUser)->get(route('it-requests.print', $itRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ITRequests/Print')
                ->missing('format')
                ->has('request')
                ->has('timeline'));

        // Parameter lama tidak lagi mengubah format dokumen.
        $this->actingAs($itUser)->get(route('it-requests.print', [$itRequest, 'format' => 'memo']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ITRequests/Print')->missing('format'));

        // Listing menampilkan tombol Save to PDF hanya untuk IT pada baris completed.
        $this->actingAs($itUser)->get(route('it-requests.index'))
            ->assertInertia(fn (Assert $page) => $page->where('requests.data.0.can_print', true));
        $this->actingAs($user)->get(route('it-requests.index'))
            ->assertInertia(fn (Assert $page) => $page->where('requests.data.0.can_print', false));
    }

    public function test_it_fills_pr_msd_number_after_completion_and_audits_it(): void
    {
        [$user, $employee, $hodUser, $itUser, $pmUser] = $this->itActors();

        $this->actingAs($user)->post(route('it-requests.store'), $this->itPayload($employee))->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();

        foreach (['hod' => $hodUser, 'it' => $itUser, 'pm_gm' => $pmUser] as $step => $actor) {
            $approval = $itRequest->approvalRequests()->where('step_code', $step)->firstOrFail();
            $this->actingAs($actor)->post(route('approvals.action', [$approval, 'approve']))->assertRedirect();
        }
        $this->assertSame(RequestStatus::Completed, $itRequest->refresh()->status);

        // Pemilik biasa tidak boleh mengisi nomor PR.
        $this->actingAs($user)->post(route('it-requests.pr-number.update', $itRequest), ['pr_msd_number' => 'PR-1001'])->assertForbidden();
        $this->assertNull($itRequest->refresh()->pr_msd_number);

        // IT mengisi lalu merevisi nomor PR; semua perubahan tercatat di audit log.
        $this->actingAs($itUser)->post(route('it-requests.pr-number.update', $itRequest), ['pr_msd_number' => 'PR-MSD-1001'])->assertSessionHasNoErrors();
        $this->assertSame('PR-MSD-1001', $itRequest->refresh()->pr_msd_number);
        $this->actingAs($itUser)->post(route('it-requests.pr-number.update', $itRequest), ['pr_msd_number' => 'PR-MSD-1002'])->assertSessionHasNoErrors();
        $this->assertSame('PR-MSD-1002', $itRequest->refresh()->pr_msd_number);

        $logs = Activity::where('subject_type', 'it_request')->where('subject_id', $itRequest->id)->where('description', 'it.pr_msd_number_updated')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['old' => null, 'new' => 'PR-MSD-1001'], $logs->first()->properties->toArray());

        // Nomor PR boleh dikosongkan (opsional).
        $this->actingAs($itUser)->post(route('it-requests.pr-number.update', $itRequest), ['pr_msd_number' => ''])->assertSessionHasNoErrors();
        $this->assertNull($itRequest->refresh()->pr_msd_number);
    }

    public function test_print_is_forbidden_before_completed_or_for_stranger(): void    {
        [$user, $employee] = $this->itActors();
        [$stranger] = $this->employee('employee');

        $this->actingAs($user)->post(route('it-requests.store'), $this->itPayload($employee))->assertRedirect();
        $itRequest = ItRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('it-requests.submit', $itRequest))->assertSessionHasNoErrors();

        $this->actingAs($user)->get(route('it-requests.print', $itRequest))->assertForbidden();
        $this->actingAs($stranger)->get(route('it-requests.print', $itRequest))->assertForbidden();
    }

    public function test_it_index_supports_search_filter_and_sort(): void
    {
        [$user, $employee] = $this->employee('employee');

        $this->actingAs($user)->post(route('it-requests.store'), $this->itPayload($employee))->assertRedirect();
        $this->actingAs($user)->post(route('it-requests.store'), $this->itPayload($employee))->assertRedirect();
        $first = ItRequest::oldest('id')->firstOrFail();
        $second = ItRequest::latest('id')->firstOrFail();
        $second->forceFill(['status' => RequestStatus::Completed])->save();

        $this->actingAs($user)->get(route('it-requests.index', ['search' => $first->request_number]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.id', $first->id));

        $this->actingAs($user)->get(route('it-requests.index', ['status' => 'completed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.id', $second->id));

        $this->actingAs($user)->get(route('it-requests.index', ['sort' => 'request_number', 'direction' => 'asc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('requests.data.0.id', $first->id)->where('requests.data.1.id', $second->id));

        $this->actingAs($user)->get(route('it-requests.index', ['sort' => 'password']))->assertSessionHasErrors('sort');
    }

    public function test_it_index_filters_by_device_types_and_date_range(): void
    {
        [$user, $employee] = $this->employee('employee');

        $this->actingAs($user)->post(route('it-requests.store'), $this->itPayload($employee))->assertRedirect();
        $this->actingAs($user)->post(route('it-requests.store'), [...$this->itPayload($employee), 'device_type' => 'desktop'])->assertRedirect();
        $laptop = ItRequest::where('device_type', 'laptop')->firstOrFail();
        $desktop = ItRequest::where('device_type', 'desktop')->firstOrFail();
        $laptop->forceFill(['created_at' => '2026-01-05 08:00:00', 'updated_at' => '2026-01-05 08:00:00'])->save();

        $this->actingAs($user)->get(route('it-requests.index', ['device_types' => ['desktop']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.id', $desktop->id));

        $this->actingAs($user)->get(route('it-requests.index', ['date_from' => '2026-01-05', 'date_to' => '2026-01-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.id', $laptop->id));

        $this->actingAs($user)->get(route('it-requests.index', ['device_types' => ['photon_torpedo']]))->assertSessionHasErrors('device_types.0');
        $this->actingAs($user)->get(route('it-requests.index', ['date_from' => '2026-02-01', 'date_to' => '2026-01-01']))->assertSessionHasErrors('date_to');
    }

    public function test_erp_index_supports_search_filter_and_sort(): void
    {
        [$user, $employee] = $this->employee('employee');

        $this->actingAs($user)->post(route('erp-requests.store'), $this->erpPayload($employee))->assertRedirect();
        $this->actingAs($user)->post(route('erp-requests.store'), [...$this->erpPayload($employee), 'action_type' => 'reset_auth', 'existing_erp_username' => 'u.baru'])->assertRedirect();
        $first = ErpRequest::oldest('id')->firstOrFail();
        $second = ErpRequest::latest('id')->firstOrFail();
        $second->forceFill(['status' => RequestStatus::Completed])->save();

        $this->actingAs($user)->get(route('erp-requests.index', ['action_type' => 'reset_auth']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.id', $second->id));

        $this->actingAs($user)->get(route('erp-requests.index', ['search' => $first->request_number]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.id', $first->id));

        $this->actingAs($user)->get(route('erp-requests.index', ['sort' => 'request_number', 'direction' => 'asc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('requests.data.0.id', $first->id)->where('requests.data.1.id', $second->id));

        $first->forceFill(['created_at' => '2026-01-05 08:00:00', 'updated_at' => '2026-01-05 08:00:00'])->save();
        $this->actingAs($user)->get(route('erp-requests.index', ['date_from' => '2026-01-05', 'date_to' => '2026-01-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.id', $first->id));
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
        $cooUser->assignRole('coo');
        [$ceoUser] = $this->employee('ceo');
        $employee->forceFill(['site' => 'Site-UT', 'hod_id' => $hodEmployee->id])->save();
        SitePmGmAssignment::create(['site' => 'Site-UT', 'pm_gm_user_id' => $pmUser->id, 'is_active' => true]);

        return [$user, $employee, $hodUser, $itUser, $pmUser, $cooUser, $ceoUser];
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
