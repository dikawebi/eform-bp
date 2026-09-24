<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Pengajuan Perjalanan Dinas Phase 3 (PRD §2 modul B, §5.2, §6, §7).
 *
 * Total dan status dihitung/dijalankan server-side dalam service/action
 * di dalam DB transaction; tidak dipercaya dari frontend (PRD §11).
 */
class TravelRequest extends Model
{
    use HasFactory;

    /**
     * Fillable sempit: hanya input user. Server-side only
     * (request_number, employee_id, total_advance, status, timestamp,
     * snapshot, created_by/updated_by) diisi controller/service,
     * bukan mass-assignment dari request.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purpose',
        'start_date',
        'end_date',
        'origin',
        'destination',
        'is_project_trip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'is_project_trip' => 'boolean',
            'total_advance' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'advance_paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'employee_snapshot_json' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TravelRequestItem::class);
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

    public function settlements(): MorphMany
    {
        return $this->morphMany(Settlement::class, 'source');
    }

    /**
     * Status yang masih boleh diedit pemilik (draft/returned).
     * Approved dan seterusnya immutable tanpa returned/revision flow (PRD §6.5-6).
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [RequestStatus::Draft, RequestStatus::Returned], true);
    }

    public function hasAdvance(): bool
    {
        return (float) $this->total_advance > 0;
    }

    public function needsSettlement(): bool
    {
        return $this->hasAdvance() && $this->isSettlementEligible();
    }

    public function isSettlementEligible(): bool
    {
        return in_array($this->status, [RequestStatus::AdvancePaid, RequestStatus::SettlementRequired], true);
    }
}
