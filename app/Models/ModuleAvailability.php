<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Saklar ketersediaan modul untuk admin: modul dapat dinonaktifkan
 * (maintenance mode) sehingga member biasa tidak bisa membukanya,
 * sementara pemegang `module.manage` tetap bisa lewat untuk verifikasi.
 *
 * Modul yang belum pernah dikonfigurasi dianggap aktif (default aman
 * untuk instalasi lama dan test tanpa seeder).
 */
class ModuleAvailability extends Model
{
    /** @var array<string, string> key => label Bahasa Indonesia */
    public const MODULES = [
        'leave' => 'Cuti/Izin',
        'travel' => 'Perjalanan Dinas',
        'settlement' => 'Settlement',
        'medical_claim' => 'Medical Claim',
        'it_request' => 'Hardware & Software Request',
        'erp_request' => 'ERP Request',
        'report' => 'Laporan',
    ];

    protected $fillable = ['module', 'is_enabled', 'maintenance_message'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public static function isEnabled(string $module): bool
    {
        return (bool) (static::query()->where('module', $module)->value('is_enabled') ?? true);
    }

    public static function message(string $module): ?string
    {
        $message = static::query()->where('module', $module)->value('maintenance_message');

        return $message !== null && trim((string) $message) !== '' ? (string) $message : null;
    }
}
