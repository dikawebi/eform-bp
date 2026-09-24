<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateApprovalWorkflowRequest;
use App\Models\ApprovalWorkflow;
use App\Services\Approval\UpdateApprovalWorkflow;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalWorkflowController extends Controller
{
    use AuthorizesRequests;

    public function index(?string $scope = null): Response
    {
        $this->authorize('viewAny', ApprovalWorkflow::class);
        $entityTypes = [
            'leave' => 'leave_request',
            'travel' => 'travel_request',
            'settlement' => 'settlement',
            'medical-claim' => 'medical_claim',
        ];
        abort_if($scope !== null && ! array_key_exists($scope, $entityTypes), 404);

        return Inertia::render('Settings/Workflows/Index', [
            'workflows' => ApprovalWorkflow::query()->with('steps')->when($scope, fn ($query) => $query->where('entity_type', $entityTypes[$scope]))->orderBy('entity_type')->orderBy('code')->get(),
            'scope' => $scope,
            'stepCodes' => [
                ['value' => 'supervisor', 'label' => 'Supervisor / SPT'],
                ['value' => 'hod', 'label' => 'HOD'],
                ['value' => 'pm', 'label' => 'Project Manager'],
                ['value' => 'hrga', 'label' => 'HRGA'],
                ['value' => 'document_validation', 'label' => 'Validasi Dokumen'],
                ['value' => 'finance', 'label' => 'Finance'],
            ],
            'approverRoles' => [
                ['value' => 'supervisor', 'label' => 'Supervisor / SPT'],
                ['value' => 'hod', 'label' => 'HOD'],
                ['value' => 'project_manager', 'label' => 'Project Manager'],
                ['value' => 'hrga', 'label' => 'HRGA'],
                ['value' => 'hrga_manager', 'label' => 'HRGA Manager'],
                ['value' => 'finance', 'label' => 'Finance'],
            ],
        ]);
    }

    public function update(UpdateApprovalWorkflowRequest $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $this->authorize('update', $workflow);
        UpdateApprovalWorkflow::run($workflow, $request->validated(), $request->user());

        return redirect()->route('settings.workflows.index')
            ->with('success', 'Workflow approval berhasil diperbarui.');
    }
}
