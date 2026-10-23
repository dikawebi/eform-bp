<?php

namespace App\Services\Leave;

use App\Services\DocumentNumberSequence;

class LeaveRequestNumber
{
    public static function generate(): string
    {
        return DocumentNumberSequence::generate('leave_request');
    }
}
