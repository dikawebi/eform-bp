<?php

namespace App\Models;

use App\Enums\LeavePeriodCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris periode tanggal cuti/izin. day_count dihitung server
 * inklusif (end - start + 1) via CalculateLeaveDays.
 */
class LeavePeriod extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category',
        'start_date',
        'end_date',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => LeavePeriodCategory::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'day_count' => 'integer',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
