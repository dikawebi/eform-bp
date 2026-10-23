<?php

namespace App\Services\It;

use App\Services\DocumentNumberSequence;

final class ErpRequestNumber
{
    public static function generate(): string
    {
        return DocumentNumberSequence::generate('erp_request');
    }
}
