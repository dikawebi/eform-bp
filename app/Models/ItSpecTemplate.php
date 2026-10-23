<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ItSpecTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'device_type',
        'specs_json',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'specs_json' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Template aktif yang dapat dipakai untuk jenis perangkat tertentu.
     *
     * @return Collection<int, self>
     */
    public static function availableFor(?string $deviceType): Collection
    {
        return self::query()
            ->active()
            ->ordered()
            ->when($deviceType, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereNull('device_type')
                ->orWhere('device_type', $deviceType)))
            ->get();
    }
}
