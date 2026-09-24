<?php

namespace App\Services\Settlement;

use App\Models\Settlement;

final class SettlementNumber
{
    public static function generate(): string
    {
        $prefix = 'STL-'.now()->format('Ym').'-';
        $last = Settlement::query()->where('settlement_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('settlement_number')->first();
        $next = $last ? ((int) substr($last->settlement_number, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
