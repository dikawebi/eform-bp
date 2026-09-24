<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowStep extends Model
{
    protected $fillable = ['workflow_id', 'step_order', 'step_code', 'approver_role', 'approver_resolver', 'is_required', 'can_skip_if_no_supervisor'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'can_skip_if_no_supervisor' => 'boolean'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }
}
