<?php

namespace App\Policies;

use App\Enums\ApprovalStepStatus;
use App\Models\ApprovalRequest;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Approval\ApprovalActorConflicts;

class ApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('approval.inbox.view') || $user->can('approval.audit.view');
    }

    public function view(User $user, ApprovalRequest $approval): bool
    {
        $isCurrent = $approval->chain_generation !== null && ApprovalRequest::query()
            ->where('approvable_type', $approval->approvable_type)->where('approvable_id', $approval->approvable_id)
            ->orderByDesc('id')->value('chain_generation') === $approval->chain_generation;

        return ($isCurrent && $approval->status === ApprovalStepStatus::Pending && (int) $approval->approver_user_id === (int) $user->getKey() && $user->can('approval.inbox.view'))
            || $user->can('approval.audit.view');
    }

    public function act(User $user, ApprovalRequest $approval): bool
    {
        $user->loadMissing('employee');
        $parent = $approval->approvable;

        $scoped = ! ($parent instanceof Settlement) || ($approval->step_code === 'hrga' ? $user->can('settlement.review.hrga') : ($approval->step_code === 'finance' ? $user->can('settlement.review.finance') : false));

        return $scoped && $user->can('approval.act') && $user->active && $user->employee?->active === true
            && $user->hasRole($approval->approver_role)
            && $approval->status === ApprovalStepStatus::Pending && ((int) $approval->approver_user_id === (int) $user->getKey())
            && $this->isCurrentStep($approval)
            && $parent !== null && ! ApprovalActorConflicts::conflicts($parent, $user, $approval);
    }

    private function isCurrentStep(ApprovalRequest $approval): bool
    {
        $generation = ApprovalRequest::query()->where('approvable_type', $approval->approvable_type)->where('approvable_id', $approval->approvable_id)->orderByDesc('id')->value('chain_generation');

        return $generation === $approval->chain_generation
            && ! ApprovalRequest::query()->where('approvable_type', $approval->approvable_type)->where('approvable_id', $approval->approvable_id)->where('chain_generation', $generation)->where('status', ApprovalStepStatus::Pending)->where('step_order', '<', $approval->step_order)->exists();
    }
}
