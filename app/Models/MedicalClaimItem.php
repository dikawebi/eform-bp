<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalClaimItem extends Model
{
    protected $fillable = ['patient_name', 'relationship', 'dependent_id', 'treatment_date', 'facility_name', 'diagnosis_code', 'amount'];

    protected function casts(): array
    {
        return ['treatment_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(MedicalClaim::class, 'medical_claim_id');
    }
}
