<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ItRequest extends Model
{
    protected $fillable = [
        'request_number',
        'employee_id',
        'recipient_name',
        'recipient_id_number',
        'recipient_site',
        'recipient_cost_code',
        'recipient_position',
        'recipient_effective_date',
        'is_new_employee',
        'request_type',
        'replacement_reason',
        'replacement_note',
        'device_type',
        'special_specification',
        'description',
        'purpose',
        'software_standard_json',
        'software_optional_json',
        'accessories_json',
        'accessory_other_note',
        'needed_date',
        'priority',
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
            'is_new_employee' => 'boolean',
            'special_specification' => 'boolean',
            'software_standard_json' => 'array',
            'software_optional_json' => 'array',
            'accessories_json' => 'array',
            'recipient_effective_date' => 'date',
            'needed_date' => 'date',
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
