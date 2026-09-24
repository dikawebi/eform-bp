<?php

namespace App\Enums;

/**
 * Kategori periode cuti/izin per baris tanggal (leave_periods.category).
 */
enum LeavePeriodCategory: string
{
    case Onsite = 'onsite';
    case TravelHome = 'travel_home';
    case RosterLeave = 'roster_leave';
    case Coff = 'coff';
    case AnnualLeave = 'annual_leave';
    case Permission = 'permission';
    case TravelToSite = 'travel_to_site';

    public function label(): string
    {
        return match ($this) {
            self::Onsite => 'Onsite',
            self::TravelHome => 'Perjalanan ke Rumah/Lokasi',
            self::RosterLeave => 'Cuti Roster/OS',
            self::Coff => 'C-Off',
            self::AnnualLeave => 'Cuti Tahunan',
            self::Permission => 'Izin/Lainnya',
            self::TravelToSite => 'Perjalanan ke Site',
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
