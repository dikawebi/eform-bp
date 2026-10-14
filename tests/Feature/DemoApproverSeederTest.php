<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SitePmGmAssignment;
use App\Models\User;
use Database\Seeders\DemoApproverSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoApproverSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_idempotent_and_links_every_role(): void
    {
        Employee::factory()->count(12)->create(['active' => true, 'user_id' => null]);

        $this->seed(DemoApproverSeeder::class);
        $this->seed(DemoApproverSeeder::class);

        $expectations = [
            'demo.pemohon@borneoprima.com' => ['employee'],
            'demo.supervisor@borneoprima.com' => ['employee', 'supervisor'],
            'demo.hod@borneoprima.com' => ['employee', 'hod'],
            'demo.pm@borneoprima.com' => ['employee', 'project_manager'],
            'demo.hrga@borneoprima.com' => ['employee', 'hrga'],
            'demo.hrgamanager@borneoprima.com' => ['employee', 'hrga_manager'],
            'demo.finance@borneoprima.com' => ['employee', 'finance'],
            'demo.admin@borneoprima.com' => ['employee', 'admin'],
            'demo.auditor@borneoprima.com' => ['auditor'],
            'demo.it@borneoprima.com' => ['employee', 'it'],
            'demo.erpreviewer@borneoprima.com' => ['employee', 'erp_reviewer'],
            'demo.cooceo@borneoprima.com' => ['employee', 'coo_ceo'],
        ];

        $this->assertSame(count($expectations), User::query()->where('email', 'like', '%@borneoprima.com')->count());

        foreach ($expectations as $email => $roles) {
            $user = User::query()->where('email', $email)->with('employee')->firstOrFail();
            $this->assertTrue($user->active);
            $this->assertNotNull($user->employee);
            $this->assertTrue($user->employee->active);
            foreach ($roles as $role) {
                $this->assertTrue($user->hasRole($role), "Akun {$email} harus memiliki role {$role}.");
            }
            // Approval mensyaratkan permission aksi untuk semua approver.
            if ($email !== 'demo.auditor@borneoprima.com' && $email !== 'demo.pemohon@borneoprima.com' && $email !== 'demo.admin@borneoprima.com') {
                $this->assertTrue($user->can('approval.act'), "Akun {$email} harus bisa approval.act.");
            }
        }

        $applicant = User::query()->where('email', 'demo.pemohon@borneoprima.com')->firstOrFail()->employee;
        $supervisor = User::query()->where('email', 'demo.supervisor@borneoprima.com')->firstOrFail()->employee;
        $hod = User::query()->where('email', 'demo.hod@borneoprima.com')->firstOrFail()->employee;
        $pm = User::query()->where('email', 'demo.pm@borneoprima.com')->firstOrFail();
        $this->assertSame((int) $supervisor->id, (int) $applicant->supervisor_id);
        $this->assertSame((int) $hod->id, (int) $applicant->hod_id);
        $this->assertNotEmpty($applicant->site);
        $this->assertDatabaseHas('site_pm_gm_assignments', [
            'site' => $applicant->site,
            'pm_gm_user_id' => $pm->id,
            'is_active' => true,
        ]);
        $this->assertSame(1, SitePmGmAssignment::query()->where('site', $applicant->site)->count());
    }
}
