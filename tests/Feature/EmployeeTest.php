<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_admin_can_create_employee(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('master.employees.store'), [
            'employee_number' => 'NIK-001',
            'name' => 'Budi Santoso',
            'department' => 'HRGA',
            'level' => 'Staff',
            'job_title' => 'Staff HRGA',
            'employment_status' => 'permanent',
            'poh_status' => 'non_local',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', ['employee_number' => 'NIK-001', 'active' => true]);
    }

    public function test_master_employee_menu_is_visible_to_viewers_but_create_requires_manage_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('hod');

        $this->actingAs($viewer)->get(route('master.employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('menus', fn ($menus) => collect($menus)->contains(fn ($menu) => $menu['label'] === 'Karyawan' && $menu['group'] === 'Administrasi'))
                ->where('auth.user.permissions', fn ($permissions) => collect($permissions)->contains('employee.view.any') && ! collect($permissions)->contains('employee.manage')));
    }

    public function test_nik_must_be_unique(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Employee::factory()->create(['employee_number' => 'NIK-DUP']);

        $response = $this->actingAs($admin)->post(route('master.employees.store'), [
            'employee_number' => 'NIK-DUP',
            'name' => 'Duplikat',
            'department' => 'Finance',
            'level' => 'Staff',
            'job_title' => 'Staff',
            'employment_status' => 'permanent',
            'poh_status' => 'local',
        ]);

        $response->assertSessionHasErrors('employee_number');
    }

    public function test_supervisor_cannot_be_self(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employee = Employee::factory()->create();

        $response = $this->actingAs($admin)->put(route('master.employees.update', $employee), [
            'employee_number' => $employee->employee_number,
            'name' => $employee->name,
            'department' => $employee->department,
            'level' => $employee->level,
            'job_title' => $employee->job_title,
            'employment_status' => 'permanent',
            'poh_status' => 'non_local',
            'supervisor_id' => $employee->id,
        ]);

        $response->assertSessionHasErrors('supervisor_id');
    }

    public function test_employee_can_view_own_but_not_others(): void
    {
        $user = User::factory()->create();
        $user->assignRole('employee');

        $own = Employee::factory()->create(['user_id' => $user->id]);
        $other = Employee::factory()->create();

        $this->actingAs($user)
            ->get(route('master.employees.show', $own))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('master.employees.show', $other))
            ->assertForbidden();
    }

    public function test_destroy_deactivates_instead_of_deleting(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employee = Employee::factory()->create(['active' => true]);

        $this->actingAs($admin)
            ->delete(route('master.employees.destroy', $employee))
            ->assertRedirect();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'active' => false]);
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_import_rejects_duplicate_active_nik(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Employee::factory()->create(['employee_number' => 'NIK-EXIST', 'active' => true]);

        $csv = "employee_number,name,department,level,job_title,poh_status\n"
            ."NIK-EXIST,Orang Lama,HRGA,Staff,Staff,local\n"
            .'NIK-NEW,Orang Baru,Finance,Staff,Staff,non_local'."\n";

        $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

        $response = $this->actingAs($admin)->post(route('master.employees.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $result = session('import_result');
        $this->assertSame(1, $result['imported']);
        $this->assertCount(1, $result['failed']);
        $this->assertDatabaseHas('employees', ['employee_number' => 'NIK-NEW']);
        $this->assertDatabaseCount('employees', 2);
    }

    // ---------- B1: created_by/updated_by ----------

    public function test_store_fills_created_by_and_updated_by(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('master.employees.store'), [
            'employee_number' => 'NIK-B1',
            'name' => 'B1 User',
            'department' => 'HRGA',
            'level' => 'Staff',
            'job_title' => 'Staff',
            'employment_status' => 'permanent',
            'poh_status' => 'local',
        ])->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'employee_number' => 'NIK-B1',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_update_fills_updated_by_and_keeps_created_by(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $other = User::factory()->create();
        $other->assignRole('admin');

        $employee = Employee::factory()->create([
            'employee_number' => 'NIK-B1U',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($other)->put(route('master.employees.update', $employee), [
            'employee_number' => 'NIK-B1U',
            'name' => 'Updated Name',
            'department' => $employee->department,
            'level' => $employee->level,
            'job_title' => $employee->job_title,
            'employment_status' => 'contract',
            'poh_status' => 'local',
        ])->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'created_by' => $admin->id,
            'updated_by' => $other->id,
            'name' => 'Updated Name',
        ]);
    }

    // ---------- B2: NIK trim + duplikat ----------

    public function test_nik_with_spaces_is_trimmed_and_duplicate_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Employee::factory()->create(['employee_number' => 'NIK-TRIM']);

        $this->actingAs($admin)->post(route('master.employees.store'), [
            'employee_number' => '  NIK-TRIM  ',
            'name' => 'Dup Trim',
            'department' => 'Finance',
            'level' => 'Staff',
            'job_title' => 'Staff',
            'employment_status' => 'permanent',
            'poh_status' => 'local',
        ])->assertSessionHasErrors('employee_number');
    }

    // ---------- B3: cycle + atasan aktif ----------

    public function test_hierarchy_cycle_a_b_then_b_a_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $a = Employee::factory()->create(['employee_number' => 'NIK-A', 'active' => true]);
        $b = Employee::factory()->create(['employee_number' => 'NIK-B', 'active' => true, 'supervisor_id' => $a->id]);

        // A -> B harus ditolak karena B adalah bawahan A (cycle).
        $response = $this->actingAs($admin)->put(route('master.employees.update', $a), [
            'employee_number' => 'NIK-A',
            'name' => $a->name,
            'department' => $a->department,
            'level' => $a->level,
            'job_title' => $a->job_title,
            'employment_status' => 'permanent',
            'poh_status' => 'non_local',
            'supervisor_id' => $b->id,
        ]);

        $response->assertSessionHasErrors('supervisor_id');
    }

    public function test_inactive_supervisor_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $inactive = Employee::factory()->create(['active' => false]);
        $employee = Employee::factory()->create();

        $this->actingAs($admin)->put(route('master.employees.update', $employee), [
            'employee_number' => $employee->employee_number,
            'name' => $employee->name,
            'department' => $employee->department,
            'level' => $employee->level,
            'job_title' => $employee->job_title,
            'employment_status' => 'permanent',
            'poh_status' => 'non_local',
            'supervisor_id' => $inactive->id,
        ])->assertSessionHasErrors('supervisor_id');
    }

    // ---------- B4: import ----------

    public function test_import_rejects_intra_file_duplicate(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $csv = "employee_number,name,department,level,job_title,poh_status\n"
            ."NIK-F1,Orang Satu,HRGA,Staff,Staff,local\n"
            ."NIK-F1,Orang Satu Dup,HRGA,Staff,Staff,local\n";

        $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

        $this->actingAs($admin)->post(route('master.employees.import'), ['file' => $file])
            ->assertRedirect();

        $result = session('import_result');
        $this->assertSame(1, $result['imported']);
        $this->assertCount(1, $result['failed']);
    }

    public function test_import_rejects_missing_header(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $csv = "name,department,level,job_title\nBudi,HRGA,Staff,Staff\n";
        $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

        $this->actingAs($admin)->post(route('master.employees.import'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_import_rejects_invalid_poh(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $csv = "employee_number,name,department,level,job_title,poh_status\n"
            ."NIK-POH,Orang POH,HRGA,Staff,Staff,invalid_poh\n";
        $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

        $this->actingAs($admin)->post(route('master.employees.import'), ['file' => $file])
            ->assertRedirect();

        $result = session('import_result');
        $this->assertSame(0, $result['imported']);
        $this->assertCount(1, $result['failed']);
        $this->assertDatabaseMissing('employees', ['employee_number' => 'NIK-POH']);
    }

    public function test_import_rejects_csv_injection(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $csv = "employee_number,name,department,level,job_title,poh_status\n"
            ."NIK-INJ,=CMD('calc'),HRGA,Staff,Staff,local\n";
        $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

        $this->actingAs($admin)->post(route('master.employees.import'), ['file' => $file])
            ->assertRedirect();

        $result = session('import_result');
        $this->assertSame(0, $result['imported']);
        $this->assertCount(1, $result['failed']);
    }

    public function test_import_resolves_forward_ref(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Baris pertama mereferensikan NIK yang baru muncul di baris kedua.
        $csv = "employee_number,name,department,level,job_title,poh_status,supervisor_number\n"
            ."NIK-CHILD,Anak,HRGA,Staff,Staff,local,NIK-PARENT\n"
            ."NIK-PARENT,Induk,HRGA,Manager,Manager,local,\n";
        $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

        $this->actingAs($admin)->post(route('master.employees.import'), ['file' => $file])
            ->assertRedirect();

        $result = session('import_result');
        $this->assertSame(2, $result['imported']);
        $this->assertCount(0, $result['failed']);

        $parent = Employee::where('employee_number', 'NIK-PARENT')->firstOrFail();
        $child = Employee::where('employee_number', 'NIK-CHILD')->firstOrFail();
        $this->assertSame($parent->id, $child->supervisor_id);
    }

    public function test_import_rejects_over_1000_rows(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $lines = ['employee_number,name,department,level,job_title,poh_status'];
        for ($i = 1; $i <= 1001; $i++) {
            $lines[] = "NIK-CAP-{$i},Orang {$i},HRGA,Staff,Staff,local";
        }
        $file = UploadedFile::fake()->createWithContent('employees.csv', implode("\n", $lines)."\n");

        $this->actingAs($admin)->post(route('master.employees.import'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    // ---------- B5: destroy ----------

    public function test_destroy_rejected_when_has_active_subordinates(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $leader = Employee::factory()->create(['active' => true]);
        Employee::factory()->create(['active' => true, 'supervisor_id' => $leader->id]);

        $this->actingAs($admin)->delete(route('master.employees.destroy', $leader))
            ->assertSessionHasErrors('employee');

        $this->assertDatabaseHas('employees', ['id' => $leader->id, 'active' => true]);
    }

    public function test_destroy_sets_ended_at(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employee = Employee::factory()->create(['active' => true, 'ended_at' => null]);

        $this->actingAs($admin)->delete(route('master.employees.destroy', $employee))
            ->assertRedirect();

        $employee->refresh();
        $this->assertFalse((bool) $employee->active);
        $this->assertNotNull($employee->ended_at);
        $this->assertSame($admin->id, (int) $employee->updated_by);
    }

    public function test_destroy_with_reassign_succeeds(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $leader = Employee::factory()->create(['active' => true]);
        $replacement = Employee::factory()->create(['active' => true]);
        $sub = Employee::factory()->create(['active' => true, 'supervisor_id' => $leader->id]);

        $this->actingAs($admin)->delete(
            route('master.employees.destroy', $leader).'?reassign_to='.$replacement->id,
            ['reassign_to' => $replacement->id]
        )->assertRedirect();

        $this->assertDatabaseHas('employees', ['id' => $leader->id, 'active' => false]);
        $this->assertDatabaseHas('employees', ['id' => $sub->id, 'supervisor_id' => $replacement->id]);
    }

    public function test_update_ignores_active_flag(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employee = Employee::factory()->create(['active' => true]);

        $this->actingAs($admin)->put(route('master.employees.update', $employee), [
            'employee_number' => $employee->employee_number,
            'name' => $employee->name,
            'department' => $employee->department,
            'level' => $employee->level,
            'job_title' => $employee->job_title,
            'employment_status' => 'permanent',
            'poh_status' => 'non_local',
            'active' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'active' => true]);
    }

    // ---------- B6 ----------

    public function test_employment_status_must_be_in_list(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('master.employees.store'), [
            'employee_number' => 'NIK-EST',
            'name' => 'Status Invalid',
            'department' => 'HRGA',
            'level' => 'Staff',
            'job_title' => 'Staff',
            'employment_status' => 'magang',
            'poh_status' => 'local',
        ])->assertSessionHasErrors('employment_status');
    }

    public function test_non_admin_cannot_set_user_id(): void
    {
        $hrga = User::factory()->create();
        $hrga->assignRole('hrga');
        $link = User::factory()->create();

        $this->actingAs($hrga)->post(route('master.employees.store'), [
            'employee_number' => 'NIK-UID',
            'name' => 'Link User',
            'department' => 'HRGA',
            'level' => 'Staff',
            'job_title' => 'Staff',
            'employment_status' => 'permanent',
            'poh_status' => 'local',
            'user_id' => $link->id,
        ])->assertSessionHasErrors('user_id');
    }

    public function test_admin_can_set_user_id_and_logs_old_new(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $link = User::factory()->create();

        $employee = Employee::factory()->create(['user_id' => null]);

        $this->actingAs($admin)->put(route('master.employees.update', $employee), [
            'employee_number' => $employee->employee_number,
            'name' => $employee->name,
            'department' => $employee->department,
            'level' => $employee->level,
            'job_title' => $employee->job_title,
            'employment_status' => 'permanent',
            'poh_status' => 'non_local',
            'user_id' => $link->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'user_id' => $link->id]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Employee::class,
            'subject_id' => $employee->id,
            'description' => 'employee.updated',
        ]);
    }

    // ---------- G-02/G-03 ----------

    public function test_employee_forbidden_for_master_actions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('employee');

        $employee = Employee::factory()->create();

        $this->actingAs($user)->get(route('master.employees.index'))->assertForbidden();
        $this->actingAs($user)->get(route('master.employees.create'))->assertForbidden();
        $this->actingAs($user)->post(route('master.employees.store'), [])->assertForbidden();
        $this->actingAs($user)->put(route('master.employees.update', $employee), [])->assertForbidden();
        $this->actingAs($user)->delete(route('master.employees.destroy', $employee))->assertForbidden();

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "employee_number,name,department,level,job_title\nNIK-X,X,HRGA,Staff,Staff\n"
        );
        $this->actingAs($user)->post(route('master.employees.import'), ['file' => $file])->assertForbidden();
    }

    public function test_guest_redirected_to_login(): void
    {
        $employee = Employee::factory()->create();

        $this->get(route('master.employees.index'))->assertRedirect(route('login'));
        $this->get(route('master.employees.create'))->assertRedirect(route('login'));
        $this->post(route('master.employees.store'), [])->assertRedirect(route('login'));
        $this->put(route('master.employees.update', $employee), [])->assertRedirect(route('login'));
        $this->delete(route('master.employees.destroy', $employee))->assertRedirect(route('login'));
    }

    public function test_index_search_and_active_filter(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Employee::factory()->create(['name' => 'Budi Searchable', 'department' => 'HRGA', 'active' => true]);
        Employee::factory()->create(['name' => 'Andi Lain', 'department' => 'Finance', 'active' => true]);
        $inactive = Employee::factory()->create(['name' => 'Budi Nonaktif', 'department' => 'HRGA', 'active' => false]);

        // Search menyaring hasil.
        $searchResponse = $this->actingAs($admin)->get(route('master.employees.index', ['search' => 'Searchable']));
        $searchResponse->assertOk();
        $searchProps = $searchResponse->original->getData()['page']['props'];
        $searchNames = collect($searchProps['employees']['data'])->pluck('name')->all();
        $this->assertContains('Budi Searchable', $searchNames);
        $this->assertNotContains('Andi Lain', $searchNames);

        // Filter active=1 menyembunyikan nonaktif.
        $activeResponse = $this->actingAs($admin)->get(route('master.employees.index', ['active' => '1']));
        $activeResponse->assertOk();
        $activeProps = $activeResponse->original->getData()['page']['props'];
        $activeIds = collect($activeProps['employees']['data'])->pluck('id')->all();
        $this->assertNotContains($inactive->id, $activeIds);

        // Filter active=0 menampilkan nonaktif.
        $inactiveResponse = $this->actingAs($admin)->get(route('master.employees.index', ['active' => '0']));
        $inactiveResponse->assertOk();
        $inactiveProps = $inactiveResponse->original->getData()['page']['props'];
        $inactiveIds = collect($inactiveProps['employees']['data'])->pluck('id')->all();
        $this->assertContains($inactive->id, $inactiveIds);
    }
}
