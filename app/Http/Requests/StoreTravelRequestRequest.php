<?php

namespace App\Http\Requests;

use App\Models\TravelRequest;

/**
 * Validasi store (draft baru) perjalanan dinas.
 */
class StoreTravelRequestRequest extends BaseTravelRequestRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->user()?->can('travel.project.flag')) {
            $this->merge(['is_project_trip' => false]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', TravelRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::sharedRules();
    }
}
