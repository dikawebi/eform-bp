<?php

namespace App\Http\Requests;

use App\Models\TravelRequest;

/**
 * Validasi update perjalanan dinas (hanya draft/returned, dicek Policy).
 */
class UpdateTravelRequestRequest extends BaseTravelRequestRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->user()?->can('travel.project.flag')) {
            $this->merge(['is_project_trip' => false]);
        }
    }

    public function authorize(): bool
    {
        $travel = $this->route('travel');

        if (! $travel instanceof TravelRequest) {
            return false;
        }

        return $this->user()?->can('update', $travel) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::sharedRules();
    }
}
