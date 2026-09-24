<?php

namespace App\Services\Approval;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class ApprovalActorConflicts
{
    /** All identities involved in an on-behalf request and delegation chain. */
    public static function ids(Model $parent, ?ApprovalRequest $approval = null): array
    {
        $snapshot = (array) $parent->employee_snapshot_json;
        $ids = [
            data_get($parent, 'created_by'),
            data_get($parent, 'employee.user_id'),
            data_get($parent, 'employee_id') ? $parent->employee?->user_id : null,
            data_get($snapshot, 'requester.user_id'),
            data_get($snapshot, 'approval_snapshot.requester.user_id'),
            data_get($snapshot, 'beneficiary.user_id'),
            data_get($snapshot, 'approval_snapshot.beneficiary.user_id'),
        ];

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    public static function conflicts(Model $parent, User $actor, ?ApprovalRequest $approval = null): bool
    {
        return in_array((int) $actor->getKey(), self::ids($parent, $approval), true);
    }
}
