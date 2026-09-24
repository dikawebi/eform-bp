<?php

namespace App\Enums;

enum ApprovalStepStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Delegated = 'delegated';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}
