<?php

namespace App\Services\Approval;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStepStatus;
use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalTransition
{
    public static function run(ApprovalRequest $request, User $actor, ApprovalActionType $action, ?string $comments = null, ?User $delegateTo = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $action, $comments, $delegateTo) {
            $approval = ApprovalRequest::query()->with('approvable')->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $actor = User::query()->with('employee')->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $parent = $approval->approvable;
            if ($parent === null) {
                abort(404);
            }
            $parent = $parent::query()->whereKey($parent->getKey())->lockForUpdate()->firstOrFail();

            $currentGeneration = ApprovalRequest::query()->where('approvable_type', $approval->approvable_type)->where('approvable_id', $approval->approvable_id)->orderByDesc('id')->value('chain_generation');
            if ($approval->status !== ApprovalStepStatus::Pending || $approval->chain_generation === null || $approval->chain_generation !== $currentGeneration) {
                throw ValidationException::withMessages(['approval' => 'Approval ini sudah diproses.']);
            }
            if (ApprovalRequest::query()->where('approvable_type', $approval->approvable_type)->where('approvable_id', $approval->approvable_id)->where('chain_generation', $approval->chain_generation)->where('status', ApprovalStepStatus::Pending)->where('step_order', '<', $approval->step_order)->exists()) {
                throw ValidationException::withMessages(['approval' => 'Approval ini belum menjadi tahap aktif.']);
            }
            if (! in_array($parent->status, [RequestStatus::Submitted, RequestStatus::InReview], true)) {
                throw ValidationException::withMessages(['approval' => 'Pengajuan tidak sedang berada dalam tahap approval.']);
            }
            if ((int) $approval->approver_user_id !== (int) $actor->getKey()) {
                throw ValidationException::withMessages(['approval' => 'Approval ini tidak ditugaskan kepada Anda.']);
            }
            $snapshot = (array) $parent->employee_snapshot_json;
            // DEBUG
            if (! $actor->active || $actor->employee?->active !== true || ! $actor->hasRole($approval->approver_role) || ! $actor->can('approval.act') || ApprovalActorConflicts::conflicts($parent, $actor, $approval)) {
                throw ValidationException::withMessages(['approval' => 'Aktor approval tidak aktif, tidak eligible, atau memiliki konflik kepentingan.']);
            }
            if ($parent instanceof Settlement && (($approval->step_code === 'hrga' && ! $actor->can('settlement.review.hrga')) || ($approval->step_code === 'finance' && ! $actor->can('settlement.review.finance')))) {
                throw ValidationException::withMessages(['approval' => 'Anda tidak memiliki scope review settlement untuk tahap ini.']);
            }
            if ($parent instanceof MedicalClaim && (! $actor->can('medical.review') || ! $actor->can('medical.view.sensitive'))) {
                throw ValidationException::withMessages(['approval' => 'Anda tidak memiliki hak review medical claim.']);
            }
            if (in_array($action, [ApprovalActionType::Reject, ApprovalActionType::Return], true) && trim((string) $comments) === '') {
                throw ValidationException::withMessages(['comments' => 'Komentar wajib diisi untuk reject atau return.']);
            }
            if ($action === ApprovalActionType::Delegate) {
                $delegationConflicts = array_merge(ApprovalActorConflicts::ids($parent, $approval), [(int) $actor->id], $approval->actions()->pluck('actor_id')->map(fn ($id) => (int) $id)->all());
                if ($delegateTo !== null) {
                    $delegateTo->loadMissing('employee');
                }
                if ($delegateTo === null || ! $delegateTo->active || $delegateTo->employee?->active !== true || ! $delegateTo->hasRole($approval->approver_role) || ! $delegateTo->can('approval.act') || ApprovalActorConflicts::conflicts($parent, $delegateTo, $approval) || in_array((int) $delegateTo->id, $delegationConflicts, true) || ApprovalRequest::query()->where('approvable_type', $approval->approvable_type)->where('approvable_id', $approval->approvable_id)->where('chain_generation', $approval->chain_generation)->where('approver_user_id', $delegateTo->id)->where('status', ApprovalStepStatus::Pending)->exists()) {
                    throw ValidationException::withMessages(['delegate_user_id' => 'Pengguna tujuan tidak aktif, tidak eligible, atau memiliki konflik kepentingan.']);
                }
                $approval->forceFill(['approver_user_id' => $delegateTo->getKey(), 'comments' => $comments])->save();
                $approval->actions()->create(['actor_id' => $actor->getKey(), 'action' => $action, 'comments' => $comments]);
                activity()->performedOn($parent)->causedBy($actor)->withProperties(self::auditProperties($approval, $parent, $action, $actor, $comments, ['delegate_user_id' => $delegateTo->id]))->log('approval.delegated');
                app(\App\Services\InAppNotifier::class)->notifyUser($delegateTo->id, 'approval.assigned', 'Approval didelegasikan kepada Anda', 'Ada pengajuan yang didelegasikan dan menunggu pemeriksaan Anda.', route('approvals.show', $approval));

                return $approval->refresh();
            }

            $approval->forceFill(['status' => match ($action) {
                ApprovalActionType::Approve => ApprovalStepStatus::Approved,
                ApprovalActionType::Reject => ApprovalStepStatus::Rejected,
                ApprovalActionType::Return => ApprovalStepStatus::Returned,
                default => ApprovalStepStatus::Cancelled,
            }, 'acted_at' => now(), 'comments' => $comments])->save();
            $approval->actions()->create(['actor_id' => $actor->getKey(), 'action' => $action, 'comments' => $comments]);

            $parentFrom = $parent->status;
            if ($action === ApprovalActionType::Approve) {
                $next = ApprovalRequest::query()->where('approvable_type', $approval->approvable_type)->where('approvable_id', $approval->approvable_id)->where('chain_generation', $approval->chain_generation)->where('status', ApprovalStepStatus::Cancelled)->where('step_order', '>', $approval->step_order)->orderBy('step_order')->lockForUpdate()->first();
                if ($next) {
                    $next->forceFill(['status' => ApprovalStepStatus::Pending])->save();
                    activity()->performedOn($parent)->causedBy($actor)->withProperties(['approval_id' => $next->id, 'generation' => $approval->chain_generation, 'approver_user_id' => $next->approver_user_id, 'reason' => 'previous_step_approved'])->log('approval.step_activated');
                    app(\App\Services\InAppNotifier::class)->notifyUser($next->approver_user_id, 'approval.assigned', 'Approval baru menunggu Anda', 'Tahap berikutnya dari pengajuan telah menunggu pemeriksaan Anda.', route('approvals.show', $next));
                } else {
                    if ($parent instanceof Settlement) {
                        $parent->forceFill(['status' => RequestStatus::PaymentProcessing, 'approved_at' => now()])->save();
                    } else {
                        $parent->forceFill(['status' => RequestStatus::Approved, 'approved_at' => now()])->save();
                    }
                }
            } elseif ($action === ApprovalActionType::Return) {
                $parent->forceFill(['status' => RequestStatus::Returned])->save();
                self::cancelRemaining($approval, $parent, $actor, 'returned');
            } elseif ($action === ApprovalActionType::Reject) {
                $parent->forceFill(['status' => RequestStatus::Rejected])->save();
                self::cancelRemaining($approval, $parent, $actor, 'rejected');
            }
            activity()->performedOn($parent)->causedBy($actor)->withProperties(self::auditProperties($approval, $parent, $action, $actor, $comments, ['from' => $parentFrom->value, 'to' => $parent->status->value]))->log('approval.'.$action->value);

            $event = 'workflow.'.$action->value;
            $title = match ($action) {
                ApprovalActionType::Approve => 'Tahap pengajuan disetujui',
                ApprovalActionType::Return => 'Pengajuan perlu diperbaiki',
                ApprovalActionType::Reject => 'Pengajuan ditolak',
                default => 'Pembaruan pengajuan',
            };
            $message = match ($action) {
                ApprovalActionType::Approve => 'Satu tahap approval pengajuan Anda telah disetujui.',
                ApprovalActionType::Return => 'Pengajuan Anda dikembalikan untuk diperbaiki. Silakan lihat catatan pada transaksi.',
                ApprovalActionType::Reject => 'Pengajuan Anda ditolak. Silakan lihat catatan pada transaksi.',
                default => 'Status pengajuan Anda telah diperbarui.',
            };
            app(\App\Services\InAppNotifier::class)->notifyOwner($parent, $event, $title, $message);

            return $approval->refresh();
        });
    }

    private static function auditProperties(ApprovalRequest $approval, object $parent, ApprovalActionType $action, User $actor, ?string $comments, array $extra = []): array
    {
        $properties = array_merge(['approval_id' => $approval->id, 'action' => $action->value, 'chain_generation' => $approval->chain_generation, 'step' => $approval->step_code, 'actor' => $actor->id], $extra);
        if (! $parent instanceof MedicalClaim) {
            $properties['comments'] = $comments;
        }

        return $properties;
    }

    private static function cancelRemaining(ApprovalRequest $approval, object $parent, User $actor, string $reason): void
    {
        $rows = ApprovalRequest::query()->where('approvable_type', $approval->approvable_type)->where('approvable_id', $approval->approvable_id)->where('chain_generation', $approval->chain_generation)->where('id', '!=', $approval->id)->whereIn('status', [ApprovalStepStatus::Pending, ApprovalStepStatus::Cancelled])->lockForUpdate()->get();
        foreach ($rows as $row) {
            $row->forceFill(['status' => ApprovalStepStatus::Cancelled, 'acted_at' => now(), 'comments' => "Cancelled: {$reason}"])->save();
            activity()->performedOn($parent)->causedBy($actor)->withProperties(['approval_id' => $row->id, 'generation' => $approval->chain_generation, 'reason' => $reason])->log('approval.chain_step_cancelled');
        }
    }
}
