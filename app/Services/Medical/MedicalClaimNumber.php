<?php

namespace App\Services\Medical;

use App\Services\DocumentNumberSequence;

final class MedicalClaimNumber
{
    public static function generate(): string
    {
        return DocumentNumberSequence::generate('medical_claim');
    }
}
