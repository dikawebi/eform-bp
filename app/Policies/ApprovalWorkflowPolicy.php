<?php

namespace App\Policies;

use App\Models\ApprovalWorkflow;
use App\Models\User;

class ApprovalWorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('workflow.manage');
    }

    public function view(User $user, ApprovalWorkflow $workflow): bool
    {
        return $user->can('workflow.manage');
    }

    public function update(User $user, ApprovalWorkflow $workflow): bool
    {
        return $user->can('workflow.manage');
    }
}
