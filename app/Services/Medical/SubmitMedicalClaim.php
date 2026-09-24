<?php

namespace App\Services\Medical;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\MedicalClaim;
use App\Models\User;
use App\Services\Approval\BuildApprovalChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitMedicalClaim
{
    public static function run(MedicalClaim $claim, User $actor): MedicalClaim
    {
        return DB::transaction(function () use ($claim, $actor) {
            $claim = MedicalClaim::query()->with('items')->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if (! in_array($claim->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
                throw ValidationException::withMessages(['status' => 'Klaim tidak dapat disubmit pada status ini.']);
            }
            $from = $claim->status;
            $employee = Employee::query()->with(['supervisor.user', 'hod.user'])->whereKey($claim->employee_id)->firstOrFail();
            $documents = $claim->attachments()->pluck('document_type')->all();
            foreach (config('eform.medical.required_documents', ['receipt']) as $requiredDocument) {
                if (! in_array($requiredDocument, $documents, true)) {
                    throw ValidationException::withMessages(['attachments' => "Dokumen {$requiredDocument} wajib diunggah sebelum submit."]);
                }
            }
            $snapshot = ['employee_number' => $employee->employee_number, 'name' => $employee->name, 'department' => $employee->department, 'level' => $employee->level, 'job_title' => $employee->job_title, 'poh_status' => $employee->poh_status, 'supervisor_id' => $employee->supervisor_id, 'hod_id' => $employee->hod_id, 'approval_snapshot' => ['supervisor' => ['user_id' => $employee->supervisor?->user_id], 'hod' => ['user_id' => $employee->hod?->user_id]]];
            CalculateMedicalClaim::run($claim);
            $claim->forceFill(['status' => RequestStatus::Submitted, 'submitted_at' => now(), 'employee_number' => $employee->employee_number, 'employee_name' => $employee->name, 'department' => $employee->department, 'employee_snapshot_json' => $snapshot, 'updated_by' => $actor->id])->save();
            BuildApprovalChain::run($claim, $snapshot, $actor);
            activity()->performedOn($claim)->causedBy($actor)->withProperties(['from' => $from->value, 'to' => RequestStatus::Submitted->value])->log('medical.submitted');

            return $claim->refresh();
        });
    }
}
