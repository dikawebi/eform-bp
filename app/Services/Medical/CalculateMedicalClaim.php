<?php

namespace App\Services\Medical;

use App\Models\MedicalClaim;
use Illuminate\Validation\ValidationException;

final class CalculateMedicalClaim
{
    public static function run(MedicalClaim $claim): string
    {
        $total = '0';
        foreach ($claim->items as $item) {
            $amount = self::decimal((string) $item->amount);
            if (bccomp($amount, '0', 2) < 0 || bccomp($amount, '999999999999.99', 2) > 0) {
                throw ValidationException::withMessages(['items' => 'Nominal klaim tidak valid.']);
            }
            $total = bcadd($total, $amount, 2);
            if (bccomp($total, '999999999999.99', 2) > 0) {
                throw ValidationException::withMessages(['items' => 'Total klaim melebihi batas maksimum.']);
            }
        }
        $claim->forceFill(['total_amount' => $total])->save();

        return $total;
    }

    private static function decimal(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['items' => 'Nominal klaim harus berupa angka dengan maksimal dua desimal.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ltrim($whole, '0').'.'.str_pad($fraction, 2, '0');
    }
}
