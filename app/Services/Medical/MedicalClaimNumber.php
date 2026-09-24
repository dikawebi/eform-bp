<?php

namespace App\Services\Medical;

use App\Models\MedicalClaim;

final class MedicalClaimNumber
{
    public static function generate(): string
    {
        $prefix = 'MED-'.now()->format('Ym').'-';
        $last = MedicalClaim::query()->where('claim_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('claim_number')->value('claim_number');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
