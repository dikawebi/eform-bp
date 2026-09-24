<?php

namespace App\Services\Travel;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\Approval\BuildApprovalChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transisi submit perjalanan dinas: draft/returned → submitted.
 *
 * Dijalankan server-side dalam DB transaction oleh controller:
 * - Cek ownership + state (jangan percaya status dari frontend).
 * - Employee harus aktif (PRD §6.1).
 * - Hitung ulang total advance dari data tersimpan (defensive).
 * - Isi snapshot profil dari master aktif (PRD §6.2-6.3).
 * - Catat activity log (PRD §6.7).
 */
class SubmitTravelRequest
{
    /**
     * @throws ValidationException
     */
    public static function run(TravelRequest $travel, User $actor): TravelRequest
    {
        return DB::transaction(function () use ($travel, $actor) {
            if (! auth()->check() || (int) auth()->id() !== (int) $actor->getKey()) {
                throw ValidationException::withMessages([
                    'actor' => 'Pengguna yang submit harus merupakan pengguna terautentikasi.',
                ]);
            }

            $travel = TravelRequest::query()->whereKey($travel->getKey())->lockForUpdate()->firstOrFail();
            $travel->load(['items', 'employee.supervisor.user', 'employee.hod.user']);

            $isOwner = (int) $travel->created_by === (int) $actor->getKey()
                || (int) $travel->employee?->user_id === (int) $actor->getKey();
            if (! $isOwner && ! $actor->can('travel.submit.onbehalf')) {
                throw ValidationException::withMessages([
                    'actor' => 'Anda tidak berhak submit pengajuan milik pengguna lain.',
                ]);
            }

            if (! in_array($travel->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengajuan draft atau returned yang dapat disubmit.',
                ]);
            }
            $from = $travel->status;

            $employee = $travel->employee ?? Employee::query()->find($travel->employee_id);

            if ($employee === null || ! $employee->active) {
                throw ValidationException::withMessages([
                    'employee' => 'Data karyawan tidak aktif sehingga pengajuan tidak dapat disubmit.',
                ]);
            }

            // Hitung ulang defensif dari data tersimpan (jangan percaya total lama).
            // Rekalkulasi string-presisi, bukan float mentah.
            $totalAdvanceStr = '0';
            foreach ($travel->items as $item) {
                $amount = CalculateTravelAdvance::amountForItem(
                    $item->quantity,
                    $item->unit_price,
                );
                if ((string) $item->amount !== $amount) {
                    $item->forceFill(['amount' => $amount])->save();
                }
                $totalAdvanceStr = bcadd($totalAdvanceStr, $amount, 2);
                if (bccomp($totalAdvanceStr, '999999999999.99', 2) === 1) {
                    throw ValidationException::withMessages(['items' => 'Total biaya melebihi batas maksimum.']);
                }
            }
            $approvalSnapshot = [
                'beneficiary' => ['user_id' => $employee->user_id, 'employee_id' => $employee->getKey()],
                'requester' => ['user_id' => $actor->getKey()],
                'supervisor' => [
                    'step_code' => 'supervisor',
                    'approver_role' => 'supervisor',
                    'employee_id' => $employee->supervisor_id,
                    'user_id' => $employee->supervisor?->user_id,
                    'nik' => $employee->supervisor?->employee_number,
                    'name' => $employee->supervisor?->name,
                ],
                'hod' => [
                    'step_code' => 'hod',
                    'approver_role' => 'hod',
                    'employee_id' => $employee->hod_id,
                    'user_id' => $employee->hod?->user_id,
                    'nik' => $employee->hod?->employee_number,
                    'name' => $employee->hod?->name,
                ],
                'pm' => [
                    'step_code' => 'pm', 'approver_role' => 'project_manager',
                    'user_id' => data_get($travel->employee_snapshot_json, 'approval_snapshot.pm.user_id'),
                ],
            ];

            $travel->forceFill([
                'total_advance' => $totalAdvanceStr,
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
                    'supervisor_number' => $employee->supervisor?->employee_number,
                    'supervisor_name' => $employee->supervisor?->name,
                    'hod_number' => $employee->hod?->employee_number,
                    'hod_name' => $employee->hod?->name,
                    'approval_snapshot' => $approvalSnapshot,
                    'is_project_trip' => (bool) $travel->is_project_trip,
                ],
                'status' => RequestStatus::Submitted,
                'submitted_at' => now(),
                'updated_by' => $actor->getKey(),
            ])->save();

            BuildApprovalChain::run($travel, $travel->employee_snapshot_json, $actor);

            activity()
                ->performedOn($travel)
                ->causedBy($actor)
                ->withProperties([
                    'request_number' => $travel->request_number,
                    'from' => $from->value,
                    'to' => RequestStatus::Submitted->value,
                    'total_advance' => $totalAdvanceStr,
                ])
                ->log('travel.submitted');

            if ($from === RequestStatus::Returned) {
                activity()
                    ->performedOn($travel)
                    ->causedBy($actor)
                    ->withProperties([
                        'request_number' => $travel->request_number,
                        'revision' => true,
                        'approval_snapshot' => $approvalSnapshot,
                    ])
                    ->log('travel.revision_submitted');
            }

            return $travel->refresh();
        });
    }
}
