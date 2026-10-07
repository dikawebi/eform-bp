<?php

namespace App\Services\It;

use App\Models\ErpRequest;

final class ErpRequestNumber
{
    public static function generate(): string
    {
        $prefix = 'ERP-'.now()->format('Ym').'-';
        $last = ErpRequest::query()->where('request_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('request_number')->value('request_number');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
