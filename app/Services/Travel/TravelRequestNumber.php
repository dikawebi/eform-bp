<?php

namespace App\Services\Travel;

use App\Services\DocumentNumberSequence;

class TravelRequestNumber
{
    public static function generate(): string
    {
        return DocumentNumberSequence::generate('travel_request');
    }
}
