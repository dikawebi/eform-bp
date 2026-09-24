<?php

namespace App\Enums;

/**
 * Kategori item biaya (travel items, leave cost items, settlement items).
 */
enum CostCategory: string
{
    case LandTransport = 'land_transport';
    case Flight = 'flight';
    case Hotel = 'hotel';
    case Meal = 'meal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LandTransport => 'Transport Darat',
            self::Flight => 'Tiket Pesawat',
            self::Hotel => 'Penginapan/Hotel',
            self::Meal => 'Makan',
            self::Other => 'Biaya Lainnya',
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
