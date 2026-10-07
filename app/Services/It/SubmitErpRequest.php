<?php

namespace App\Services\It;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\ErpRequest;
use App\Models\SitePmGmAssignment;
use App\Models\User;
use App\Services\Approval\BuildApprovalChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitErpRequest
{
    public static function run(ErpRequest $request, User $actor): ErpRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $request = ErpRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! in_array($request->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
                throw ValidationException::withMessages(['status' => 'Request tidak dapat disubmit pada status ini.']);
            }

            $from = $request->status;
            $employee = $request->employee_id
                ? Employee::query()->with(['supervisor.user', 'hod.user'])->whereKey($request->employee_id)->first()
                : null;
            $site = $employee?->site ?? $request->recipient_site;
            if (blank($site)) {
                throw ValidationException::withMessages(['site' => 'Site penerima belum terisi sehingga PM/GM tidak dapat ditentukan.']);
            }
            $assignment = SitePmGmAssignment::query()->where('site', $site)->where('is_active', true)->first();
            if ($assignment === null) {
                throw ValidationException::withMessages(['site' => "Belum ada mapping PM/GM aktif untuk site {$site}. Hubungi administrator."]);
            }

            $snapshot = [
                'employee_number' => $employee?->employee_number,
                'name' => $employee?->name ?? $request->recipient_name,
                'department' => $employee?->department,
                'level' => $employee?->level,
                'job_title' => $employee?->job_title,
                'site' => $site,
                'cost_code' => $employee?->cost_code ?? $request->recipient_cost_code,
                'supervisor_id' => $employee?->supervisor_id,
                'hod_id' => $employee?->hod_id,
                'approval_snapshot' => [
                    'supervisor' => ['user_id' => $employee?->supervisor?->user_id],
                    'hod' => ['user_id' => $employee?->hod?->user_id],
                    'pm_gm' => ['user_id' => $assignment->pm_gm_user_id],
                ],
            ];

            $request->forceFill([
                'status' => RequestStatus::Submitted,
                'submitted_at' => now(),
                'employee_snapshot_json' => $snapshot,
                'updated_by' => $actor->id,
            ])->save();

            BuildApprovalChain::run($request, $snapshot, $actor);
            activity()->performedOn($request)->causedBy($actor)->withProperties(['from' => $from->value, 'to' => RequestStatus::Submitted->value])->log('erp.submitted');

            return $request->refresh();
        });
    }
}
