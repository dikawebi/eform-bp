<?php

namespace App\Services\Settlement;

use App\Enums\RequestStatus;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Approval\BuildApprovalChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitSettlement
{
    public static function run(Settlement $settlement, User $actor): Settlement
    {
        return DB::transaction(function () use ($settlement, $actor) {
            $settlement = Settlement::query()->with(['items', 'employee', 'leaveRequest', 'travelRequest'])->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if (! in_array($settlement->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
                throw ValidationException::withMessages(['status' => 'Hanya draft atau returned yang dapat disubmit.']);
            }
            $owner = (int) $settlement->created_by === (int) $actor->id || (int) $settlement->employee?->user_id === (int) $actor->id;
            if (! $owner && ! $actor->can('settlement.submit.onbehalf')) {
                throw ValidationException::withMessages(['actor' => 'Anda tidak berhak submit settlement ini.']);
            }
            $source = ResolveSettlementSource::run($settlement->source_type, (int) $settlement->source_id, $settlement, true);
            $manual = ResolveSettlementSource::isManual($settlement->source_type);
            $allowed = config('eform.settlement.allowed_source_statuses', ['advance_paid', 'settlement_required']);
            if ((! $manual && ! in_array($source->status->value, $allowed, true))
                || bccomp($manual ? '0.00' : (string) $source->total_advance, (string) $settlement->advance_amount, 2) !== 0) {
                throw ValidationException::withMessages(['source_id' => 'Sumber advance berubah atau tidak lagi eligible.']);
            }
            $from = $settlement->status;
            $result = CalculateSettlement::run($settlement);
            $settlement->forceFill($result + ['status' => RequestStatus::Submitted, 'submitted_at' => now(), 'updated_by' => $actor->id])->save();
            BuildApprovalChain::run($settlement, $settlement->employee_snapshot_json, $actor);
            activity()->performedOn($settlement)->causedBy($actor)->withProperties(['from' => $from->value, 'to' => RequestStatus::Submitted->value])->log('settlement.submitted');

            return $settlement->refresh();
        });
    }
}
