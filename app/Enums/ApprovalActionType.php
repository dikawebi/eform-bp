<?php

namespace App\Enums;

enum ApprovalActionType: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Return = 'return';
    case Delegate = 'delegate';
    case Cancel = 'cancel';
}
