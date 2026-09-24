<?php

namespace App\Enums;

/**
 * Status umum request/transaksi eForm BP.
 *
 * Dipakai oleh leave_requests, travel_requests, settlements, medical_claims.
 */
enum RequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Processing = 'processing';
    case AdvancePaid = 'advance_paid';
    case SettlementRequired = 'settlement_required';
    case PaymentProcessing = 'payment_processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::InReview => 'Dalam Review',
            self::Returned => 'Dikembalikan',
            self::Rejected => 'Ditolak',
            self::Approved => 'Disetujui',
            self::Processing => 'Diproses',
            self::AdvancePaid => 'Advance Dibayar',
            self::SettlementRequired => 'Perlu Settlement',
            self::PaymentProcessing => 'Proses Pembayaran',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
