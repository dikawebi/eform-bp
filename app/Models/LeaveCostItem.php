<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris biaya cuti. amount (= qty*price, 0 jika tidak eligible)
 * dan eligible_by_policy dihitung server via CalculateLeaveAdvance.
 */
class LeaveCostItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category',
        'description',
        'quantity',
        'unit_price',
        'origin',
        'destination',
        'flight_destination',
        'service_date',
        'check_in_date',
        'check_out_date',
        'departure_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'eligible_by_policy' => 'boolean',
            'service_date' => 'date',
            'check_in_date' => 'date',
            'check_out_date' => 'date',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
