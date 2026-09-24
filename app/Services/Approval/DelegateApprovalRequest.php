<?php

namespace App\Services\Approval;

use App\Enums\ApprovalActionType;
use App\Models\ApprovalRequest;
use App\Models\User;

class DelegateApprovalRequest
{
    public static function run(ApprovalRequest $request, User $actor, User $target, ?string $comments = null): ApprovalRequest
    {
        return ApprovalTransition::run($request, $actor, ApprovalActionType::Delegate, $comments, $target);
    }
}
