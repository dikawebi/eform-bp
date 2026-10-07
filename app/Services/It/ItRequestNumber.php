<?php

namespace App\Services\It;

use App\Models\ItRequest;

final class ItRequestNumber
{
    public static function generate(): string
    {
        $prefix = 'ITR-'.now()->format('Ym').'-';
        $last = ItRequest::query()->where('request_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('request_number')->value('request_number');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
