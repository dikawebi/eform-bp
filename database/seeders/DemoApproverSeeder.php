<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\SitePmGmAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Demo login untuk setiap approver di semua form, sesuai role-nya.
 *
 * - Cuti/Izin & Perjalanan Dinas: supervisor, hod, project_manager, hrga.
 * - Settlement: hrga, finance.
 * - Medical Claim: hrga, hrga_manager (+ finance untuk pembayaran).
 * - IT Request (Hardware & Software): hod, it, project_manager (PM/GM site), coo_ceo.
 * - ERP Request: hod, erp_reviewer, it, project_manager (PM/GM site).
 * - Pelengkap: applicant (pemohon), admin, auditor.
 *
 * Email memakai domain korporat @borneoprima.com agar lolos validasi
 * pendaftaran korporat. Password semua akun: `password`.
 *
 * Seeder ini opt-in (tidak dipanggil DatabaseSeeder) dan idempoten:
 * aman dijalankan ulang tanpa menggandakan user.
 */
class DemoApproverSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const SITE = 'DEMO-SITE';

    /** @var array<string, array{email: string, name: string, roles: list<string>}> */
    private const ACCOUNTS = [
        'applicant' => ['email' => 'demo.pemohon@borneoprima.com', 'name' => 'DEMO Pemohon', 'roles' => ['employee']],
        'supervisor' => ['email' => 'demo.supervisor@borneoprima.com', 'name' => 'DEMO Supervisor', 'roles' => ['employee', 'supervisor']],
        'hod' => ['email' => 'demo.hod@borneoprima.com', 'name' => 'DEMO HOD', 'roles' => ['employee', 'hod']],
        'project_manager' => ['email' => 'demo.pm@borneoprima.com', 'name' => 'DEMO Project Manager', 'roles' => ['employee', 'project_manager']],
        'hrga' => ['email' => 'demo.hrga@borneoprima.com', 'name' => 'DEMO HRGA', 'roles' => ['employee', 'hrga']],
        'hrga_manager' => ['email' => 'demo.hrgamanager@borneoprima.com', 'name' => 'DEMO HRGA Manager', 'roles' => ['employee', 'hrga_manager']],
        'finance' => ['email' => 'demo.finance@borneoprima.com', 'name' => 'DEMO Finance', 'roles' => ['employee', 'finance']],
        'admin' => ['email' => 'demo.admin@borneoprima.com', 'name' => 'DEMO Admin', 'roles' => ['employee', 'admin']],
        'auditor' => ['email' => 'demo.auditor@borneoprima.com', 'name' => 'DEMO Auditor', 'roles' => ['auditor']],
        'it' => ['email' => 'demo.it@borneoprima.com', 'name' => 'DEMO IT', 'roles' => ['employee', 'it']],
        'erp_reviewer' => ['email' => 'demo.erpreviewer@borneoprima.com', 'name' => 'DEMO ERP Reviewer', 'roles' => ['employee', 'erp_reviewer']],
        'coo_ceo' => ['email' => 'demo.cooceo@borneoprima.com', 'name' => 'DEMO COO', 'roles' => ['employee', 'coo', 'coo_ceo']],
        'ceo' => ['email' => 'demo.ceo@borneoprima.com', 'name' => 'DEMO CEO', 'roles' => ['employee', 'ceo']],
    ];

    public function run(): void
    {
        $this->call(RolesPermissionsSeeder::class);

        DB::transaction(function (): void {
            $users = $this->resolveDemoUsers();

            // Hanya relasi pelaporan pemohon yang diatur; field identitas master tidak diubah.
            $applicant = $users['applicant']->employee;
            $applicant->forceFill([
                'supervisor_id' => $users['supervisor']->employee->id,
                'hod_id' => $users['hod']->employee->id,
            ])->save();

            // Site pemohon agar pengajuan IT/ERP bisa disubmit manual;
            // hanya diisi bila masih kosong, tidak menimpa data master.
            if (blank($applicant->site)) {
                $applicant->forceFill(['site' => self::SITE])->save();
            }
            SitePmGmAssignment::query()->firstOrCreate(
                ['site' => $applicant->site],
                ['pm_gm_user_id' => $users['project_manager']->id, 'is_active' => true],
            );
        });
    }

    /** @return array<string, User> */
    private function resolveDemoUsers(): array
    {
        $emails = array_column(self::ACCOUNTS, 'email');
        $existing = User::query()->whereIn('email', $emails)->with('employee')->get()->keyBy('email');

        /** @var array<string, Employee|null> $employees */
        $employees = [];
        foreach (self::ACCOUNTS as $key => $account) {
            $user = $existing->get($account['email']);
            if ($user !== null && $user->employee === null) {
                throw new RuntimeException("Akun demo {$account['email']} sudah ada tetapi tidak terhubung ke master employee.");
            }
            $employees[$key] = $user?->employee;
        }

        $missing = array_keys(array_filter($employees, fn (?Employee $employee) => $employee === null));
        if ($missing !== []) {
            $available = Employee::query()->where('active', true)->whereNull('user_id')->orderBy('employee_number')->lockForUpdate()->get();
            if ($available->count() < count($missing)) {
                throw new RuntimeException('DemoApproverSeeder memerlukan '.count($missing).' master employee aktif yang belum terhubung ke akun. Tidak ada perubahan demo dibuat.');
            }
            foreach (array_values($missing) as $index => $key) {
                $employees[$key] = $available[$index];
            }
        }

        $users = [];
        foreach (self::ACCOUNTS as $key => $account) {
            $user = User::query()->firstOrCreate(['email' => $account['email']], [
                'name' => $account['name'],
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'active' => true,
            ]);
            $user->forceFill([
                'name' => $account['name'],
                'active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $employee = $employees[$key];
            if ((int) $employee->user_id !== 0 && (int) $employee->user_id !== (int) $user->id) {
                throw new RuntimeException("Master employee untuk akun demo {$account['email']} sudah terhubung ke akun lain.");
            }
            if ((int) $employee->user_id !== (int) $user->id) {
                $employee->forceFill(['user_id' => $user->id])->save();
            }

            // Additive seperti seeder lain: tidak mencabut role yang sudah ada.
            $user->syncRoles(array_values(array_unique(array_merge($user->getRoleNames()->all(), $account['roles']))));

            $users[$key] = $user->fresh(['employee']);
        }

        return $users;
    }
}
