<?php

namespace App\Services\Settlement;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateSettlement
{
    public static function run(string $sourceType, int $sourceId, User $actor, array $items = [], ?string $sourceReference = null): Settlement
    {
        return DB::transaction(function () use ($sourceType, $sourceId, $actor, $items, $sourceReference) {
            $manual = ResolveSettlementSource::isManual($sourceType);
            $sourceReference = $manual ? trim((string) $sourceReference) : null;
            $source = ResolveSettlementSource::run($sourceType, $sourceId, lock: true, sourceReference: $sourceReference);
            $allowed = config('eform.settlement.allowed_source_statuses', ['advance_paid', 'settlement_required']);
            if (! $manual && ! in_array($source->status->value, $allowed, true)) {
                throw ValidationException::withMessages(['source_id' => 'Sumber belum eligible untuk settlement.']);
            }
            $advance = $manual ? '0.00' : (string) ($source->total_advance ?? 0);
            if (! $manual && bccomp($advance, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['source_id' => 'Sumber tidak memiliki advance.']);
            }
            $employee = $source instanceof Employee ? $source : $source->employee;
            if (! $employee || ! $employee->active) {
                throw ValidationException::withMessages(['employee' => 'Karyawan sumber tidak aktif.']);
            }
            $owner = (int) $source->created_by === (int) $actor->id || (int) $employee->user_id === (int) $actor->id;
            if (! $owner && ! $actor->can('settlement.create.onbehalf')) {
                throw ValidationException::withMessages(['actor' => 'Anda tidak berhak membuat settlement ini.']);
            }
            $settlement = new Settlement;
            $settlement->forceFill([
                'settlement_number' => SettlementNumber::generate(), 'source_type' => $sourceType, 'source_id' => $sourceId,
                'source_reference' => $sourceReference,
                'leave_request_id' => $sourceType === 'leave_request' ? $sourceId : null,
                'travel_request_id' => $sourceType === 'travel_request' ? $sourceId : null,
                'source_key' => $manual ? $sourceType.':'.$sourceId.':'.hash('sha256', mb_strtolower($sourceReference)) : $sourceType.':'.$sourceId,
                'employee_id' => $employee->id, 'status' => RequestStatus::Draft,
                'advance_amount' => $advance, 'actual_amount' => '0.00', 'difference_amount' => $advance,
                'difference_type' => 'overpayment', 'allow_partial' => false, 'is_final' => true,
                'employee_snapshot_json' => self::snapshot($employee, $actor),
                'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            try {
                $settlement->save();
            } catch (QueryException $exception) {
                if (str_contains(strtolower($exception->getMessage()), 'source_key')) {
                    throw ValidationException::withMessages(['source_id' => 'Sumber sudah memiliki settlement aktif.']);
                }
                throw $exception;
            }
            self::syncItems($settlement, $items);
            activity()->performedOn($settlement)->causedBy($actor)->withProperties(['source_type' => $sourceType, 'source_id' => $sourceId])->log('settlement.created');

            return $settlement->refresh();
        });
    }

    public static function syncItems(Settlement $settlement, array $items): void
    {
        $settlement->items()->delete();
        foreach ($items as $row) {
            $item = $settlement->items()->create(Arr::only($row, ['transaction_date', 'description', 'category', 'receipt_no']));
            $item->forceFill(['amount' => self::money($row['amount'] ?? '0')])->save();
        }
        $result = CalculateSettlement::run($settlement->load('items'));
        $settlement->forceFill($result)->save();
    }

    private static function snapshot(Employee $employee, User $actor): array
    {
        return ['employee_number' => $employee->employee_number, 'name' => $employee->name, 'department' => $employee->department,
            'level' => $employee->level, 'job_title' => $employee->job_title, 'roster' => $employee->roster,
            'employment_status' => $employee->employment_status, 'poh_status' => $employee->poh_status,
            'poh_city' => $employee->poh_city, 'poh_province' => $employee->poh_province,
            'supervisor_id' => $employee->supervisor_id, 'hod_id' => $employee->hod_id,
            'requester' => ['user_id' => $actor->id], 'beneficiary' => ['user_id' => $employee->user_id, 'employee_id' => $employee->id]];
    }

    private static function money(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $value) || bccomp($value, '999999999999.99', 2) === 1) {
            throw ValidationException::withMessages(['items' => 'Nominal item tidak valid.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
