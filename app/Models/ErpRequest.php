<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ErpRequest extends Model
{
    protected $fillable = [
        'request_number',
        'employee_id',
        'recipient_name',
        'recipient_site',
        'recipient_cost_code',
        'recipient_position',
        'action_type',
        'existing_erp_username',
        'business_purpose',
        'modules_json',
        'status',
        'employee_snapshot_json',
        'submitted_at',
        'approved_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'modules_json' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'employee_snapshot_json' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [RequestStatus::Draft, RequestStatus::Returned], true);
    }
}
