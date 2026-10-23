<?php

namespace Tests\Feature;

use App\Enums\ApprovalStepStatus;
use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\ItRequest;
use App\Models\ItSpecTemplate;
use App\Models\SitePmGmAssignment;
use App\Models\User;
use App\Services\It\ItRequestPdf;
use Database\Seeders\ApprovalWorkflowSeeder;
use Database\Seeders\ItItemOptionSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ItSpecificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesPermissionsSeeder::class, ApprovalWorkflowSeeder::class, ItItemOptionSeeder::class]);
    }

    public function test_it_reviewer_saves_draft_and_owner_cannot_see_it_before_approval(): void
    {
        [$request, $users, $itApproval] = $this->requestAtItStep();

        $this->actingAs($users['it'])->post(route('it-requests.specification.store', $request), $this->specPayload())
            ->assertSessionHasNoErrors();

        $specification = $request->refresh()->specification;
        $this->assertNotNull($specification);
        $this->assertNull($specification->locked_at);
        $this->assertSame('laptop', $specification->device_type);
        $this->assertSame('Intel Core i7', $specification->specs_json[0]['value']);

        $this->actingAs($users['owner'])->get(route('it-requests.show', $request))
            ->assertInertia(fn (Assert $page) => $page->where('specification', null));

        $this->actingAs($users['it'])->get(route('approvals.show', $itApproval))
            ->assertInertia(fn (Assert $page) => $page
                ->where('availableActions.it_specification.can_edit', true)
                ->where('approval.approvable.specification.specs.0.label', 'CPU'));
    }

    public function test_only_assigned_it_reviewer_can_save_specification(): void
    {
        [$request, $users] = $this->requestAtItStep();
        $otherIt = User::factory()->create(['active' => true]);
        $otherIt->assignRole('it');
        Employee::factory()->create(['user_id' => $otherIt->id, 'active' => true]);

        $this->actingAs($users['owner'])->post(route('it-requests.specification.store', $request), $this->specPayload())
            ->assertSessionHasErrors('specification');
        $this->actingAs($otherIt)->post(route('it-requests.specification.store', $request), $this->specPayload())
            ->assertSessionHasErrors('specification');

        $this->assertDatabaseMissing('it_request_specifications', ['it_request_id' => $request->id]);
    }

    public function test_it_cannot_approve_without_specification(): void
    {
        [$request, $users, $itApproval] = $this->requestAtItStep();

        $this->actingAs($users['it'])->post(route('approvals.action', [$itApproval, 'approve']))
            ->assertSessionHasErrors('specification');

        $this->assertSame(RequestStatus::InReview, $request->refresh()->status);
        $this->assertSame(ApprovalStepStatus::Pending, $itApproval->refresh()->status);
    }

    public function test_it_approval_locks_specification_and_owner_can_view_it(): void
    {
        [$request, $users, $itApproval] = $this->requestAtItStep();

        $this->actingAs($users['it'])->post(route('it-requests.specification.store', $request), $this->specPayload())
            ->assertSessionHasNoErrors();
        $this->actingAs($users['it'])->post(route('approvals.action', [$itApproval, 'approve']))
            ->assertSessionHasNoErrors();

        $specification = $request->refresh()->specification;
        $this->assertNotNull($specification->locked_at);
        $this->assertSame($users['it']->id, (int) $specification->locked_by);

        $this->actingAs($users['owner'])->get(route('it-requests.show', $request))
            ->assertInertia(fn (Assert $page) => $page
                ->where('specification.is_locked', true)
                ->where('specification.specs.0.label', 'CPU'));

        $pmApproval = $request->approvalRequests()->where('step_code', 'pm_gm')->firstOrFail();
        $this->actingAs($users['pm'])->post(route('approvals.action', [$pmApproval, 'approve']))
            ->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::Completed, $request->refresh()->status);

        $html = ItRequestPdf::html($request->refresh());
        $this->assertStringContainsString('Spesifikasi Teknis Ditetapkan IT', $html);
        $this->assertStringContainsString('Intel Core i7', $html);

        $this->assertTrue(Activity::query()
            ->where('subject_type', 'it_request')
            ->where('subject_id', $request->id)
            ->whereIn('description', ['it.specification_saved', 'it.specification_locked'])
            ->exists());
    }

    public function test_template_must_match_device_and_specification_is_snapshotted(): void
    {
        [$request, $users] = $this->requestAtItStep();
        $laptop = ItSpecTemplate::create([
            'code' => 'laptop_standard',
            'name' => 'Laptop Standard',
            'device_type' => 'laptop',
            'specs_json' => [['label' => 'RAM', 'value' => '16 GB']],
            'is_active' => true,
        ]);
        ItSpecTemplate::create([
            'code' => 'desktop_standard',
            'name' => 'Desktop Standard',
            'device_type' => 'desktop',
            'specs_json' => [['label' => 'RAM', 'value' => '32 GB']],
            'is_active' => true,
        ]);

        $desktop = ItSpecTemplate::query()->where('code', 'desktop_standard')->firstOrFail();
        $this->actingAs($users['it'])->post(route('it-requests.specification.store', $request), [
            'template_id' => $desktop->id,
            'specs' => [['label' => 'RAM', 'value' => '32 GB']],
        ])->assertSessionHasErrors('template_id');

        $this->actingAs($users['it'])->post(route('it-requests.specification.store', $request), [
            'template_id' => $laptop->id,
            'specs' => [['label' => 'RAM', 'value' => '64 GB']],
        ])->assertSessionHasNoErrors();

        $laptop->forceFill(['specs_json' => [['label' => 'RAM', 'value' => '128 GB']]])->save();

        $this->assertSame('64 GB', $request->refresh()->specification->specs_json[0]['value']);
    }

    public function test_specification_validation_rejects_empty_and_duplicate_rows(): void
    {
        [$request, $users] = $this->requestAtItStep();

        $this->actingAs($users['it'])->post(route('it-requests.specification.store', $request), [
            'specs' => [['label' => 'CPU', 'value' => 'i7'], ['label' => 'cpu', 'value' => 'i9']],
        ])->assertSessionHasErrors('specs.1.label');

        $this->actingAs($users['it'])->post(route('it-requests.specification.store', $request), [
            'specs' => [],
        ])->assertSessionHasErrors('specs');
    }

    public function test_it_master_can_manage_spec_templates_and_non_admin_cannot(): void
    {
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('admin');
        [$user] = $this->employee('employee');

        $this->actingAs($user)->get(route('master.it-spec-templates.index'))->assertForbidden();

        $this->actingAs($admin)->post(route('master.it-spec-templates.store'), [
            'code' => 'laptop_cad',
            'name' => 'Laptop CAD',
            'device_type' => 'laptop',
            'specs' => [['label' => 'GPU', 'value' => 'RTX A500']],
            'is_active' => true,
            'sort_order' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('it_spec_templates', ['code' => 'laptop_cad', 'device_type' => 'laptop']);

        $template = ItSpecTemplate::query()->where('code', 'laptop_cad')->firstOrFail();
        $this->actingAs($admin)->put(route('master.it-spec-templates.update', $template), [
            'code' => 'laptop_cad',
            'name' => 'Laptop CAD 2026',
            'device_type' => 'laptop',
            'specs' => [['label' => 'GPU', 'value' => 'RTX A1000']],
            'is_active' => false,
            'sort_order' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($template->refresh()->is_active);
        $this->assertSame('Laptop CAD 2026', $template->name);
    }

    /**
     * @return array{0: ItRequest, 1: array<string, User>, 2: ApprovalRequest}
     */
    private function requestAtItStep(): array
    {
        [$owner, $employee] = $this->employee('employee');
        [$hod] = $this->employee('hod');
        [$it] = $this->employee('it');
        [$pm] = $this->employee('project_manager');
        $employee->forceFill(['site' => 'Site-Spec', 'hod_id' => Employee::query()->where('user_id', $hod->id)->value('id')])->save();
        SitePmGmAssignment::create(['site' => 'Site-Spec', 'pm_gm_user_id' => $pm->id, 'is_active' => true]);

        $this->actingAs($owner)->post(route('it-requests.store'), [
            'employee_id' => $employee->id,
            'request_type' => 'new_item',
            'device_type' => 'laptop',
            'needed_date' => now()->addWeek()->toDateString(),
            'priority' => 'normal',
        ])->assertRedirect();

        $request = ItRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($owner)->post(route('it-requests.submit', $request))->assertSessionHasNoErrors();

        $hodApproval = $request->approvalRequests()->where('step_code', 'hod')->firstOrFail();
        $this->actingAs($hod)->post(route('approvals.action', [$hodApproval, 'approve']))->assertRedirect();

        $itApproval = $request->approvalRequests()->where('step_code', 'it')->firstOrFail();

        return [$request, ['owner' => $owner, 'hod' => $hod, 'it' => $it, 'pm' => $pm], $itApproval];
    }

    private function specPayload(): array
    {
        return [
            'specs' => [
                ['label' => 'CPU', 'value' => 'Intel Core i7'],
                ['label' => 'RAM', 'value' => '32 GB'],
                ['label' => 'Storage', 'value' => '1 TB NVMe'],
            ],
            'notes' => 'Untuk pekerjaan office dan analisis data.',
        ];
    }

    /**
     * @return array{0: User, 1: Employee}
     */
    private function employee(string $role): array
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'active' => true]);

        return [$user, $employee];
    }
}
