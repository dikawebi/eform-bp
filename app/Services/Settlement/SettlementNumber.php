<?php

namespace App\Services\Settlement;

use App\Services\DocumentNumberSequence;

final class SettlementNumber
{
    public static function generate(): string
    {
        return DocumentNumberSequence::generate('settlement');
    }
}
