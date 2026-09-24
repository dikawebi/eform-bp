<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;

/**
 * Validasi update cuti/izin (hanya draft/returned, dicek Policy).
 */
class UpdateLeaveRequestRequest extends BaseLeaveRequestRequest
{
    public function authorize(): bool
    {
        $leave = $this->route('leave');

        if (! $leave instanceof LeaveRequest) {
            return false;
        }

        return $this->user()?->can('update', $leave) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::sharedRules();
    }
}
