<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
    }

    /**
     * Buat user employee + master aktif terhubung.
     */
    protected function makeEmployeeUser(string $poh = 'non_local'): array
    {
        $user = User::factory()->create();
        $user->assignRole('employee');

        // The default matrix has a required HOD step. Keep the fixture valid
        // instead of weakening the production resolver for tests.
        $hodUser = User::factory()->create();
        $hodUser->assignRole('hod');
        $hod = Employee::factory()->create([
            'user_id' => $hodUser->id,
            'active' => true,
        ]);
        $hrgaUser = User::factory()->create();
        $hrgaUser->assignRole('hrga');
        Employee::factory()->create(['user_id' => $hrgaUser->id, 'active' => true]);

        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'poh_status' => $poh,
            'active' => true,
            'hod_id' => $hod->id,
        ]);

        return [$user, $employee];
    }

    protected function leavePayload(array $overrides = []): array
    {
        return array_merge([
            'leave_type' => 'annual_leave',
            'reason' => 'Pulang ke rumah',
            'last_working_date' => '2026-09-20',
            'onsite_date' => '2026-10-10',
            'periods' => [
                [
                    'category' => 'travel_home',
                    'start_date' => '2026-09-21',
                    'end_date' => '2026-09-23',
                    'notes' => null,
                ],
            ],
            'cost_items' => [],
        ], $overrides);
    }

    // ---------- Kalkulasi hari ----------

    public function test_store_calculates_days_per_period_and_total(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)
            ->post(route('leaves.store'), $this->leavePayload())
            ->assertRedirect();

        /** @var LeaveRequest $leave */
        $leave = LeaveRequest::query()->firstOrFail();

        // Inklusif: 21–23 Sep = 3 hari.
        $this->assertSame(3, (int) $leave->periods()->firstOrFail()->day_count);
        $this->assertSame(3, (int) $leave->total_days);
        $this->assertMatchesRegularExpression('/^CUTI-\d{6}-\d{4}$/', $leave->request_number);
        $this->assertSame($user->id, (int) $leave->created_by);
        $this->assertSame($user->id, (int) $leave->updated_by);
    }

    public function test_store_supports_multiple_periods_with_summed_total(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload([
            'periods' => [
                ['category' => 'travel_home', 'start_date' => '2026-09-21', 'end_date' => '2026-09-22'],
                ['category' => 'annual_leave', 'start_date' => '2026-09-23', 'end_date' => '2026-09-25', 'notes' => 'Cuti'],
                ['category' => 'travel_to_site', 'start_date' => '2026-09-26', 'end_date' => '2026-09-26'],
            ],
        ]))->assertRedirect();

        $leave = LeaveRequest::query()->firstOrFail();
        $dayCounts = $leave->periods()->orderBy('id')->pluck('day_count')->map(fn ($v) => (int) $v)->all();

        $this->assertSame([2, 3, 1], $dayCounts);
        $this->assertSame(6, (int) $leave->total_days);
    }

    // ---------- Aturan lokal/non-lokal ----------

    public function test_local_employee_costs_are_ineligible_with_zero_advance(): void
    {
        [$user] = $this->makeEmployeeUser('local');

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload([
            'cost_items' => [
                ['category' => 'land_transport', 'description' => 'Travel', 'quantity' => 2, 'unit_price' => 150000],
                ['category' => 'meal', 'description' => 'Makan', 'quantity' => 3, 'unit_price' => 50000],
            ],
        ]))->assertRedirect();

        $leave = LeaveRequest::query()->firstOrFail();
        $leave->load('costItems');

        $this->assertTrue((bool) $leave->is_local);
        $this->assertSame(0.0, (float) $leave->total_advance);
        foreach ($leave->costItems as $item) {
            $this->assertFalse((bool) $item->eligible_by_policy);
            $this->assertSame(0.0, (float) $item->amount);
        }
    }

    public function test_leave_costs_keep_workbook_route_and_hotel_details_and_derive_nights(): void
    {
        [$user] = $this->makeEmployeeUser();
        $costItems = [
            ['category' => 'land_transport', 'description' => 'Perjalanan ke bandara', 'quantity' => 1, 'unit_price' => 80000, 'origin' => 'Rumah', 'destination' => 'Bandara', 'flight_destination' => 'Balikpapan', 'departure_time' => '08:30', 'service_date' => '2026-09-24'],
            ['category' => 'hotel', 'description' => 'Penginapan', 'quantity' => 99, 'unit_price' => 200000, 'check_in_date' => '2026-09-24', 'check_out_date' => '2026-09-27'],
        ];

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload(['cost_items' => $costItems]))->assertRedirect();

        $leave = LeaveRequest::query()->with('costItems')->firstOrFail();
        $routeCost = $leave->costItems->firstWhere('category', 'land_transport');
        $hotelCost = $leave->costItems->firstWhere('category', 'hotel');
        $this->assertSame('Rumah', $routeCost->origin);
        $this->assertSame('Balikpapan', $routeCost->flight_destination);
        $this->assertSame('08:30', substr((string) $routeCost->departure_time, 0, 5));
        $this->assertSame('3.00', $hotelCost->quantity);
        $this->assertSame('600000.00', $hotelCost->amount);
    }

    public function test_non_local_advance_is_traceable_to_cost_items(): void
    {
        [$user] = $this->makeEmployeeUser('non_local');

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload([
            'cost_items' => [
                ['category' => 'land_transport', 'description' => 'Travel', 'quantity' => 2, 'unit_price' => 150000],
                ['category' => 'meal', 'description' => 'Makan', 'quantity' => 3, 'unit_price' => 50000],
            ],
        ]))->assertRedirect();

        $leave = LeaveRequest::query()->firstOrFail();
        $leave->load('costItems');

        $this->assertFalse((bool) $leave->is_local);

        $expected = [300000.0, 150000.0];
        $actual = $leave->costItems->map(fn ($i) => (float) $i->amount)->all();
        $this->assertSame($expected, $actual);

        foreach ($leave->costItems as $item) {
            $this->assertTrue((bool) $item->eligible_by_policy);
            $this->assertSame(
                round((float) $item->quantity * (float) $item->unit_price, 2),
                (float) $item->amount
            );
        }

        $this->assertSame(450000.0, (float) $leave->total_advance);
        $this->assertSame(
            round($leave->costItems->sum(fn ($i) => (float) $i->amount), 2),
            (float) $leave->total_advance
        );
    }

    // ---------- Validasi ----------

    public function test_flight_category_is_rejected_for_leave(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload([
            'cost_items' => [
                ['category' => 'flight', 'description' => 'Pesawat', 'quantity' => 1, 'unit_price' => 1000000],
            ],
        ]))->assertSessionHasErrors('cost_items.0.category');

        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_period_end_before_start_is_rejected(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload([
            'periods' => [
                ['category' => 'annual_leave', 'start_date' => '2026-09-25', 'end_date' => '2026-09-23'],
            ],
        ]))->assertSessionHasErrors('periods.0.end_date');

        $this->assertDatabaseCount('leave_requests', 0);
    }

    // ---------- Submit + snapshot + audit ----------

    public function test_submit_fills_snapshot_and_audit_log(): void
    {
        [$user, $employee] = $this->makeEmployeeUser('non_local');

        $this->actingAs($user)
            ->post(route('leaves.store'), $this->leavePayload())
            ->assertRedirect();

        $leave = LeaveRequest::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('leaves.submit', $leave))
            ->assertRedirect();

        $leave->refresh();

        $this->assertSame(RequestStatus::InReview, $leave->status);
        $this->assertNotNull($leave->submitted_at);
        $this->assertSame($employee->employee_number, $leave->employee_number);
        $this->assertSame($employee->name, $leave->employee_name);
        $this->assertSame($employee->department, $leave->department);
        $this->assertNotNull($leave->employee_snapshot_json);
        $this->assertSame($employee->employee_number, $leave->employee_snapshot_json['employee_number']);
        $this->assertSame('non_local', $leave->employee_snapshot_json['poh_status']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'leave_request',
            'subject_id' => $leave->id,
            'description' => 'leave.created',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'leave_request',
            'subject_id' => $leave->id,
            'description' => 'leave.submitted',
        ]);
    }

    public function test_resubmit_after_submitted_is_forbidden(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload());
        $leave = LeaveRequest::query()->firstOrFail();

        $this->actingAs($user)->post(route('leaves.submit', $leave))->assertRedirect();
        $this->actingAs($user)->post(route('leaves.submit', $leave))->assertForbidden();
    }

    public function test_submit_is_rejected_when_required_hod_is_unresolved(): void
    {
        [$user, $employee] = $this->makeEmployeeUser();
        $employee->forceFill(['hod_id' => null])->save();

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload())->assertRedirect();
        $leave = LeaveRequest::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('leaves.submit', $leave))
            ->assertRedirect()
            ->assertSessionHasErrors('approval');

        $this->assertSame(RequestStatus::Draft, $leave->fresh()->status);
        $this->assertDatabaseCount('approval_requests', 0);
    }

    // ---------- Immutability ----------

    public function test_approved_leave_is_immutable(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload());
        $leave = LeaveRequest::query()->firstOrFail();
        $leave->forceFill(['status' => RequestStatus::Approved])->save();

        $this->actingAs($user)
            ->put(route('leaves.update', $leave), $this->leavePayload(['reason' => 'Diubah']))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('leaves.edit', $leave))
            ->assertForbidden();

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'reason' => 'Pulang ke rumah']);
    }

    public function test_returned_leave_is_editable_and_recalculates(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload());
        $leave = LeaveRequest::query()->firstOrFail();
        $leave->forceFill(['status' => RequestStatus::Returned])->save();

        $this->actingAs($user)->put(route('leaves.update', $leave), $this->leavePayload([
            'reason' => 'Revisi alasan',
            'periods' => [
                ['category' => 'annual_leave', 'start_date' => '2026-09-21', 'end_date' => '2026-09-24'],
            ],
        ]))->assertRedirect();

        $leave->refresh();

        $this->assertSame('Revisi alasan', $leave->reason);
        $this->assertSame(4, (int) $leave->total_days);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'leave_request',
            'subject_id' => $leave->id,
            'description' => 'leave.updated',
        ]);
    }

    // ---------- Settlement flag ----------

    public function test_requires_settlement_flag_reflects_advance(): void
    {
        [$user] = $this->makeEmployeeUser('non_local');

        // Tanpa biaya → advance 0 → flag false.
        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload());
        $plain = LeaveRequest::query()->latest('id')->firstOrFail();

        $plainResponse = $this->actingAs($user)->get(route('leaves.show', $plain));
        $plainResponse->assertOk();
        $plainProps = $plainResponse->original->getData()['page']['props'];
        $this->assertFalse($plainProps['requires_settlement']);

        // Dengan biaya → advance > 0 → flag true.
        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload([
            'cost_items' => [
                ['category' => 'meal', 'description' => 'Makan', 'quantity' => 2, 'unit_price' => 75000],
            ],
        ]));
        $withCost = LeaveRequest::query()->latest('id')->firstOrFail();
        $this->assertSame(150000.0, (float) $withCost->total_advance);

        $costResponse = $this->actingAs($user)->get(route('leaves.show', $withCost));
        $costResponse->assertOk();
        $costProps = $costResponse->original->getData()['page']['props'];
        $this->assertTrue($costProps['requires_settlement']);
    }

    // ---------- Ownership & cancel ----------

    public function test_employee_cannot_view_or_edit_others_leave(): void
    {
        [$userA] = $this->makeEmployeeUser();
        [$userB] = $this->makeEmployeeUser();

        $this->actingAs($userA)->post(route('leaves.store'), $this->leavePayload());
        $leave = LeaveRequest::query()->firstOrFail();

        $this->actingAs($userB)->get(route('leaves.show', $leave))->assertForbidden();
        $this->actingAs($userB)->put(route('leaves.update', $leave), $this->leavePayload())->assertForbidden();
        $this->actingAs($userB)->post(route('leaves.submit', $leave))->assertForbidden();
    }

    public function test_index_is_scoped_to_own_leaves(): void
    {
        [$userA] = $this->makeEmployeeUser();
        [$userB] = $this->makeEmployeeUser();

        $this->actingAs($userA)->post(route('leaves.store'), $this->leavePayload());
        $this->actingAs($userB)->post(route('leaves.store'), $this->leavePayload(['reason' => 'Milik B']));

        $response = $this->actingAs($userA)->get(route('leaves.index'));
        $response->assertOk();

        $props = $response->original->getData()['page']['props'];
        $reasons = collect($props['leaves']['data'])->pluck('reason')->all();

        $this->assertContains('Pulang ke rumah', $reasons);
        $this->assertNotContains('Milik B', $reasons);
    }

    public function test_owner_can_cancel_draft_and_it_is_logged(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload());
        $leave = LeaveRequest::query()->firstOrFail();

        $this->actingAs($user)->post(route('leaves.cancel', $leave))->assertRedirect();

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => RequestStatus::Cancelled->value,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'leave_request',
            'subject_id' => $leave->id,
            'description' => 'leave.cancelled',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('leaves.index'))->assertRedirect(route('login'));
        $this->post(route('leaves.store'), [])->assertRedirect(route('login'));
    }

    // ---------- A2 on-behalf ----------

    public function test_view_all_without_onbehalf_cannot_create_for_others(): void
    {
        [$userA, $employeeA] = $this->makeEmployeeUser();
        [$userB, $employeeB] = $this->makeEmployeeUser();

        // User A punya view.all (mis. viewer) tapi TANPA leave.create.onbehalf.
        $userA->givePermissionTo('leave.view.all');
        $this->assertFalse($userA->can('leave.create.onbehalf'));

        // Buat untuk diri sendiri tetap boleh.
        $this->actingAs($userA)
            ->post(route('leaves.store'), $this->leavePayload(['employee_id' => $employeeA->id]))
            ->assertRedirect();

        // Buat untuk orang lain wajib 403 walau punya view.all.
        $this->actingAs($userA)
            ->post(route('leaves.store'), $this->leavePayload(['employee_id' => $employeeB->id]))
            ->assertForbidden();
    }

    public function test_onbehalf_permission_can_create_for_others(): void
    {
        [$admin] = $this->makeEmployeeUser();
        $admin->assignRole('admin');
        [, $employeeB] = $this->makeEmployeeUser();

        $this->assertTrue($admin->can('leave.create.onbehalf'));

        $this->actingAs($admin)
            ->post(route('leaves.store'), $this->leavePayload(['employee_id' => $employeeB->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('leave_requests', ['employee_id' => $employeeB->id]);
    }

    // ---------- A3 server-side fields diabaikan ----------

    public function test_client_supplied_totals_and_status_are_ignored(): void
    {
        [$user] = $this->makeEmployeeUser('non_local');

        $payload = $this->leavePayload([
            // Field server-side yang coba disuntik dari client — harus diabaikan.
            'request_number' => 'FAKE-0001',
            'status' => 'approved',
            'total_days' => 999,
            'total_advance' => 999999999,
            'is_local' => true,
            'periods' => [
                [
                    'category' => 'annual_leave',
                    'start_date' => '2026-09-21',
                    'end_date' => '2026-09-22',
                    'day_count' => 999,
                    'notes' => null,
                ],
            ],
            'cost_items' => [
                [
                    'category' => 'meal',
                    'description' => 'Makan',
                    'quantity' => 2,
                    'unit_price' => 75000,
                    'amount' => 1,
                    'eligible_by_policy' => false,
                ],
            ],
        ]);

        $this->actingAs($user)->post(route('leaves.store'), $payload)->assertRedirect();

        /** @var LeaveRequest $leave */
        $leave = LeaveRequest::query()->firstOrFail();
        $leave->load(['periods', 'costItems']);

        // request_number atomik server, bukan FAKE.
        $this->assertMatchesRegularExpression('/^CUTI-\d{6}-\d{4}$/', $leave->request_number);
        // Status tetap draft.
        $this->assertSame(RequestStatus::Draft, $leave->status);
        // is_local ikut master (non_local → false), bukan true dari client.
        $this->assertFalse((bool) $leave->is_local);
        // day_count dihitung inklusif 21–22 = 2, bukan 999.
        $this->assertSame(2, (int) $leave->periods->firstOrFail()->day_count);
        $this->assertSame(2, (int) $leave->total_days);
        // amount = 2*75000 = 150000 presisi string, bukan 1 dari client.
        $this->assertSame(150000.0, (float) $leave->costItems->firstOrFail()->amount);
        $this->assertTrue((bool) $leave->costItems->firstOrFail()->eligible_by_policy);
        $this->assertSame(150000.0, (float) $leave->total_advance);
    }

    public function test_cost_overflow_is_rejected(): void
    {
        [$user] = $this->makeEmployeeUser('non_local');

        // qty*price = 9999 * 9999999999 >> 999.999.999.999,99 → after-validator.
        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload([
            'cost_items' => [
                ['category' => 'meal', 'description' => 'Overflow', 'quantity' => 9999, 'unit_price' => 9999999999],
            ],
        ]))->assertSessionHasErrors('cost_items.0.quantity');

        $this->assertDatabaseCount('leave_requests', 0);

        // Batas max per-field juga selaras: qty > 9999 ditolak.
        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload([
            'cost_items' => [
                ['category' => 'meal', 'description' => 'X', 'quantity' => 10000, 'unit_price' => 1000],
            ],
        ]))->assertSessionHasErrors('cost_items.0.quantity');
    }

    // ---------- A4 overlap ----------

    public function test_overlapping_periods_are_rejected(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload([
            'periods' => [
                ['category' => 'annual_leave', 'start_date' => '2026-09-21', 'end_date' => '2026-09-23'],
                ['category' => 'annual_leave', 'start_date' => '2026-09-23', 'end_date' => '2026-09-25'],
            ],
        ]))->assertSessionHasErrors('periods');

        $this->assertDatabaseCount('leave_requests', 0);

        // Bersinggungan penuh juga ditolak.
        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload([
            'periods' => [
                ['category' => 'annual_leave', 'start_date' => '2026-09-21', 'end_date' => '2026-09-25'],
                ['category' => 'annual_leave', 'start_date' => '2026-09-22', 'end_date' => '2026-09-23'],
            ],
        ]))->assertSessionHasErrors('periods');

        // Tidak tumpang-tindih tetap lolos.
        $this->actingAs($user)->post(route('leaves.store'), $this->leavePayload([
            'periods' => [
                ['category' => 'annual_leave', 'start_date' => '2026-09-21', 'end_date' => '2026-09-22'],
                ['category' => 'annual_leave', 'start_date' => '2026-09-23', 'end_date' => '2026-09-24'],
            ],
        ]))->assertRedirect();
    }

    // ---------- GAP-01 create/edit pages ----------

    public function test_create_page_owner_ok_and_guest_redirect(): void
    {
        // Guest dulu sebelum actingAs menempel di test ini.
        $this->get(route('leaves.create'))->assertRedirect(route('login'));

        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->get(route('leaves.create'))->assertOk();
    }

    public function test_edit_page_owner_ok_other_forbidden_guest_redirect(): void
    {
        [$userA] = $this->makeEmployeeUser();
        [$userB] = $this->makeEmployeeUser();

        $this->actingAs($userA)->post(route('leaves.store'), $this->leavePayload());
        $leave = LeaveRequest::query()->firstOrFail();

        $this->actingAs($userA)->get(route('leaves.edit', $leave))->assertOk();
        $this->actingAs($userB)->get(route('leaves.edit', $leave))->assertForbidden();

        // actingAs menempel pada test-case; logout dulu agar jadi guest murni.
        auth()->logout();
        $this->get(route('leaves.edit', $leave))->assertRedirect(route('login'));
    }
}
