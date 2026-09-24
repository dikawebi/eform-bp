<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\Attachment;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttachmentPolicy
{
    public function download(User $user, Attachment $attachment): bool
    {
        // Reject other bounded contexts before resolving the morph relation;
        // this also prevents a travel permission from becoming a medical or
        // settlement document permission.
        if (! in_array($attachment->attachable_type, [
            Relation::getMorphAlias(LeaveRequest::class), Relation::getMorphAlias(TravelRequest::class), Relation::getMorphAlias(Settlement::class), Relation::getMorphAlias(MedicalClaim::class),
        ], true)) {
            return false;
        }

        $travel = $attachment->attachable;

        // A generic attachment ID is never sufficient: travel permission must
        // be checked against the actual polymorphic parent.
        if ($travel instanceof MedicalClaim) {
            $owner = (int) $travel->created_by === (int) $user->id || $travel->employee?->user_id === $user->id;

            return app(MedicalClaimPolicy::class)->view($user, $travel)
                && (($owner && $user->can('attachment.download.own'))
                    || ($user->can('medical.view.sensitive') && ($user->can('attachment.download.medical') || $user->can('medical.review'))));
        }
        if (! $travel instanceof TravelRequest && ! $travel instanceof Settlement) {
            return $travel instanceof LeaveRequest && $this->canAccessLeave($user, $travel);
        }

        if ($travel instanceof Settlement) {
            return $user->can('attachment.download.all') || ($user->can('attachment.download.own') && ((int) $travel->created_by === (int) $user->id || (int) $travel->employee?->user_id === (int) $user->id)) || ($user->can('attachment.download.assigned') && $this->isAssigned($user, $travel));
        }

        return $this->canAccessTravel($user, $travel);
    }

    public function upload(User $user, TravelRequest $travel): bool
    {
        if (! in_array($travel->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
            return false;
        }

        return $user->can('attachment.upload.all')
            || ($user->can('attachment.upload.own') && $this->isOwner($user, $travel))
            || ($user->can('attachment.upload.assigned') && $this->isAssigned($user, $travel));
    }

    public function uploadLeave(User $user, LeaveRequest $leave): bool
    {
        if (! $leave->isEditable()) {
            return false;
        }

        return $user->can('attachment.upload.all')
            || ($user->can('attachment.upload.own') && $this->isOwner($user, $leave))
            || ($user->can('attachment.upload.assigned') && $this->isAssigned($user, $leave));
    }

    protected function canAccessTravel(User $user, TravelRequest $travel): bool
    {
        if ($user->can('attachment.download.all')) {
            return true;
        }

        if ($user->can('attachment.download.own') && $this->isOwner($user, $travel)) {
            return true;
        }

        return $user->can('attachment.download.assigned') && $this->isAssigned($user, $travel);
    }

    protected function canAccessLeave(User $user, LeaveRequest $leave): bool
    {
        return $user->can('attachment.download.all')
            || ($user->can('attachment.download.own') && $this->isOwner($user, $leave))
            || ($user->can('attachment.download.assigned') && $this->isAssigned($user, $leave));
    }

    protected function isOwner(User $user, object $travel): bool
    {
        return (int) $travel->created_by === (int) $user->getKey()
            || (int) $travel->employee?->user_id === (int) $user->getKey();
    }

    protected function isAssigned(User $user, object $travel): bool
    {
        // Approval engine is introduced in Phase 4; keep this policy safe when
        // the table is not installed yet and never treat a role as assignment.
        if (! Schema::hasTable('approval_requests')) {
            return false;
        }

        $query = DB::table('approval_requests')
            ->where('approvable_type', $travel->getMorphClass())
            ->where('approvable_id', $travel->getKey())
            ->where('approver_user_id', $user->getKey());

        if (Schema::hasColumn('approval_requests', 'acted_at')) {
            $query->whereNull('acted_at');
        }

        if (Schema::hasColumn('approval_requests', 'status')) {
            $query->whereIn('status', ['pending', 'in_review', 'submitted']);
        }

        return $query->exists();
    }
}
