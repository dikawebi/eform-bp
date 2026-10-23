<?php

namespace App\Services\It;

use App\Services\DocumentNumberSequence;

final class ItRequestNumber
{
    public static function generate(): string
    {
        return DocumentNumberSequence::generate('it_request');
    }
}
