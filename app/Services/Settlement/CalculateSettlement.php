<?php

namespace App\Services\Settlement;

use App\Enums\DifferenceType;
use App\Models\Settlement;

final class CalculateSettlement
{
    /** @return array{actual_amount:string,difference_amount:string,difference_type:DifferenceType} */
    public static function run(Settlement $settlement): array
    {
        $actual = '0.00';
        foreach ($settlement->items as $item) {
            $amount = self::money((string) $item->amount);
            $actual = bcadd($actual, $amount, 2);
        }
        $advance = self::money((string) $settlement->advance_amount);
        $comparison = bccomp($advance, $actual, 2);
        $difference = bcsub($advance, $actual, 2);
        $type = $comparison > 0 ? DifferenceType::Overpayment : ($comparison < 0 ? DifferenceType::Underpayment : DifferenceType::Balanced);

        return ['actual_amount' => $actual, 'difference_amount' => $difference, 'difference_type' => $type];
    }

    private static function money(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Nominal settlement tidak valid.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
