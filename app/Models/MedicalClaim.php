<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MedicalClaim extends Model
{
    protected $fillable = ['benefit_type'];

    protected function casts(): array
    {
        return ['benefit_types' => 'array', 'status' => RequestStatus::class, 'total_amount' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'completed_at' => 'datetime', 'payment_processed_at' => 'datetime', 'payment_date' => 'date', 'employee_snapshot_json' => 'array'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MedicalClaimItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
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
