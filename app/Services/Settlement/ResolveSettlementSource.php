<?php

namespace App\Services\Settlement;

use App\Models\LeaveRequest;
use App\Models\Employee;
use App\Models\Settlement;
use App\Models\TravelRequest;
use Illuminate\Validation\ValidationException;

final class ResolveSettlementSource
{
    public static function isManual(string $sourceType): bool
    {
        return in_array($sourceType, ['new_join', 'other'], true);
    }

    public static function run(string $sourceType, int $sourceId, ?Settlement $settlement = null, bool $lock = false, ?string $sourceReference = null): LeaveRequest|TravelRequest|Employee
    {
        if (self::isManual($sourceType)) {
            $reference = trim((string) ($sourceReference ?? $settlement?->source_reference));
            if ($reference === '' || mb_strlen($reference) > 100) {
                throw ValidationException::withMessages(['source_reference' => 'Nomor referensi wajib diisi (maksimal 100 karakter).']);
            }

            $query = Employee::query()->whereKey($sourceId);
            if ($lock) {
                $query->lockForUpdate();
            }
            $employee = $query->first();
            if (! $employee || ! $employee->active) {
                throw ValidationException::withMessages(['source_id' => 'Karyawan sumber tidak ditemukan atau tidak aktif.']);
            }
            if ($settlement !== null && ($settlement->source_type !== $sourceType
                || (int) $settlement->source_id !== $sourceId
                || (int) $settlement->employee_id !== $sourceId
                || $settlement->leave_request_id !== null
                || $settlement->travel_request_id !== null
                || trim((string) $settlement->source_reference) !== $reference
                || bccomp((string) $settlement->advance_amount, '0.00', 2) !== 0)) {
                throw ValidationException::withMessages(['source_id' => 'Sumber settlement manual tidak konsisten.']);
            }

            return $employee;
        }

        $class = match ($sourceType) {
            'leave_request' => LeaveRequest::class,
            'travel_request' => TravelRequest::class,
            default => throw ValidationException::withMessages(['source_type' => 'Sumber settlement tidak valid.']),
        };

        $query = $class::query()->with('employee')->whereKey($sourceId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $source = $query->first();
        if (! $source) {
            throw ValidationException::withMessages(['source_id' => 'Sumber settlement tidak ditemukan.']);
        }

        if ($settlement !== null) {
            $expectedLeave = $sourceType === 'leave_request' ? $sourceId : null;
            $expectedTravel = $sourceType === 'travel_request' ? $sourceId : null;
            $exactlyOne = ((int) ($settlement->leave_request_id !== null) + (int) ($settlement->travel_request_id !== null)) === 1;
            if (! $exactlyOne || $settlement->source_type !== $sourceType || (int) $settlement->source_id !== $sourceId
                || $settlement->leave_request_id !== $expectedLeave || $settlement->travel_request_id !== $expectedTravel
                || (int) $settlement->employee_id !== (int) $source->employee_id) {
                throw ValidationException::withMessages(['source_id' => 'Sumber settlement tidak konsisten.']);
            }
        }

        if ($settlement !== null && filled($settlement->source_reference)) {
            throw ValidationException::withMessages(['source_reference' => 'Sumber transaksi tidak menerima nomor referensi manual.']);
        }

        return $source;
    }
}
