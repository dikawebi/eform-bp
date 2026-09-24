<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;

/**
 * Validasi store (draft baru) cuti/izin.
 */
class StoreLeaveRequestRequest extends BaseLeaveRequestRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LeaveRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::sharedRules();
    }
}
