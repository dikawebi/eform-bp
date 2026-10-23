<?php

namespace App\Services\Approval;

use App\Models\ApprovalRequest;
use App\Models\ItRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalActionAvailability
{
    public static function for(Model $approvable, User $user): ?array
    {
        $approval = ApprovalRequest::query()
            ->where('approvable_type', $approvable->getMorphClass())
            ->where('approvable_id', $approvable->getKey())
            ->currentChain()
            ->pending()
            ->actionable()
            ->where('approver_user_id', $user->getKey())
            ->first();

        if ($approval === null || ! $user->can('act', $approval)) {
            return null;
        }

        $requiresItSpecification = $approvable instanceof ItRequest && $approval->step_code === 'it';
        $hasItSpecification = ! $requiresItSpecification || $approvable->specification()->exists();

        return [
            'id' => $approval->getKey(),
            'step_code' => $approval->step_code,
            'approve' => $hasItSpecification,
            'return' => true,
            'reject' => true,
            'requires_it_specification' => $requiresItSpecification,
            'has_it_specification' => $hasItSpecification,
            'can_edit_it_specification' => $requiresItSpecification
                && $user->can('saveSpecification', $approvable),
            'specification_url' => $requiresItSpecification
                ? route('approvals.show', $approval)
                : null,
        ];
    }
}
