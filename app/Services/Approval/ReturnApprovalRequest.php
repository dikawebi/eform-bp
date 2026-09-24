<?php

namespace App\Services\Approval;

use App\Enums\ApprovalActionType;
use App\Models\ApprovalRequest;
use App\Models\User;

class ReturnApprovalRequest
{
    public static function run(ApprovalRequest $request, User $actor, string $comments): ApprovalRequest
    {
        return ApprovalTransition::run($request, $actor, ApprovalActionType::Return, $comments);
    }
}
