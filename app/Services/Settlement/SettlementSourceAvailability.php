<?php

namespace App\Services\Settlement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SettlementSourceAvailability
{
    public static function for(Model $source, User $user): bool
    {
        return $user->can('settlement.create.own')
            && in_array($source->status->value, config('eform.settlement.allowed_source_statuses', ['advance_paid', 'settlement_required']), true)
            && (float) $source->total_advance > 0
            && ! $source->settlements()->whereNotNull('source_key')->exists()
            && ((int) $source->created_by === (int) $user->getKey()
                || $source->employee?->user_id === $user->getKey());
    }
}
