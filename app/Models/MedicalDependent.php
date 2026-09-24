<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalDependent extends Model
{
    protected $fillable = ['employee_id', 'name', 'relationship', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
