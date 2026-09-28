<?php

namespace App\Http\Controllers;

use App\Data\Approval\ApprovalViewData;
use App\Enums\ApprovalStepStatus;
use App\Http\Requests\ApprovalActionRequest;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\Approval\ApprovalActorConflicts;
use App\Services\Approval\ApproveApprovalRequest;
use App\Services\Approval\DelegateApprovalRequest;
use App\Services\Approval\RejectApprovalRequest;
use App\Services\Approval\ReturnApprovalRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ApprovalRequest::class);
        $approvals = ApprovalRequest::query()->with(['approvable', 'approver', 'actions.actor'])->currentChain()->pending()->actionable()->where('approver_user_id', $request->user()->getKey())->orderBy('step_order')->paginate(20);
        $approvals->setCollection($approvals->getCollection()->map(fn (ApprovalRequest $approval) => ApprovalViewData::make($approval, $request->user())));

        return Inertia::render('Approvals/Index', ['approvals' => $approvals]);
    }

    public function show(Request $request, ApprovalRequest $approval): Response
    {
        $this->authorize('view', $approval);
        $approval->load(['approvable', 'workflow.steps', 'actions.actor']);

        $canAct = $request->user()->can('act', $approval);
        $conflict = $approval->approvable !== null && ApprovalActorConflicts::conflicts($approval->approvable, $request->user(), $approval);
        $canAct = $canAct && ! $conflict;
        $delegates = $canAct ? User::query()->where('active', true)->role($approval->approver_role)->permission('approval.act')
            ->with('employee:id,user_id,name')->orderBy('name')->orderBy('id')->get()
            ->filter(fn (User $user) => (! $user->employee || $user->employee->active)
                && ! in_array($user->id, array_merge(ApprovalActorConflicts::ids($approval->approvable, $approval), [(int) $request->user()->id], $approval->actions->pluck('actor_id')->map(fn ($id) => (int) $id)->all()), true))
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'employee_name' => $user->employee?->name])->values()->all() : [];
        $availableActions = [
            'approve' => $canAct, 'return' => $canAct, 'reject' => $canAct,
            'delegate' => $canAct && $delegates !== [], 'eligibleDelegates' => $delegates,
        ];

        return Inertia::render('Approvals/Show', ['approval' => ApprovalViewData::make($approval, $request->user()), 'availableActions' => $availableActions]);
    }

    public function action(ApprovalActionRequest $request, ApprovalRequest $approval, string $action): RedirectResponse
    {
        abort_unless(in_array($action, ['approve', 'return', 'reject', 'delegate'], true), 404);

        // A stale approval page can remain open after another action or a
        // previous click has completed this step. Keep authorization strict,
        // but return a useful message instead of a generic 403 to its assignee.
        if ((int) $approval->approver_user_id === (int) $request->user()->getKey()
            && $approval->status !== ApprovalStepStatus::Pending
            && $request->user()->can('approval.act')) {
            return back()->withErrors([
                'approval' => 'Tahap approval ini sudah diproses dan tidak dapat diulang. Buka inbox approval untuk melihat tahap berikutnya.',
            ]);
        }

        $this->authorize('act', $approval);
        $data = $request->validated();
        match ($action) {
            'approve' => ApproveApprovalRequest::run($approval, $request->user()),
            'return' => ReturnApprovalRequest::run($approval, $request->user(), $data['comments']),
            'reject' => RejectApprovalRequest::run($approval, $request->user(), $data['comments']),
            'delegate' => DelegateApprovalRequest::run($approval, $request->user(), User::findOrFail($data['delegate_user_id']), $data['comments'] ?? null),
        };

        return back()->with('success', 'Tindakan approval berhasil diproses.');
    }
}
