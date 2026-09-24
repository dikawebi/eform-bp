<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Pengajuan Cuti/Izin Phase 2 (PRD §2 modul A, §6, §7).
 *
 * Total dan status dihitung/dijalankan server-side dalam service/action
 * di dalam DB transaction; tidak dipercaya dari frontend (PRD §11).
 */
class LeaveRequest extends Model
{
    use HasFactory;

    /**
     * Fillable sempit: hanya input user. Server-side only
     * (request_number, employee_id, total_days, total_advance, is_local,
     * status, timestamp, snapshot, created_by/updated_by) diisi controller/
     * service, bukan mass-assignment dari request.
     *
     * @var list<string>
     */
    protected $fillable = [
        'leave_type',
        'reason',
        'last_working_date',
        'onsite_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'is_local' => 'boolean',
            'total_days' => 'integer',
            'total_advance' => 'decimal:2',
            'last_working_date' => 'date',
            'onsite_date' => 'date',
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

    public function periods(): HasMany
    {
        return $this->hasMany(LeavePeriod::class);
    }

    public function costItems(): HasMany
    {
        return $this->hasMany(LeaveCostItem::class);
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

    /**
     * Flag settlement: advance > 0 berarti butuh settlement setelah
     * proses biaya selesai (PRD §6.14). Dipakai Phase 5 sebagai eligibilitas
     * sumber advance dan diuji di Phase 2.
     */
    public function needsSettlement(): bool
    {
        return (float) $this->total_advance > 0;
    }

    public function isSettlementEligible(): bool
    {
        return in_array($this->status, [RequestStatus::AdvancePaid, RequestStatus::SettlementRequired], true);
    }
}
