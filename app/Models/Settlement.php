<?php

namespace App\Models;

use App\Enums\DifferenceType;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Employee;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = ['source_type', 'source_id'];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'difference_type' => DifferenceType::class,
            'advance_amount' => 'decimal:2', 'actual_amount' => 'decimal:2', 'difference_amount' => 'decimal:2',
            'allow_partial' => 'boolean', 'is_final' => 'boolean',
            'employee_snapshot_json' => 'array', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function source(): LeaveRequest|TravelRequest|Employee|null
    {
        return match ($this->source_type) {
            'leave_request' => $this->leaveRequest,
            'travel_request' => $this->travelRequest,
            'new_join', 'other' => Employee::query()->find($this->source_id),
            default => null,
        };
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [RequestStatus::Draft, RequestStatus::Returned], true);
    }
}
