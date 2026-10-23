<?php

namespace App\Services\It;

use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\ItRequest;
use App\Models\User;
use App\Services\Approval\ApprovalActorConflicts;

final class ItSpecificationAccess
{
    public static function pendingItApproval(ItRequest $request, User $user): ?ApprovalRequest
    {
        return ApprovalRequest::query()
            ->where('approvable_type', $request->getMorphClass())
            ->where('approvable_id', $request->getKey())
            ->currentChain()
            ->pending()
            ->actionable()
            ->where('step_code', 'it')
            ->where('approver_user_id', $user->getKey())
            ->first();
    }

    public static function canEdit(ItRequest $request, User $user): bool
    {
        if ($request->status !== RequestStatus::InReview
            || ! $user->active
            || $user->employee?->active !== true
            || ! $user->can('it_request.review')
            || ! $user->can('approval.act')) {
            return false;
        }

        $approval = self::pendingItApproval($request, $user);

        return $approval !== null
            && ! ApprovalActorConflicts::conflicts($request, $user, $approval);
    }
}
