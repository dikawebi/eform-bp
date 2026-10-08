<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    /**
     * 9 role sesuai PRD §4.
     *
     * @var list<string>
     */
    public const ROLES = [
        'employee',
        'supervisor',
        'hod',
        'project_manager',
        'hrga',
        'hrga_manager',
        'finance',
        'admin',
        'auditor',
        'it',
        'erp_reviewer',
        'coo_ceo',
    ];

    /**
     * Permission minimal Phase 0.
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'dashboard.view',

        'employee.view.own',
        'employee.view.any',
        'employee.manage',

        'leave.create.own',
        'leave.create.onbehalf',
        'leave.view.own',
        'leave.view.all',
        'leave.view.subordinates',

        'travel.create.own',
        'travel.create.onbehalf',
        'travel.view.own',
        'travel.view.all',
        'travel.view.subordinates',
        'travel.project.flag',
        'travel.submit.onbehalf',

        'settlement.create.own',
        'settlement.create.onbehalf',
        'settlement.view.own',
        'settlement.view.all',
        'settlement.view.subordinates',
        'settlement.review.hrga',
        'settlement.review.finance',
        'settlement.complete',

        'medical.create.own',
        'medical.view.own',
        'medical.view.all',
        'medical.view.subordinates',
        'medical.review',
        'medical.payment.process',
        'medical.payment.complete',
        'medical.view.sensitive',
        'medical.view.aggregate',

        'it_request.create.own',
        'it_request.create.onbehalf',
        'it.master.manage',
        'it_request.view.own',
        'it_request.view.all',
        'it_request.view.subordinates',
        'it_request.review',

        'erp_request.create.own',
        'erp_request.create.onbehalf',
        'erp_request.view.own',
        'erp_request.view.all',
        'erp_request.view.subordinates',
        'erp_request.review',

        'approval.inbox.view',
        'approval.audit.view',
        'workflow.scope.view',
        'approval.act',

        'attachment.download.own',
        'attachment.download.assigned',
        'attachment.download.all',
        'attachment.download.medical',
        'attachment.upload.own',
        'attachment.upload.assigned',
        'attachment.upload.all',

        'workflow.manage',
        'role.manage',
        'user.manage',
        'report.export',
        'report.view',
        'advance.process.leave',
        'advance.process.travel',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        foreach (self::ROLES as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        $this->assignRolePermissions();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Subset logis per role sesuai PRD:
     * employee = pengajuan milik sendiri,
     * supervisor/hod/pm = review approval,
     * hrga = administrasi + advance + medical,
     * finance = review selisih settlement,
     * admin = semua, auditor = read-only + export.
     */
    protected function assignRolePermissions(): void
    {
        $give = function (string $role, array $permissions): void {
            /** @var Role $roleModel */
            $roleModel = Role::where('name', $role)->where('guard_name', 'web')->firstOrFail();
            // Seed only the baseline permissions. Do not revoke permissions that
            // an administrator has intentionally added to an existing role.
            $roleModel->givePermissionTo($permissions);
        };

        // These permissions used to be assigned to supervisor/hod. Revoke only
        // the obsolete broad read grants; preserve any other custom grants.
        foreach (['supervisor', 'hod'] as $role) {
            Role::where('name', $role)->where('guard_name', 'web')->firstOrFail()
                ->revokePermissionTo(['leave.view.all', 'travel.view.all']);
        }

        $give('admin', array_values(array_diff(self::PERMISSIONS, [
            'advance.process.leave',
            'advance.process.travel',
        ])));

        $give('employee', [
            'dashboard.view',
            'employee.view.own',
            'leave.create.own',
            'leave.view.own',
            'travel.create.own',
            'travel.view.own',
            'settlement.create.own',
            'settlement.view.own',
            'medical.create.own',
            'medical.view.own',
            'it_request.create.own',
            'it_request.view.own',
            'erp_request.create.own',
            'erp_request.view.own',
            'report.view',
            'attachment.download.own',
            'attachment.upload.own',
        ]);

        $give('supervisor', [
            'dashboard.view',
            'employee.view.own',
            'employee.view.any',
            'leave.view.own',
            'leave.view.subordinates',
            'travel.view.own',
            'travel.view.subordinates',
            'settlement.view.subordinates',
            'medical.view.subordinates',
            'it_request.view.subordinates',
            'erp_request.view.subordinates',
            'approval.inbox.view',
            'approval.act',
            'attachment.download.own',
            'attachment.download.assigned',
            'attachment.upload.assigned',
        ]);

        $give('hod', [
            'dashboard.view',
            'employee.view.any',
            'leave.view.own',
            'leave.view.subordinates',
            'travel.view.own',
            'travel.view.subordinates',
            'settlement.view.subordinates',
            'medical.view.subordinates',
            'it_request.view.subordinates',
            'erp_request.view.subordinates',
            'approval.inbox.view',
            'approval.act',
            'attachment.download.own',
            'attachment.download.assigned',
            'attachment.upload.assigned',
        ]);

        $give('project_manager', [
            'dashboard.view',
            'employee.view.any',
            'leave.view.all',
            'travel.view.all',
            'it_request.view.all',
            'erp_request.view.all',
            'approval.inbox.view',
            'approval.act',
            'attachment.download.assigned',
            'attachment.upload.assigned',
        ]);

        $give('it', [
            'dashboard.view',
            'employee.view.any',
            'it_request.view.all',
            'it_request.review',
            'it.master.manage',
            'erp_request.view.all',
            'erp_request.review',
            'approval.inbox.view',
            'approval.act',
            'attachment.download.assigned',
            'attachment.upload.assigned',
        ]);

        $give('erp_reviewer', [
            'dashboard.view',
            'erp_request.view.all',
            'erp_request.review',
            'approval.inbox.view',
            'approval.act',
            'attachment.download.assigned',
            'attachment.upload.assigned',
        ]);

        $give('coo_ceo', [
            'dashboard.view',
            'it_request.view.all',
            'approval.inbox.view',
            'approval.act',
            'attachment.download.assigned',
            'attachment.upload.assigned',
        ]);

        $give('hrga', [
            'dashboard.view',
            'employee.view.own',
            'employee.view.any',
            'employee.manage',
            'leave.view.own',
            'leave.view.all',
            'travel.view.own',
            'travel.view.all',
            'settlement.view.all',
            'settlement.review.hrga',
            'medical.view.own',
            'medical.view.all',
            'medical.review',
            'medical.view.sensitive',
            'approval.inbox.view',
            'approval.act',
            'attachment.download.own',
            'attachment.download.assigned',
            'attachment.upload.assigned',
            'attachment.download.medical',
            'report.export',
            'report.view',
            'advance.process.leave',
            'advance.process.travel',
        ]);

        $give('hrga_manager', [
            'dashboard.view',
            'employee.view.own',
            'employee.view.any',
            'employee.manage',
            'leave.create.onbehalf',
            'leave.view.own',
            'leave.view.all',
            'settlement.create.onbehalf',
            'it_request.create.onbehalf',
            'it_request.view.all',
            'it.master.manage',
            'erp_request.create.onbehalf',
            'erp_request.view.all',
            'travel.create.onbehalf',
            'travel.submit.onbehalf',
            'travel.project.flag',
            'travel.view.own',
            'travel.view.all',
            'settlement.view.all',
            'settlement.review.hrga',
            'medical.view.own',
            'medical.view.all',
            'medical.review',
            'medical.view.sensitive',
            'approval.inbox.view',
            'approval.act',
            'attachment.download.own',
            'attachment.download.assigned',
            'attachment.upload.assigned',
            'attachment.download.medical',
            'workflow.manage',
            'report.export',
            'report.view',
            'advance.process.leave',
            'advance.process.travel',
        ]);

        $give('finance', [
            'dashboard.view',
            'leave.view.all',
            'travel.view.all',
            'settlement.view.all',
            'settlement.review.finance',
            'settlement.complete',
            'medical.payment.process',
            'medical.payment.complete',
            'medical.view.aggregate',
            'approval.inbox.view',
            'approval.act',
            'attachment.download.assigned',
            'attachment.upload.assigned',
            'report.export',
            'report.view',
        ]);

        // Auditor read-only + report.export; SENGAJA tanpa
        // attachment.download.medical (dokumen medis sensitif).
        $give('auditor', [
            'dashboard.view',
            'employee.view.any',
            'leave.view.all',
            'travel.view.all',
            'settlement.view.all',
            'medical.view.aggregate',
            'it_request.view.all',
            'erp_request.view.all',
            'attachment.download.assigned',
            'attachment.upload.assigned',
            'report.export',
            'report.view',
            'approval.audit.view',
        ]);
    }
}
