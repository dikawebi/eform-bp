<?php

namespace App\Services\Leave;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Approval\BuildApprovalChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transisi submit cuti/izin: draft/returned → submitted.
 *
 * Dijalankan server-side dalam DB transaction oleh controller:
 * - Cek ownership + state (jangan percaya status dari frontend).
 * - Employee harus aktif (PRD §6.1).
 * - Hitung ulang total hari + advance dari data tersimpan (defensive).
 * - Isi snapshot profil dari master aktif (PRD §6.2-6.3).
 * - Catat activity log (PRD §6.7).
 */
class SubmitLeaveRequest
{
    /**
     * @throws ValidationException
     */
    public static function run(LeaveRequest $leave, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($leave, $actor) {
            $leave = LeaveRequest::query()->whereKey($leave->getKey())->lockForUpdate()->firstOrFail();
            $leave->loadMissing(['periods', 'costItems', 'employee.supervisor.user', 'employee.hod.user']);

            if (! in_array($leave->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengajuan draft atau returned yang dapat disubmit.',
                ]);
            }
            if (! auth()->check() || (int) auth()->id() !== (int) $actor->id) {
                throw ValidationException::withMessages(['actor' => 'Pengguna submit tidak valid.']);
            }
            if ((int) $leave->created_by !== (int) $actor->id && (int) $leave->employee?->user_id !== (int) $actor->id && ! $actor->can('leave.submit.onbehalf')) {
                throw ValidationException::withMessages(['actor' => 'Anda tidak berhak submit pengajuan ini.']);
            }

            $employee = $leave->employee ?? Employee::query()->find($leave->employee_id);

            if ($employee === null || ! $employee->active) {
                throw ValidationException::withMessages([
                    'employee' => 'Data karyawan tidak aktif sehingga pengajuan tidak dapat disubmit.',
                ]);
            }

            // Hitung ulang defensif dari data tersimpan (jangan percaya total lama).
            $totalDays = 0;
            foreach ($leave->periods as $period) {
                $dayCount = CalculateLeaveDays::daysForPeriod(
                    $period->start_date->toDateString(),
                    $period->end_date->toDateString(),
                );
                if ((int) $period->day_count !== $dayCount) {
                    $period->forceFill(['day_count' => $dayCount])->save();
                }
                $totalDays += $dayCount;
            }

            $isLocal = $employee->poh_status === 'local';
            // Rekalkulasi string-presisi (A3), bukan float mentah.
            $totalAdvanceStr = '0';
            foreach ($leave->costItems as $item) {
                $result = CalculateLeaveAdvance::amountForItem(
                    $isLocal,
                    $item->quantity,
                    $item->unit_price,
                );
                if ((float) $item->amount !== $result['amount'] || (bool) $item->eligible_by_policy !== $result['eligible']) {
                    $item->forceFill([
                        'amount' => $result['amount'],
                        'eligible_by_policy' => $result['eligible'],
                    ])->save();
                }
                $totalAdvanceStr = function_exists('bcadd')
                    ? bcadd($totalAdvanceStr, number_format($result['amount'], 2, '.', ''), 2)
                    : number_format(((float) $totalAdvanceStr) + $result['amount'], 2, '.', '');
            }
            $totalAdvance = (float) $totalAdvanceStr;

            $leave->forceFill([
                'total_days' => $totalDays,
                'total_advance' => round($totalAdvance, 2),
                'is_local' => $isLocal,
                // Snapshot histori (PRD §6.2-6.3).
                'employee_number' => $employee->employee_number,
                'employee_name' => $employee->name,
                'department' => $employee->department,
                'employee_snapshot_json' => [
                    'employee_number' => $employee->employee_number,
                    'name' => $employee->name,
                    'department' => $employee->department,
                    'level' => $employee->level,
                    'job_title' => $employee->job_title,
                    'roster' => $employee->roster,
                    'employment_status' => $employee->employment_status,
                    'poh_status' => $employee->poh_status,
                    'poh_city' => $employee->poh_city,
                    'poh_province' => $employee->poh_province,
                    'supervisor_id' => $employee->supervisor_id,
                    'hod_id' => $employee->hod_id,
                    'approval_snapshot' => [
                        'beneficiary' => ['user_id' => $employee->user_id, 'employee_id' => $employee->getKey()],
                        'requester' => ['user_id' => $actor->getKey()],
                        'supervisor' => ['user_id' => $employee->supervisor?->user_id, 'employee_id' => $employee->supervisor_id],
                        'hod' => ['user_id' => $employee->hod?->user_id, 'employee_id' => $employee->hod_id],
                    ],
                ],
                'status' => RequestStatus::Submitted,
                'submitted_at' => now(),
                'updated_by' => $actor->getKey(),
            ])->save();

            BuildApprovalChain::run($leave, $leave->employee_snapshot_json, $actor);

            activity()
                ->performedOn($leave)
                ->causedBy($actor)
                ->withProperties([
                    'request_number' => $leave->request_number,
                    'from' => $leave->getOriginal('status') instanceof RequestStatus ? $leave->getOriginal('status')->value : (string) $leave->getOriginal('status'),
                    'to' => RequestStatus::Submitted->value,
                    'total_days' => $totalDays,
                    'total_advance' => round($totalAdvance, 2),
                ])
                ->log('leave.submitted');

            return $leave->refresh();
        });
    }
}
