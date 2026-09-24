<?php

namespace App\Services\Travel;

/**
 * Kalkulasi advance perjalanan dinas dari item biaya (PRD §2 modul B, §6.4).
 *
 * - amount per item = quantity * unit_price (dibulatkan 2 desimal).
 * - Semua kategori CostCategory eligible termasuk flight.
 * - Total advance = sum amount, selalu traceable dari item biaya.
 */
class CalculateTravelAdvance
{
    /**
     * Hitung satu item.
     *
     * String math (bcmul, presisi 2 desimal) bukan float mentah agar
     * tidak ada galat floating-point / overflow.
     */
    public static function amountForItem(float|int|string $quantity, float|int|string $unitPrice): string
    {
        $qtyStr = is_numeric($quantity) ? (string) $quantity : '0';
        $priceStr = is_numeric($unitPrice) ? (string) $unitPrice : '0';

        return self::multiplyRounded2($qtyStr, $priceStr);
    }

    /**
     * Total advance dari kumpulan item (array/collection dengan
     * quantity + unit_price).
     *
     * @param  iterable<int, array{quantity?: mixed, unit_price?: mixed}>  $items
     */
    public static function totalForItems(iterable $items): string
    {
        // Akumulasi string (bcadd) agar presisi, bukan float mentah.
        $totalStr = '0';

        foreach ($items as $item) {
            $amount = self::amountForItem(
                $item['quantity'] ?? 0,
                $item['unit_price'] ?? 0,
            );

            $totalStr = bcadd($totalStr, $amount, 2);
        }

        return $totalStr;
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

            // BCMath truncates at the requested scale, so add the half-cent
            // at scale 3 and truncate only after the carry has been applied.
            return bcdiv(bcadd($raw, '0.005', 3), '1', 2);
        }

        throw new \RuntimeException('BCMath extension is required for financial calculations.');
    }
}
