<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdvanceProcessing
{
    public static function run(LeaveRequest|TravelRequest $parent, User $actor): Model
    {
        return DB::transaction(function () use ($parent, $actor): Model {
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $lockedEmployee = Employee::query()->where('user_id', $lockedActor->getKey())->lockForUpdate()->first();
            if (! $lockedActor->active || $lockedEmployee?->active !== true) {
                throw new AuthorizationException('Akun dan data karyawan pemroses harus aktif.');
            }
            $permission = $parent instanceof LeaveRequest ? 'advance.process.leave' : 'advance.process.travel';
            if (! $lockedActor->can($permission)) {
                throw new AuthorizationException('Pengguna tidak berhak memproses advance ini.');
            }

            $locked = $parent::query()->whereKey($parent->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [RequestStatus::Approved, RequestStatus::Processing], true)) {
                if (in_array($locked->status, [RequestStatus::AdvancePaid, RequestStatus::SettlementRequired, RequestStatus::Completed], true)) {
                    return $locked;
                }

                throw ValidationException::withMessages(['status' => 'Pengajuan belum dapat diproses untuk pembayaran advance.']);
            }

            $from = $locked->status;
            $now = now();
            $values = ['updated_by' => $actor->id];

            if ($locked->approved_at === null) {
                $values['approved_at'] = $now;
            }

            if ($from === RequestStatus::Approved) {
                $values['status'] = RequestStatus::Processing;
                $locked->forceFill($values)->save();
                activity()->performedOn($locked)->causedBy($actor)->withProperties([
                    'from' => $from->value, 'to' => RequestStatus::Processing->value, 'actor_id' => $actor->id,
                ])->log('advance.processing');
            }

            $locked->forceFill([
                'status' => RequestStatus::AdvancePaid,
                'advance_paid_at' => $now,
                'updated_by' => $actor->id,
            ])->save();
            activity()->performedOn($locked)->causedBy($actor)->withProperties([
                'from' => RequestStatus::Processing->value,
                'to' => RequestStatus::AdvancePaid->value,
                'actor_id' => $actor->id,
                'total_advance' => (float) $locked->total_advance,
            ])->log('advance.paid');

            $final = (float) $locked->total_advance > 0 ? RequestStatus::SettlementRequired : RequestStatus::Completed;
            $values = [
                'status' => $final,
                'advance_paid_at' => $now,
                'updated_by' => $actor->id,
            ];
            if ($final === RequestStatus::Completed) {
                $values['completed_at'] = $now;
            }
            $locked->forceFill($values)->save();
            activity()->performedOn($locked)->causedBy($actor)->withProperties([
                'from' => RequestStatus::AdvancePaid->value,
                'to' => $final->value,
                'actor_id' => $actor->id,
                'total_advance' => (float) $locked->total_advance,
            ])->log($final === RequestStatus::SettlementRequired ? 'advance.settlement_required' : 'advance.completed');
            app(InAppNotifier::class)->notifyOwner(
                $locked,
                $final === RequestStatus::SettlementRequired ? 'advance.settlement_required' : 'advance.completed',
                $final === RequestStatus::SettlementRequired ? 'Advance telah diproses' : 'Pengajuan selesai diproses',
                $final === RequestStatus::SettlementRequired ? 'Advance telah diproses. Settlement dapat diajukan setelah transaksi selesai.' : 'Pengajuan tanpa advance telah selesai diproses.',
            );

            return $locked->refresh();
        });
    }
}
