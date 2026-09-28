<?php

namespace App\Models;

use App\Enums\ApprovalStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    protected $fillable = ['approvable_type', 'approvable_id', 'chain_generation', 'workflow_id', 'step_order', 'step_code', 'approver_role', 'approver_user_id', 'status', 'due_at', 'acted_at', 'comments'];

    protected function casts(): array
    {
        return ['status' => ApprovalStepStatus::class, 'chain_generation' => 'string', 'due_at' => 'datetime', 'acted_at' => 'datetime'];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function actions()
    {
        return $this->hasMany(ApprovalAction::class);
    }

    public function scopeCurrentChain($query)
    {
        $table = $this->getTable();

        return $query->whereRaw("chain_generation = (select current.chain_generation from {$table} as current where current.approvable_type = {$table}.approvable_type and current.approvable_id = {$table}.approvable_id order by current.id desc limit 1)");
    }

    public function scopePending($query)
    {
        return $query->where('status', ApprovalStepStatus::Pending);
    }

    public function scopeActionable($query)
    {
        $table = $this->getTable();

        return $query->whereNotExists(function ($subquery) use ($table): void {
            $subquery->selectRaw('1')
                ->from("{$table} as previous")
                ->whereColumn('previous.approvable_type', "{$table}.approvable_type")
                ->whereColumn('previous.approvable_id', "{$table}.approvable_id")
                ->whereColumn('previous.chain_generation', "{$table}.chain_generation")
                ->whereColumn('previous.step_order', '<', "{$table}.step_order")
                ->where('previous.status', ApprovalStepStatus::Pending);
        });
    }
}
