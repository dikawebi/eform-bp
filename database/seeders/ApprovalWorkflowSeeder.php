<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use Illuminate\Database\Seeder;

class ApprovalWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['leave_default', 'Persetujuan Cuti/Izin', 'leave_request'],
            ['travel_default', 'Persetujuan Perjalanan Dinas', 'travel_request'],
            ['settlement_default', 'Persetujuan Settlement', 'settlement'],
            ['medical_default', 'Persetujuan Medical Claim', 'medical_claim'],
        ];
        foreach ($definitions as [$code, $name, $entity]) {
            // A workflow can be configured by an administrator after installation.
            // Foundation seeding must never reset that configuration on re-run.
            $workflow = ApprovalWorkflow::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'entity_type' => $entity, 'is_active' => true],
            );
            if (! in_array($code, ['leave_default', 'travel_default'], true)) {
                if ($code === 'settlement_default') {
                    foreach ([[1, 'hrga', 'hrga'], [2, 'finance', 'finance']] as [$order, $step, $role]) {
                        $workflow->steps()->firstOrCreate(['step_order' => $order], ['step_code' => $step, 'approver_role' => $role, 'approver_resolver' => 'role_users', 'is_required' => true, 'can_skip_if_no_supervisor' => false]);
                    }
                }

                if ($code === 'medical_default') {
                    foreach ([[1, 'hrga', 'hrga'], [2, 'document_validation', 'hrga_manager']] as [$order, $step, $role]) {
                        $workflow->steps()->firstOrCreate(['step_order' => $order], ['step_code' => $step, 'approver_role' => $role, 'approver_resolver' => 'role_users', 'is_required' => true, 'can_skip_if_no_supervisor' => false]);
                    }
                }

                continue;
            }
            $steps = [
                [1, 'supervisor', 'supervisor', 'supervisor_id', false, true],
                [2, 'hod', 'hod', 'hod_id', true, false],
                // role_users is deliberately configured here (not in a controller). Selection is
                // deterministic: active role members with approval.act, name then id ascending.
                [3, 'pm', 'project_manager', 'assigned_pm', true, false],
                [4, 'hrga', 'hrga', 'role_users', true, false],
            ];
            foreach ($steps as [$order, $step, $role, $resolver, $required, $skip]) {
                $workflow->steps()->firstOrCreate(['step_order' => $order], [
                    'step_code' => $step, 'approver_role' => $role, 'approver_resolver' => $resolver,
                    'is_required' => $required, 'can_skip_if_no_supervisor' => $skip,
                ]);
            }
        }
    }
}
