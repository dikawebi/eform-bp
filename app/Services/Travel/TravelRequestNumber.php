<?php

namespace App\Services\Travel;

use App\Models\TravelRequest;

/**
 * Nomor dokumen DINAS-YYYYMM-0001 atomik.
 *
 * Dipanggil di dalam DB transaction store dengan lockForUpdate sehingga
 * dua request bersamaan tidak mendapat sequence sama; unique constraint
 * request_number menjadi pengaman terakhir (controller retry saat
 * duplikat terdeteksi).
 */
class TravelRequestNumber
{
    public static function generate(): string
    {
        $prefix = 'DINAS-'.now()->format('Ym').'-';

        $last = TravelRequest::query()
            ->where('request_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderBy('request_number', 'desc')
            ->first();

        $sequence = 1;

        if ($last !== null) {
            $sequence = ((int) substr((string) $last->request_number, -4)) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
