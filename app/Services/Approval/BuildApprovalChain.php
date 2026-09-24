<?php

namespace App\Services\Approval;

use App\Enums\ApprovalStepStatus;
use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\User;
use Database\Seeders\ApprovalWorkflowSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BuildApprovalChain
{
    public static function run(Model $approvable, ?array $snapshot = null, ?User $actor = null): void
    {
        DB::transaction(function () use ($approvable, $snapshot, $actor): void {
            // The parent lock is the serialization point for submit/re-submit.
            $approvable = $approvable::query()->whereKey($approvable->getKey())->lockForUpdate()->firstOrFail();
            $snapshot ??= (array) $approvable->employee_snapshot_json;
            $code = $approvable instanceof LeaveRequest ? 'leave_default' : ($approvable instanceof Settlement ? 'settlement_default' : ($approvable instanceof MedicalClaim ? 'medical_default' : 'travel_default'));
            $workflow = ApprovalWorkflow::query()->where('code', $code)->where('is_active', true)->with('steps')->first();
            if ($workflow === null) {
                app(ApprovalWorkflowSeeder::class)->run();
                $workflow = ApprovalWorkflow::query()->where('code', $code)->where('is_active', true)->with('steps')->firstOrFail();
            }

            $morph = $approvable->getMorphClass();
            $old = ApprovalRequest::query()->where('approvable_type', $morph)
                ->where('approvable_id', $approvable->getKey())
                ->where('status', '!=', ApprovalStepStatus::Cancelled->value)->get();
            foreach ($old as $oldApproval) {
                $oldApproval->forceFill(['status' => ApprovalStepStatus::Cancelled, 'acted_at' => now(), 'comments' => 'Chain invalidated by new revision'])->save();
                self::audit($approvable, 'approval.chain_invalidated', ['approval_id' => $oldApproval->id, 'old_generation' => $oldApproval->chain_generation, 'reason' => 'new_revision'], $actor);
            }

            $generation = (string) Str::uuid();
            $selected = [];

            $activeCreated = false;
            foreach ($workflow->steps as $step) {
                if ($step->step_code === 'pm' && ! (bool) data_get($approvable, 'is_project_trip', false)) {
                    self::create($approvable, $workflow, $step, null, ApprovalStepStatus::Skipped, $generation, $actor);
                    self::audit($approvable, 'approval.step_skipped', ['step_code' => $step->step_code, 'generation' => $generation, 'reason' => 'not_project_trip'], $actor);

                    continue;
                }

                if ($step->step_code === 'pm' && (bool) data_get($approvable, 'is_project_trip', false)
                    && self::snapshotUserByPaths($snapshot, ['approval_snapshot.pm.user_id', 'approval_snapshot.project_manager.user_id', 'project_manager.user_id', 'project_manager_id']) === null) {
                    throw ValidationException::withMessages(['project_manager' => 'Perjalanan project wajib memiliki PM yang sudah disimpan di snapshot sebelum approval chain dibuat.']);
                }

                $userId = self::resolveUserId($step, $snapshot, $approvable, $selected);
                $hasSupervisor = self::snapshotUser($snapshot, 'supervisor_id') !== null;
                if ($userId === null && $step->step_code === 'supervisor' && $step->can_skip_if_no_supervisor && ! $hasSupervisor) {
                    self::create($approvable, $workflow, $step, null, ApprovalStepStatus::Skipped, $generation, $actor);
                    self::audit($approvable, 'approval.step_skipped', ['step_code' => $step->step_code, 'generation' => $generation, 'reason' => 'optional_supervisor_missing'], $actor);

                    continue;
                }
                if ($userId === null) {
                    throw ValidationException::withMessages(['approval' => "Approver wajib untuk langkah {$step->step_code} belum dikonfigurasi."]);
                }
                $status = $activeCreated ? ApprovalStepStatus::Cancelled : ApprovalStepStatus::Pending;
                $created = self::create($approvable, $workflow, $step, $userId, $status, $generation, $actor);
                if ($status === ApprovalStepStatus::Pending) {
                    app(\App\Services\InAppNotifier::class)->notifyUser(
                        $userId,
                        'approval.assigned',
                        'Approval baru menunggu Anda',
                        'Ada pengajuan baru yang perlu Anda periksa.',
                        route('approvals.show', $created),
                    );
                }
                self::persistSnapshot($approvable, $snapshot, $step, $created->approver);
                $selected[] = $userId;
                $activeCreated = true;
            }

            $approvable->forceFill(['status' => RequestStatus::InReview])->save();
            self::audit($approvable, 'approval.chain_created', ['generation' => $generation, 'workflow_id' => $workflow->id, 'approver_ids' => $selected], $actor);
        });
    }

    private static function create(Model $parent, ApprovalWorkflow $workflow, ApprovalWorkflowStep $step, ?int $userId, ApprovalStepStatus $status, string $generation, ?User $actor = null): ApprovalRequest
    {
        $approval = ApprovalRequest::create([
            'approvable_type' => $parent->getMorphClass(), 'approvable_id' => $parent->getKey(),
            'chain_generation' => $generation, 'workflow_id' => $workflow->getKey(), 'step_order' => $step->step_order,
            'step_code' => $step->step_code, 'approver_role' => $step->approver_role,
            'approver_user_id' => $userId, 'status' => $status, 'due_at' => now()->addDays(3),
        ]);
        self::audit($parent, 'approval.step_created', ['approval_id' => $approval->id, 'step_code' => $step->step_code, 'generation' => $generation, 'approver_user_id' => $userId, 'status' => $status->value], $actor);

        return $approval;
    }

    private static function resolveUserId(ApprovalWorkflowStep $step, array $snapshot, Model $parent, array $selected = []): ?int
    {
        if (! in_array($step->approver_resolver, ['supervisor_id', 'hod_id', 'assigned_pm', 'role_users'], true)
            || trim((string) $step->approver_role) === '') {
            throw ValidationException::withMessages(['approval' => "Resolver approval untuk langkah {$step->step_code} tidak valid."]);
        }
        $id = match ($step->approver_resolver) {
            'supervisor_id' => self::snapshotUser($snapshot, 'supervisor_id'),
            'hod_id' => self::snapshotUser($snapshot, 'hod_id'),
            'assigned_pm' => self::snapshotUserByPaths($snapshot, [
                'approval_snapshot.pm.user_id',
                'approval_snapshot.project_manager.user_id',
                'project_manager.user_id',
                'project_manager_id',
            ]),
            'role_users' => User::query()->where('active', true)->role($step->approver_role)->permission('approval.act')->whereHas('employee', fn ($e) => $e->where('active', true))->whereNotIn('id', $selected)->orderBy('name')->orderBy('id')->get()->first(fn (User $user) => ! ApprovalActorConflicts::conflicts($parent, $user))?->id,
            default => null,
        };
        $forbidden = ApprovalActorConflicts::ids($parent);
        if ($id === null || in_array((int) $id, array_merge($forbidden, $selected), true)) {
            return null;
        }

        return User::query()->whereKey($id)->where('active', true)->role($step->approver_role)->permission('approval.act')->whereHas('employee', fn ($e) => $e->where('active', true))->exists() ? (int) $id : null;
    }

    private static function snapshotUserByPaths(array $snapshot, array $paths): ?int
    {
        foreach ($paths as $path) {
            $value = data_get($snapshot, $path);
            if ($value !== null && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    }

    private static function snapshotUser(array $snapshot, string $key): ?int
    {
        $role = $key === 'supervisor_id' ? 'supervisor' : ($key === 'hod_id' ? 'hod' : $key);

        $snapshotValue = data_get($snapshot, "approval_snapshot.{$role}.user_id")
            ?? data_get($snapshot, $key.'.user_id')
            ?? data_get($snapshot, $key);
        // supervisor_id/hod_id in the transaction snapshot are employee IDs;
        // only approval_snapshot.*.user_id is already a login ID.
        if ($snapshotValue !== null && ! data_get($snapshot, "approval_snapshot.{$role}.user_id") && ! data_get($snapshot, $key.'.user_id')) {
            return (int) (Employee::query()->whereKey($snapshotValue)->value('user_id') ?? 0) ?: null;
        }

        return $snapshotValue === null ? null : (int) $snapshotValue;
    }

    private static function persistSnapshot(Model $parent, array &$snapshot, ApprovalWorkflowStep $step, ?User $user): void
    {
        if (! $user) {
            return;
        }
        $existing = (array) ($snapshot['approval_snapshot'][$step->step_code] ?? []);
        $selected = [
            'user_id' => $user->id, 'employee_id' => $user->employee?->id,
            'nik' => $user->employee?->employee_number, 'name' => $user->name,
            'role' => $step->approver_role, 'step' => $step->step_code,
        ];
        // Relation-based approvers are immutable transaction snapshots. A role_users
        // resolver (notably PM) is newly selected by this chain and replaces its old snapshot.
        $snapshot['approval_snapshot'][$step->step_code] = $step->approver_resolver === 'role_users'
            ? $selected
            : array_merge($selected, $existing);
        $parent->forceFill(['employee_snapshot_json' => $snapshot])->save();
    }

    private static function audit(Model $parent, string $event, array $properties, ?User $actor = null): void
    {
        $activity = activity()->performedOn($parent)->withProperties($properties);
        if ($actor) {
            $activity->causedBy($actor);
        }
        $activity->log($event);
    }
}
