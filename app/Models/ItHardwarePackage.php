<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItHardwarePackage extends Model
{
    protected $fillable = [
        'code',
        'label',
        'device_type',
        'tier',
        'spec_json',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'spec_json' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
