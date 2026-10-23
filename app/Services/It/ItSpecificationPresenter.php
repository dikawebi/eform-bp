<?php

namespace App\Services\It;

use App\Models\ItRequestSpecification;

final class ItSpecificationPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function make(?ItRequestSpecification $specification): ?array
    {
        if ($specification === null) {
            return null;
        }

        $specification->loadMissing(['template:id,name', 'specifiedBy:id,name', 'lockedBy:id,name']);

        return [
            'id' => $specification->id,
            'template_id' => $specification->template_id,
            'template_name' => $specification->template?->name,
            'device_type' => $specification->device_type,
            'specs' => array_values((array) $specification->specs_json),
            'notes' => $specification->notes,
            'specified_by' => $specification->specifiedBy?->name,
            'specified_at' => $specification->specified_at?->toISOString(),
            'locked_by' => $specification->lockedBy?->name,
            'locked_at' => $specification->locked_at?->toISOString(),
            'is_locked' => $specification->isLocked(),
        ];
    }
}
