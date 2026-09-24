<?php

namespace App\Services\Leave;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Kalkulasi hari cuti inklusif per periode (PRD §2 modul A, acceptance:
 * "menghitung jumlah hari setiap periode cuti dan total hari pengajuan").
 *
 * Semua hitungan server-side; frontend hanya menampilkan hasil.
 */
class CalculateLeaveDays
{
    /**
     * Jumlah hari inklusif: end - start + 1. Satu hari jika sama.
     */
    public static function daysForPeriod(string|CarbonInterface $start, string|CarbonInterface $end): int
    {
        $startDate = $start instanceof CarbonInterface ? Carbon::parse($start->toDateString()) : Carbon::parse((string) $start);
        $endDate = $end instanceof CarbonInterface ? Carbon::parse($end->toDateString()) : Carbon::parse((string) $end);

        if ($endDate->lessThan($startDate)) {
            return 0;
        }

        return $startDate->diffInDays($endDate) + 1;
    }

    /**
     * Total hari dari kumpulan periode (array/collection dengan
     * start_date + end_date). Dipakai agar total selalu traceable
     * dari day_count tiap periode.
     *
     * @param  iterable<int, array{start_date?: mixed, end_date?: mixed}>  $periods
     */
    public static function totalForPeriods(iterable $periods): int
    {
        $total = 0;

        foreach ($periods as $period) {
            $start = $period['start_date'] ?? null;
            $end = $period['end_date'] ?? null;

            if ($start === null || $end === null) {
                continue;
            }

            $startString = $start instanceof CarbonInterface ? $start->toDateString() : (string) $start;
            $endString = $end instanceof CarbonInterface ? $end->toDateString() : (string) $end;

            $total += self::daysForPeriod($startString, $endString);
        }

        return $total;
    }
}
