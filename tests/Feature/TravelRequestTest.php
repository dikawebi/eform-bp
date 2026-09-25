<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\TravelRequest;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TravelRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
    }

    protected function makeEmployeeUser(): array
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
            'active' => true,
            'hod_id' => $hod->id,
        ]);

        return [$user, $employee];
    }

    protected function travelPayload(array $overrides = []): array
    {
        return array_merge([
            'purpose' => 'Audit site',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-03',
            'origin' => 'Balikpapan',
            'destination' => 'Site',
            'is_project_trip' => false,
            'items' => [
                [
                    'category' => 'land_transport',
                    'description' => 'Travel darat',
                    'quantity' => 2,
                    'unit_price' => 150000,
                ],
                [
                    'category' => 'meal',
                    'description' => 'Makan',
                    'quantity' => 3,
                    'unit_price' => 50000,
                ],
            ],
        ], $overrides);
    }

    // ---------- Kalkulasi advance ----------

    public function test_store_calculates_advance_sum_traceable(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)
            ->post(route('travels.store'), $this->travelPayload())
            ->assertRedirect();

        /** @var TravelRequest $travel */
        $travel = TravelRequest::query()->firstOrFail();
        $travel->load('items');

        $this->assertMatchesRegularExpression('/^DINAS-\d{6}-\d{4}$/', $travel->request_number);
        $this->assertSame(RequestStatus::Draft, $travel->status);
        $this->assertSame($user->id, (int) $travel->created_by);

        $expected = [300000.0, 150000.0];
        $actual = $travel->items->map(fn ($i) => (float) $i->amount)->all();
        $this->assertSame($expected, $actual);

        foreach ($travel->items as $item) {
            $this->assertSame(
                round((float) $item->quantity * (float) $item->unit_price, 2),
                (float) $item->amount
            );
        }

        $this->assertSame(450000.0, (float) $travel->total_advance);
        $this->assertSame(
            round($travel->items->sum(fn ($i) => (float) $i->amount), 2),
            (float) $travel->total_advance
        );
    }

    public function test_flight_is_allowed_for_travel_but_does_not_add_to_advance(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload([
            'items' => [
                [
                    'category' => 'flight',
                    'description' => 'Tiket pesawat',
                    'quantity' => 1,
                    'unit_price' => 1000000,
                    'transaction_date' => '2026-10-01',
                    'origin' => 'BPN',
                    'destination' => 'SRQ',
                    'metadata' => ['airline' => 'GA'],
                ],
            ],
        ]))->assertRedirect();

        $travel = TravelRequest::query()->firstOrFail();
        $travel->load('items');

        $this->assertSame(0.0, (float) $travel->total_advance);
        $this->assertSame('flight', $travel->items->firstOrFail()->category);
        $this->assertSame(0.0, (float) $travel->items->firstOrFail()->unit_price);
        $this->assertSame(0.0, (float) $travel->items->firstOrFail()->amount);
        $this->assertSame(['airline' => 'GA'], $travel->items->firstOrFail()->metadata_json);
    }

    public function test_hotel_nights_are_derived_from_structured_check_in_and_check_out(): void
    {
        [$user] = $this->makeEmployeeUser();
        $payload = $this->travelPayload(['items' => [[
            'category' => 'hotel', 'description' => 'Penginapan', 'quantity' => 99, 'unit_price' => 150000,
            'metadata' => ['check_in_date' => '2026-10-01', 'check_out_date' => '2026-10-04'],
        ]]]);

        $this->actingAs($user)->post(route('travels.store'), $payload)->assertRedirect();

        $item = TravelRequest::query()->firstOrFail()->items()->firstOrFail();
        $this->assertSame('3.00', $item->quantity);
        $this->assertSame('450000.00', $item->amount);
        $this->assertSame('3', $item->metadata_json['nights']);
    }

    // ---------- Validasi ----------

    public function test_end_before_start_is_rejected(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload([
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-03',
        ]))->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('travel_requests', 0);
    }

    public function test_invalid_category_is_rejected(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload([
            'items' => [
                ['category' => 'invalid_cat', 'description' => 'X', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ]))->assertSessionHasErrors('items.0.category');

        $this->assertDatabaseCount('travel_requests', 0);
    }

    public function test_cost_overflow_is_rejected(): void
    {
        [$user] = $this->makeEmployeeUser();

        // qty*price = 9999 * 9999999999 >> 999.999.999.999,99 → after-validator.
        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload([
            'items' => [
                ['category' => 'meal', 'description' => 'Overflow', 'quantity' => 9999, 'unit_price' => 9999999999],
            ],
        ]))->assertSessionHasErrors('items.0.quantity');

        $this->assertDatabaseCount('travel_requests', 0);

        // Batas max per-field juga selaras: qty > 9999 ditolak.
        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload([
            'items' => [
                ['category' => 'meal', 'description' => 'X', 'quantity' => 10000, 'unit_price' => 1000],
            ],
        ]))->assertSessionHasErrors('items.0.quantity');
    }

    // ---------- Field client diabaikan ----------

    public function test_client_supplied_totals_and_status_are_ignored(): void
    {
        [$user] = $this->makeEmployeeUser();

        $payload = $this->travelPayload([
            'request_number' => 'FAKE-0001',
            'status' => 'approved',
            'total_advance' => 999999999,
            'items' => [
                [
                    'category' => 'meal',
                    'description' => 'Makan',
                    'quantity' => 2,
                    'unit_price' => 75000,
                    'amount' => 1,
                ],
            ],
        ]);

        $this->actingAs($user)->post(route('travels.store'), $payload)->assertRedirect();

        /** @var TravelRequest $travel */
        $travel = TravelRequest::query()->firstOrFail();
        $travel->load('items');

        $this->assertMatchesRegularExpression('/^DINAS-\d{6}-\d{4}$/', $travel->request_number);
        $this->assertSame(RequestStatus::Draft, $travel->status);
        // amount = 2*75000 = 150000 presisi string, bukan 1 dari client.
        $this->assertSame(150000.0, (float) $travel->items->firstOrFail()->amount);
        $this->assertSame(150000.0, (float) $travel->total_advance);
    }

    // ---------- Submit + snapshot + audit ----------

    public function test_submit_fills_snapshot_and_audit_log(): void
    {
        [$user, $employee] = $this->makeEmployeeUser();

        $this->actingAs($user)
            ->post(route('travels.store'), $this->travelPayload())
            ->assertRedirect();

        $travel = TravelRequest::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('travels.submit', $travel))
            ->assertRedirect();

        $travel->refresh();

        $this->assertSame(RequestStatus::InReview, $travel->status);
        $this->assertNotNull($travel->submitted_at);
        $this->assertSame($employee->employee_number, $travel->employee_number);
        $this->assertSame($employee->name, $travel->employee_name);
        $this->assertSame($employee->department, $travel->department);
        $this->assertNotNull($travel->employee_snapshot_json);
        $this->assertSame($employee->employee_number, $travel->employee_snapshot_json['employee_number']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'travel_request',
            'subject_id' => $travel->id,
            'description' => 'travel.created',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'travel_request',
            'subject_id' => $travel->id,
            'description' => 'travel.submitted',
        ]);
    }

    public function test_approval_snapshot_remains_immutable_when_master_supervisor_changes(): void
    {
        [$owner, $employee] = $this->makeEmployeeUser();
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole('supervisor');
        $supervisor = Employee::factory()->create([
            'user_id' => $supervisorUser->id,
            'employee_number' => 'SUP-001',
            'name' => 'Supervisor Lama',
        ]);
        $employee->forceFill(['supervisor_id' => $supervisor->id])->save();

        $this->actingAs($owner)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::firstOrFail();
        $this->actingAs($owner)
            ->post(route('travels.submit', $travel))
            ->assertRedirect();

        // Approval data is nested in the submitted transaction snapshot.
        $submitted = $travel->fresh();
        $approvalSnapshot = data_get($submitted->employee_snapshot_json, 'approval_snapshot');
        $this->assertIsArray($approvalSnapshot);
        $snapshot = $approvalSnapshot['supervisor'];

        $supervisor->forceFill(['employee_number' => 'SUP-002', 'name' => 'Supervisor Baru'])->save();

        $this->assertSame('SUP-001', $snapshot['nik']);
        $this->assertSame('Supervisor Lama', $snapshot['name']);
        $this->assertSame($supervisor->user_id, $snapshot['user_id']);
    }

    public function test_resubmit_after_submitted_is_forbidden(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::query()->firstOrFail();

        $this->actingAs($user)->post(route('travels.submit', $travel))->assertRedirect();
        $this->actingAs($user)->post(route('travels.submit', $travel))->assertForbidden();
    }

    // ---------- Immutability ----------

    public function test_approved_travel_is_immutable(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::query()->firstOrFail();
        $travel->forceFill(['status' => RequestStatus::Approved])->save();

        $this->actingAs($user)
            ->put(route('travels.update', $travel), $this->travelPayload(['purpose' => 'Diubah']))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('travels.edit', $travel))
            ->assertForbidden();

        $this->assertDatabaseHas('travel_requests', ['id' => $travel->id, 'purpose' => 'Audit site']);
    }

    public function test_returned_travel_is_editable_and_recalculates(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::query()->firstOrFail();
        $travel->forceFill(['status' => RequestStatus::Returned])->save();

        $this->actingAs($user)->put(route('travels.update', $travel), $this->travelPayload([
            'purpose' => 'Revisi tujuan',
            'items' => [
                ['category' => 'hotel', 'description' => 'Hotel', 'quantity' => 2, 'unit_price' => 500000],
            ],
        ]))->assertRedirect();

        $travel->refresh();

        $this->assertSame('Revisi tujuan', $travel->purpose);
        $this->assertSame(1000000.0, (float) $travel->total_advance);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'travel_request',
            'subject_id' => $travel->id,
            'description' => 'travel.updated',
        ]);
    }

    // ---------- Ownership & cancel ----------

    public function test_employee_cannot_view_or_edit_others_travel(): void
    {
        [$userA] = $this->makeEmployeeUser();
        [$userB] = $this->makeEmployeeUser();

        $this->actingAs($userA)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::query()->firstOrFail();

        $this->actingAs($userB)->get(route('travels.show', $travel))->assertForbidden();
        $this->actingAs($userB)->put(route('travels.update', $travel), $this->travelPayload())->assertForbidden();
        $this->actingAs($userB)->post(route('travels.submit', $travel))->assertForbidden();
    }

    public function test_index_is_scoped_to_own_travels(): void
    {
        [$userA] = $this->makeEmployeeUser();
        [$userB] = $this->makeEmployeeUser();

        $this->actingAs($userA)->post(route('travels.store'), $this->travelPayload());
        $this->actingAs($userB)->post(route('travels.store'), $this->travelPayload(['purpose' => 'Milik B']));

        $response = $this->actingAs($userA)->get(route('travels.index'));
        $response->assertOk();

        $props = $response->original->getData()['page']['props'];
        $purposes = collect($props['travels']['data'])->pluck('purpose')->all();

        $this->assertContains('Audit site', $purposes);
        $this->assertNotContains('Milik B', $purposes);
    }

    public function test_owner_can_cancel_draft_and_it_is_logged(): void
    {
        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::query()->firstOrFail();

        $this->actingAs($user)->post(route('travels.cancel', $travel))->assertRedirect();

        $this->assertDatabaseHas('travel_requests', [
            'id' => $travel->id,
            'status' => RequestStatus::Cancelled->value,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'travel_request',
            'subject_id' => $travel->id,
            'description' => 'travel.cancelled',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('travels.index'))->assertRedirect(route('login'));
        $this->post(route('travels.store'), [])->assertRedirect(route('login'));
    }

    // ---------- on-behalf ----------

    public function test_view_all_without_onbehalf_cannot_create_for_others(): void
    {
        [$userA, $employeeA] = $this->makeEmployeeUser();
        [$userB, $employeeB] = $this->makeEmployeeUser();

        // User A punya view.all tapi TANPA travel.create.onbehalf.
        $userA->givePermissionTo('travel.view.all');
        $this->assertFalse($userA->can('travel.create.onbehalf'));

        // Buat untuk diri sendiri tetap boleh.
        $this->actingAs($userA)
            ->post(route('travels.store'), $this->travelPayload(['employee_id' => $employeeA->id]))
            ->assertRedirect();

        // Buat untuk orang lain wajib 403 walau punya view.all.
        $this->actingAs($userA)
            ->post(route('travels.store'), $this->travelPayload(['employee_id' => $employeeB->id]))
            ->assertForbidden();
    }

    public function test_onbehalf_permission_can_create_for_others(): void
    {
        [$admin] = $this->makeEmployeeUser();
        $admin->assignRole('admin');
        [, $employeeB] = $this->makeEmployeeUser();

        $this->assertTrue($admin->can('travel.create.onbehalf'));

        $this->actingAs($admin)
            ->post(route('travels.store'), $this->travelPayload(['employee_id' => $employeeB->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('travel_requests', ['employee_id' => $employeeB->id]);
    }

    // ---------- create/edit pages ----------

    public function test_create_page_owner_ok_and_guest_redirect(): void
    {
        $this->get(route('travels.create'))->assertRedirect(route('login'));

        [$user] = $this->makeEmployeeUser();

        $this->actingAs($user)->get(route('travels.create'))->assertOk();
    }

    public function test_edit_page_owner_ok_other_forbidden_guest_redirect(): void
    {
        [$userA] = $this->makeEmployeeUser();
        [$userB] = $this->makeEmployeeUser();

        $this->actingAs($userA)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::query()->firstOrFail();

        $this->actingAs($userA)->get(route('travels.edit', $travel))->assertOk();
        $this->actingAs($userB)->get(route('travels.edit', $travel))->assertForbidden();

        auth()->logout();
        $this->get(route('travels.edit', $travel))->assertRedirect(route('login'));
    }

    public function test_employee_cannot_enable_project_flag_but_admin_can(): void
    {
        [$employeeUser] = $this->makeEmployeeUser();
        $this->actingAs($employeeUser)->post(route('travels.store'), $this->travelPayload(['is_project_trip' => true]));
        $this->assertFalse((bool) TravelRequest::firstOrFail()->is_project_trip);

        [$admin] = $this->makeEmployeeUser();
        $admin->assignRole('admin');
        $this->actingAs($admin)->post(route('travels.store'), $this->travelPayload(['is_project_trip' => true]));
        $this->assertTrue((bool) TravelRequest::latest('id')->firstOrFail()->is_project_trip);
    }

    public function test_decimal_scale_metadata_and_transaction_date_are_validated(): void
    {
        [$user] = $this->makeEmployeeUser();
        $base = $this->travelPayload(['items' => [[
            'category' => 'flight', 'description' => 'X', 'quantity' => '1.999', 'unit_price' => '10',
            'metadata' => ['airline' => 'GA'], 'transaction_date' => '2026-11-01',
        ]]]);
        $this->actingAs($user)->post(route('travels.store'), $base)->assertSessionHasErrors('items.0.quantity');
        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload(['items' => [[
            'category' => 'flight', 'description' => 'X', 'quantity' => 1, 'unit_price' => 10,
            'metadata' => ['hotel_nights' => 'not-allowed'], 'transaction_date' => '2026-11-01',
        ]]]))->assertSessionHasErrors('items.0.metadata');
    }

    public function test_aggregate_total_overflow_is_rejected(): void
    {
        [$user] = $this->makeEmployeeUser();
        $item = ['category' => 'meal', 'description' => 'Large', 'quantity' => 60, 'unit_price' => 9999999999];
        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload(['items' => [$item, $item]]))
            ->assertSessionHasErrors('items');
        $this->assertDatabaseCount('travel_requests', 0);
    }

    public function test_attachment_is_private_and_owner_only(): void
    {
        Storage::fake('eform-private');
        [$owner] = $this->makeEmployeeUser();
        [$other] = $this->makeEmployeeUser();
        $this->actingAs($owner)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::firstOrFail();
        $this->actingAs($owner)->post(route('travels.attachments.store', $travel), [
            'file' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
            'document_type' => 'supporting_document',
        ])->assertRedirect();
        $attachment = $travel->attachments()->firstOrFail();
        Storage::disk('eform-private')->assertExists($attachment->stored_path);
        $this->actingAs($owner)->get(route('attachments.download', $attachment))->assertOk();
        $this->actingAs($other)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_attachment_download_permission_does_not_grant_upload_permission(): void
    {
        Storage::fake('eform-private');
        [$owner] = $this->makeEmployeeUser();
        $owner->removeRole('employee');
        $owner->givePermissionTo(['travel.create.own', 'travel.view.own', 'attachment.download.own']);
        $this->actingAs($owner)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::firstOrFail();

        $this->actingAs($owner)->post(route('travels.attachments.store', $travel), [
            'file' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
            'document_type' => 'supporting_document',
        ])->assertForbidden();
    }

    public function test_travel_detail_attachment_props_are_safe_dtos(): void
    {
        Storage::fake('eform-private');
        [$owner] = $this->makeEmployeeUser();
        $this->actingAs($owner)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::firstOrFail();
        $this->actingAs($owner)->post(route('travels.attachments.store', $travel), [
            'file' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
            'document_type' => 'supporting_document',
        ]);

        $response = $this->actingAs($owner)->get(route('travels.show', $travel));
        $attachment = $response->original->getData()['page']['props']['travel']['attachments'][0];
        $this->assertArrayHasKey('download_url', $attachment);
        $this->assertArrayNotHasKey('stored_path', $attachment);
    }

    public function test_approved_attachment_remains_downloadable_to_owner(): void
    {
        Storage::fake('eform-private');
        [$owner] = $this->makeEmployeeUser();
        $this->actingAs($owner)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::firstOrFail();
        $attachment = $travel->attachments()->create([
            'document_type' => 'supporting_document',
            'original_name' => 'approved.pdf',
            'stored_path' => 'travel/approved.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'uploaded_by' => $owner->id,
        ]);
        Storage::disk('eform-private')->put($attachment->stored_path, 'proof');
        $travel->forceFill(['status' => RequestStatus::Approved])->save();

        $this->actingAs($owner)->get(route('attachments.download', $attachment))->assertOk();
    }

    public function test_travel_download_rejects_attachment_from_another_module(): void
    {
        [$owner] = $this->makeEmployeeUser();
        $attachment = new Attachment;
        $attachment->forceFill([
            'attachable_type' => LeaveRequest::class,
            'attachable_id' => 999999,
            'original_name' => 'leave.pdf',
            'stored_path' => 'leave/leave.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'uploaded_by' => $owner->id,
        ]);
        $attachment->save();

        $this->actingAs($owner)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_metadata_contract_uses_note_for_each_non_flight_category(): void
    {
        [$user] = $this->makeEmployeeUser();
        $items = collect(['land_transport', 'hotel', 'meal', 'other'])->map(fn (string $category) => [
            'category' => $category,
            'description' => $category,
            'quantity' => 1,
            'unit_price' => 1000,
            'metadata' => $category === 'hotel' ? ['nights' => 2] : ['note' => 'Catatan '.$category],
        ])->all();

        $this->actingAs($user)->post(route('travels.store'), $this->travelPayload(['items' => $items]))->assertRedirect();
        $travel = TravelRequest::firstOrFail()->load('items');
        foreach ($travel->items as $item) {
            $this->assertArrayNotHasKey('reference', $item->metadata_json ?? []);
            $this->assertNotEmpty($item->metadata_json);
        }
    }

    public function test_attachment_upload_is_rejected_after_approval_or_cancellation(): void
    {
        Storage::fake('eform-private');
        [$owner] = $this->makeEmployeeUser();
        $this->actingAs($owner)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::firstOrFail();

        $travel->forceFill(['status' => RequestStatus::Approved])->save();
        $this->actingAs($owner)->post(route('travels.attachments.store', $travel), [
            'file' => UploadedFile::fake()->create('approved.pdf', 10, 'application/pdf'),
            'document_type' => 'supporting_document',
        ])->assertForbidden();

        $travel->forceFill(['status' => RequestStatus::Cancelled])->save();
        $this->actingAs($owner)->post(route('travels.attachments.store', $travel), [
            'file' => UploadedFile::fake()->create('cancelled.pdf', 10, 'application/pdf'),
            'document_type' => 'supporting_document',
        ])->assertForbidden();
    }

    public function test_travel_attachment_validates_extension_and_document_type_and_audits_hash(): void
    {
        Storage::fake('eform-private');
        [$owner] = $this->makeEmployeeUser();
        $this->actingAs($owner)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::firstOrFail();

        $this->actingAs($owner)->post(route('travels.attachments.store', $travel), [
            'file' => UploadedFile::fake()->createWithContent('proof.exe', 'not-an-image'),
            'document_type' => 'supporting_document',
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('attachments', 0);

        $this->actingAs($owner)->post(route('travels.attachments.store', $travel), [
            'file' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
            'document_type' => 'not-allowed',
        ])->assertSessionHasErrors('document_type');
        $this->assertDatabaseCount('attachments', 0);

        $this->actingAs($owner)->post(route('travels.attachments.store', $travel), [
            'file' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
            'document_type' => 'receipt',
        ])->assertRedirect();
        $attachment = $travel->attachments()->firstOrFail();
        $this->assertSame(hash('sha256', Storage::disk('eform-private')->get($attachment->stored_path)), $attachment->sha256_hash);
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'travel_request', 'subject_id' => $travel->id, 'description' => 'travel.attachment_uploaded']);
    }

    public function test_assigned_approver_can_download_travel_attachment(): void
    {
        Storage::fake('eform-private');
        [$owner, $employee] = $this->makeEmployeeUser();
        $travel = TravelRequest::forceCreate([
            'employee_id' => $employee->id, 'request_number' => 'DINAS-ASSIGNED', 'purpose' => 'Review',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'origin' => 'A', 'destination' => 'B',
            'status' => RequestStatus::InReview, 'total_advance' => '10.00', 'created_by' => $owner->id,
        ]);
        $assigned = User::factory()->create(['active' => true]);
        $assigned->assignRole('supervisor');
        Employee::factory()->create(['user_id' => $assigned->id, 'active' => true]);
        ApprovalRequest::create([
            'approvable_type' => 'travel_request', 'approvable_id' => $travel->id, 'chain_generation' => 'assigned-test',
            'step_order' => 1, 'step_code' => 'supervisor', 'approver_role' => 'supervisor',
            'approver_user_id' => $assigned->id, 'status' => 'pending',
        ]);
        $attachment = $travel->attachments()->create([
            'document_type' => 'supporting_document', 'original_name' => 'proof.pdf',
            'stored_path' => 'travel/'.$travel->id.'/proof.pdf', 'mime_type' => 'application/pdf', 'file_size' => 5,
            'uploaded_by' => $owner->id,
        ]);
        Storage::disk('eform-private')->put($attachment->stored_path, 'proof');

        $this->actingAs($assigned)->get(route('attachments.download', $attachment))->assertOk();
    }

    public function test_settlement_eligibility_is_status_aware(): void
    {
        [$owner] = $this->makeEmployeeUser();
        $this->actingAs($owner)->post(route('travels.store'), $this->travelPayload());
        $travel = TravelRequest::firstOrFail();

        $this->assertTrue($travel->hasAdvance());
        $this->assertFalse($travel->needsSettlement());

        $travel->forceFill(['status' => RequestStatus::AdvancePaid])->save();
        $this->assertTrue($travel->needsSettlement());

        $travel->forceFill(['status' => RequestStatus::Completed])->save();
        $this->assertFalse($travel->isSettlementEligible());
    }
}
