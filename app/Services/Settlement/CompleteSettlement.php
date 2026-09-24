<?php

namespace App\Services\Settlement;

use App\Enums\ApprovalStepStatus;
use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class CompleteSettlement
{
    public static function run(Settlement $settlement, User $actor): Settlement
    {
        return DB::transaction(function () use ($settlement, $actor) {
            $locked = Settlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->with('employee')->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->can('settlement.complete') && $actor->can('settlement.review.finance'), 403);
            abort_unless($actor->active && $actor->employee?->active === true, 403);
            $financeApproval = ApprovalRequest::query()->currentChain()
                ->where('approvable_type', $locked->getMorphClass())
                ->where('approvable_id', $locked->id)
                ->where('step_code', 'finance')
                ->where('status', ApprovalStepStatus::Approved)
                ->where('approver_user_id', $actor->id)
                ->first();
            abort_unless($financeApproval !== null, 403);
            if ($locked->status !== RequestStatus::PaymentProcessing) {
                throw ValidationException::withMessages(['status' => 'Settlement belum berada di proses pembayaran.']);
            }
            $source = ResolveSettlementSource::run($locked->source_type, (int) $locked->source_id, $locked, true);
            $manual = ResolveSettlementSource::isManual($locked->source_type);
            $allowed = config('eform.settlement.allowed_source_statuses', ['advance_paid', 'settlement_required']);
            if ((! $manual && ! in_array($source->status->value, $allowed, true))
                || bccomp($manual ? '0.00' : (string) $source->total_advance, (string) $locked->advance_amount, 2) !== 0) {
                throw ValidationException::withMessages(['source_id' => 'Sumber advance berubah atau tidak dapat diselesaikan.']);
            }
            $from = $locked->status;
            $locked->forceFill(['status' => RequestStatus::Completed, 'completed_at' => now(), 'updated_by' => $actor->id])->save();
            if (! $manual) {
                $sourceFrom = $source->status;
                $sourceData = ['status' => RequestStatus::Completed, 'updated_by' => $actor->id];
                if (Schema::hasColumn($source->getTable(), 'completed_at')) {
                    $sourceData['completed_at'] = now();
                }
                $source->forceFill($sourceData)->save();
                activity()->performedOn($source)->causedBy($actor)->withProperties(['from' => $sourceFrom->value, 'to' => 'completed', 'settlement_id' => $locked->id])->log('source.completed');
            }
            activity()->performedOn($locked)->causedBy($actor)->withProperties(['from' => $from->value, 'to' => 'completed', 'source_id' => $source->id])->log('settlement.completed');
            app(\App\Services\InAppNotifier::class)->notifyOwner(
                $locked,
                'settlement.completed',
                'Settlement selesai diproses',
                'Review dan proses pembayaran settlement Anda telah selesai.',
            );

            return $locked->refresh();
        });
    }
}
