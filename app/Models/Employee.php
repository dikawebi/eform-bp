<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Employee extends Model
{
    use HasFactory;

    /**
     * Fillable sempit Phase 1: created_by/updated_by diisi server-side,
     * bukan dari input user (PRD §11).
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'employee_number',
        'name',
        'department',
        'level',
        'job_title',
        'roster',
        'employment_status',
        'poh_status',
        'poh_city',
        'poh_province',
        'supervisor_id',
        'hod_id',
        'is_project_based',
        'active',
        'joined_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_project_based' => 'boolean',
            'poh_status' => 'string',
            'joined_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        $guardSelfReference = function (Employee $employee): void {
            if (
                $employee->supervisor_id !== null
                && $employee->getKey() !== null
                && (int) $employee->supervisor_id === (int) $employee->getKey()
            ) {
                throw ValidationException::withMessages([
                    'supervisor_id' => 'Supervisor tidak boleh diri sendiri.',
                ]);
            }

            if (
                $employee->hod_id !== null
                && $employee->getKey() !== null
                && (int) $employee->hod_id === (int) $employee->getKey()
            ) {
                throw ValidationException::withMessages([
                    'hod_id' => 'HOD tidak boleh diri sendiri.',
                ]);
            }
        };

        static::creating($guardSelfReference);
        static::updating($guardSelfReference);
    }

    /**
     * Cek apakah proposed parent akan membentuk cycle ke employee.
     * Walk ancestor (supervisor_id + hod_id) via BFS max depth 10 (B3).
     */
    public static function wouldCreateCycle(int $employeeId, ?int $proposedParentId, int $maxDepth = 10): bool
    {
        if ($proposedParentId === null) {
            return false;
        }

        if ((int) $proposedParentId === (int) $employeeId) {
            return true;
        }

        $visited = [];
        $queue = [(int) $proposedParentId];

        for ($depth = 0; $depth < $maxDepth && $queue !== []; $depth++) {
            $next = [];

            foreach ($queue as $currentId) {
                if ((int) $currentId === (int) $employeeId) {
                    return true;
                }

                if (in_array((int) $currentId, $visited, true)) {
                    continue;
                }

                $visited[] = (int) $currentId;

                $parent = static::query()
                    ->select(['id', 'supervisor_id', 'hod_id'])
                    ->find($currentId);

                if ($parent === null) {
                    continue;
                }

                if ($parent->supervisor_id !== null) {
                    $next[] = (int) $parent->supervisor_id;
                }

                if ($parent->hod_id !== null) {
                    $next[] = (int) $parent->hod_id;
                }
            }

            $queue = $next;
        }

        // Jika queue masih tersisa setelah max depth, anggap dalam sebagai
        // proteksi: hanya cycle jika employee ditemukan di atas (sudah dicek).
        return false;
    }

    /**
     * Leader harus karyawan aktif (B3).
     * null = tidak ada leader = lolos.
     */
    public static function isActiveLeader(?int $id): bool
    {
        if ($id === null) {
            return true;
        }

        return static::query()->whereKey($id)->where('active', true)->exists();
    }

    public static function isActiveLeaderInDepartment(?int $id, ?string $department): bool
    {
        if ($id === null || trim((string) $department) === '') {
            return true;
        }

        $leader = static::query()->select(['id', 'department', 'active'])->find($id);

        return $leader !== null
            && $leader->active
            && mb_strtolower(trim((string) $leader->department)) === mb_strtolower(trim($department));
    }

    /**
     * Akun login yang terhubung (satu NIK = satu user).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medicalDependents(): HasMany
    {
        return $this->hasMany(MedicalDependent::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function hod(): BelongsTo
    {
        return $this->belongsTo(self::class, 'hod_id');
    }

    /**
     * Bawahan langsung via supervisor_id.
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Nonaktifkan tanpa hapus fisik (PRD §6: larangan hapus transaksi;
     * master mengikuti pola yang sama).
     */
    public function deactivate(): bool
    {
        return $this->update(['active' => false]);
    }
}
