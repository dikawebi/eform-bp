<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePmGmAssignment extends Model
{
    protected $fillable = [
        'site',
        'pm_gm_user_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function pmGmUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_gm_user_id');
    }
}
