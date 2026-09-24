<?php

namespace App\Services\Leave;

/**
 * Kalkulasi advance cuti dari item biaya (PRD §2 modul A, §6.4).
 *
 * - amount per item = quantity * unit_price (dibulatkan 2 desimal).
 * - Karyawan lokal default eligible=false + amount=0 sesuai
 *   config('eform.leave_local_eligible') yang configurable admin.
 * - Karyawan non-lokal: eligible=true.
 *
 * Total advance = sum amount eligible, sehingga selalu traceable
 * dari kategori biaya (acceptance "perhitungan total yang dapat ditelusuri").
 */
class CalculateLeaveAdvance
{
    /**
     * Hitung satu item: [amount, eligible].
     *
     * A3: amount dihitung dengan string math (bcmul, presisi 2 desimal)
     * bukan float mentah agar tidak ada galat floating-point / overflow.
     *
     * @return array{amount: float, eligible: bool}
     */
    public static function amountForItem(bool $isLocal, float|int|string $quantity, float|int|string $unitPrice): array
    {
        $eligible = $isLocal ? (bool) config('eform.leave_local_eligible', false) : true;

        if (! $eligible) {
            return ['amount' => 0.0, 'eligible' => false];
        }

        $qtyStr = is_numeric($quantity) ? (string) $quantity : '0';
        $priceStr = is_numeric($unitPrice) ? (string) $unitPrice : '0';

        $amountStr = self::multiplyRounded2($qtyStr, $priceStr);

        return ['amount' => (float) $amountStr, 'eligible' => true];
    }

    /**
     * Total advance dari kumpulan item (array/collection dengan
     * quantity + unit_price). Untuk lokal dengan policy default,
     * hasilnya 0 tanpa menghapus baris item (tetap terlacak).
     *
     * @param  iterable<int, array{quantity?: mixed, unit_price?: mixed}>  $items
     */
    public static function totalForItems(bool $isLocal, iterable $items): float
    {
        // Akumulasi string (bcadd) agar presisi, bukan float mentah.
        $totalStr = '0';

        foreach ($items as $item) {
            $result = self::amountForItem(
                $isLocal,
                $item['quantity'] ?? 0,
                $item['unit_price'] ?? 0,
            );

            $totalStr = function_exists('bcadd')
                ? bcadd($totalStr, number_format($result['amount'], 2, '.', ''), 2)
                : number_format(((float) $totalStr) + $result['amount'], 2, '.', '');
        }

        return (float) $totalStr;
    }

    /**
     * Kalikan dua desimal string lalu bulatkan half-up ke 2 desimal
     * memakai bc math (tanpa perantara float).
     *
     * @return numeric-string
     */
    public static function multiplyRounded2(string $qty, string $price): string
    {
        if (function_exists('bcmul') && function_exists('bcadd')) {
            // 6 desimal menampung qty(2)+price(2) secara eksak, lalu
            // +0.005 + truncate(2) = round half-up untuk nilai >= 0.
            $raw = bcmul($qty, $price, 6);

            return bcadd($raw, '0.005', 2);
        }

        return number_format(round(((float) $qty) * ((float) $price), 2), 2, '.', '');
    }
}
