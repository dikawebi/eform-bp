<?php

namespace App\Services\Approval;

use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateApprovalWorkflow
{
    public static function run(ApprovalWorkflow $workflow, array $data, User $actor): ApprovalWorkflow
    {
        return DB::transaction(function () use ($workflow, $data, $actor): ApprovalWorkflow {
            // Lock only the definition. Existing approval_requests deliberately remain
            // immutable and continue to point at their original workflow/step snapshot.
            $workflow = ApprovalWorkflow::query()->whereKey($workflow->getKey())->lockForUpdate()->firstOrFail();
            $before = [
                'name' => $workflow->name,
                'is_active' => (bool) $workflow->is_active,
                'steps' => $workflow->steps()->get()->map(fn ($step) => [
                    'step_order' => $step->step_order,
                    'step_code' => $step->step_code,
                    'approver_role' => $step->approver_role,
                    'approver_resolver' => $step->approver_resolver,
                    'is_required' => (bool) $step->is_required,
                    'can_skip_if_no_supervisor' => (bool) $step->can_skip_if_no_supervisor,
                ])->values()->all(),
            ];

            $workflow->forceFill([
                'name' => $data['name'],
                'is_active' => (bool) $data['is_active'],
            ])->save();

            // Replace only the definition rows. This cannot mutate approval chain rows.
            $workflow->steps()->delete();
            foreach ($data['steps'] as $step) {
                $workflow->steps()->create([
                    'step_order' => (int) $step['step_order'],
                    'step_code' => $step['step_code'],
                    'approver_role' => $step['approver_role'],
                    'approver_resolver' => $step['approver_resolver'],
                    'is_required' => (bool) $step['is_required'],
                    'can_skip_if_no_supervisor' => (bool) $step['can_skip_if_no_supervisor'],
                ]);
            }

            $workflow->load('steps');
            activity()
                ->performedOn($workflow)
                ->causedBy($actor)
                ->withProperties(['before' => $before, 'after' => [
                    'name' => $workflow->name,
                    'is_active' => (bool) $workflow->is_active,
                    'steps' => $workflow->steps->map(fn ($step) => $step->only([
                        'step_order', 'step_code', 'approver_role', 'approver_resolver',
                        'is_required', 'can_skip_if_no_supervisor',
                    ]))->values()->all(),
                ]])
                ->log('workflow.updated');

            return $workflow;
        });
    }
}
