<?php

namespace App\Services\Approval;

use App\Models\ApprovalRequest;
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

        return [
            'id' => $approval->getKey(),
            'step_code' => $approval->step_code,
            'approve' => true,
            'return' => true,
            'reject' => true,
        ];
    }
}
