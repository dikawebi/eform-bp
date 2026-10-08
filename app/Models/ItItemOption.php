<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItItemOption extends Model
{
    protected $fillable = [
        'code',
        'label',
        'kind',
        'requires_note',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_note' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }
}
