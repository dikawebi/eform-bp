<?php

namespace App\Services\Approval;

use App\Enums\ApprovalActionType;
use App\Models\ApprovalRequest;
use App\Models\User;

class ApproveApprovalRequest
{
    public static function run(ApprovalRequest $request, User $actor): ApprovalRequest
    {
        return ApprovalTransition::run($request, $actor, ApprovalActionType::Approve);
    }
}
