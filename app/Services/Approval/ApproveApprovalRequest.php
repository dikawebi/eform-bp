<?php

namespace App\Services\Approval;

use App\Enums\ApprovalActionType;
use App\Models\ApprovalRequest;
use App\Models\User;

class ApproveApprovalRequest
{
    /**
     * @param  array{escalation?: string, escalate_user_id?: int|null}|null  $options
     */
    public static function run(ApprovalRequest $request, User $actor, ?array $options = null): ApprovalRequest
    {
        return ApprovalTransition::run($request, $actor, ApprovalActionType::Approve, null, null, $options);
    }
}
