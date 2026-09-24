<?php

namespace App\Enums;

/**
 * Hasil rekonsiliasi settlement: advance vs realisasi.
 */
enum DifferenceType: string
{
    case Overpayment = 'overpayment';
    case Underpayment = 'underpayment';
    case Balanced = 'balanced';

    public function label(): string
    {
        return match ($this) {
            self::Overpayment => 'Overpayment (kelebihan, dikembalikan ke perusahaan)',
            self::Underpayment => 'Underpayment (kekurangan, dibayar ke karyawan)',
            self::Balanced => 'Balanced (pas, tidak ada selisih)',
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
