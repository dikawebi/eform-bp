<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\DocumentNumberSequence;
use App\Services\Leave\LeaveRequestNumber;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentSequenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->seed(RolesPermissionsSeeder::class);
        DocumentNumberSequence::ensureDefaults();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_default_generators_keep_the_existing_format(): void
    {
        Carbon::setTestNow('2026-10-23 10:00:00');

        $this->assertSame('CUTI-202610-0001', LeaveRequestNumber::generate());
        $this->assertSame('CUTI-202610-0002', LeaveRequestNumber::generate());
    }

    public function test_sequence_resets_when_the_period_changes(): void
    {
        Carbon::setTestNow('2026-10-23 10:00:00');

        $this->assertSame('CUTI-202610-0001', DocumentNumberSequence::generate('leave_request'));
        $this->assertSame('CUTI-202610-0002', DocumentNumberSequence::generate('leave_request'));

        Carbon::setTestNow('2026-11-01 00:01:00');

        $this->assertSame('CUTI-202611-0001', DocumentNumberSequence::generate('leave_request'));
    }

    public function test_preview_resets_when_the_year_changes(): void
    {
        $sequence = DocumentSequence::query()->where('module', 'leave_request')->firstOrFail();
        $sequence->forceFill(['next_number' => 5, 'last_period' => '202612'])->save();

        Carbon::setTestNow('2027-01-01 00:01:00');

        $this->assertSame(1, $sequence->fresh()->nextNumberForPeriod());
        $this->assertSame('CUTI-202701-0001', $sequence->fresh()->preview());
    }

    public function test_initial_next_number_is_based_on_existing_documents(): void
    {
        Carbon::setTestNow('2026-10-23 10:00:00');

        $employee = Employee::factory()->create();
        $leave = new LeaveRequest;
        $leave->forceFill([
            'request_number' => 'CUTI-202610-0007',
            'employee_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'status' => RequestStatus::Draft,
            'created_by' => $employee->user_id ?? User::factory()->create()->id,
        ])->save();

        DocumentSequence::query()->where('module', 'leave_request')->delete();
        DocumentNumberSequence::ensureDefaults();

        $this->assertSame('CUTI-202610-0008', DocumentNumberSequence::generate('leave_request'));
    }

    public function test_admin_can_update_sequence_and_audit_is_recorded(): void
    {
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->put(route('settings.sequences.update'), [
            'sequences' => [
                'leave_request' => [
                    'prefix' => 'LV',
                    'separator' => '/',
                    'period_format' => 'Ym',
                    'padding' => 5,
                    'next_number' => 42,
                ],
            ],
        ])->assertRedirect();

        $sequence = DocumentSequence::query()->where('module', 'leave_request')->firstOrFail();
        $this->assertSame('LV', $sequence->prefix);
        $this->assertSame('/', $sequence->separator);
        $this->assertSame(5, $sequence->padding);
        $this->assertSame(42, $sequence->next_number);
        $this->assertSame('LV/'.now()->format('Ym').'/00042', $sequence->preview());

        $this->assertDatabaseHas('activity_log', [
            'description' => 'sequence.updated',
            'subject_type' => DocumentSequence::class,
            'subject_id' => $sequence->id,
        ]);
    }

    public function test_sequence_with_used_next_number_is_rejected(): void
    {
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('admin');
        $employee = Employee::factory()->create();
        $creator = User::factory()->create();

        $leave = new LeaveRequest;
        $leave->forceFill([
            'request_number' => 'CUTI-'.now()->format('Ym').'-0001',
            'employee_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'status' => RequestStatus::Draft,
            'created_by' => $creator->id,
        ])->save();

        $this->actingAs($admin)->put(route('settings.sequences.update'), [
            'sequences' => [
                'leave_request' => [
                    'prefix' => 'CUTI',
                    'separator' => '-',
                    'period_format' => 'Ym',
                    'padding' => 4,
                    'next_number' => 1,
                ],
            ],
        ])->assertSessionHasErrors('sequences.leave_request.next_number');
    }

    public function test_non_admin_cannot_open_or_update_sequences(): void
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('employee');

        $this->actingAs($user)->get(route('settings.sequences.index'))->assertForbidden();
        $this->actingAs($user)->put(route('settings.sequences.update'), ['sequences' => []])->assertForbidden();
    }

    public function test_admin_can_open_the_sequence_page(): void
    {
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('settings.sequences.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Sequences/Index')
                ->has('sequences', count(DocumentSequence::MODULES))
                ->where('sequences.0.preview', fn ($preview) => is_string($preview) && $preview !== ''));
    }
}
