<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItRequestSpecification extends Model
{
    protected $fillable = [
        'it_request_id',
        'template_id',
        'device_type',
        'specs_json',
        'notes',
        'specified_by',
        'specified_at',
        'locked_by',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'specs_json' => 'array',
            'specified_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function itRequest(): BelongsTo
    {
        return $this->belongsTo(ItRequest::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ItSpecTemplate::class, 'template_id');
    }

    public function specifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'specified_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
