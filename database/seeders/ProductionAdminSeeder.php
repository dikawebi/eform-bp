<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class ProductionAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! Role::query()->where('name', 'admin')->where('guard_name', 'web')->exists()) {
            $this->call(RolesPermissionsSeeder::class);
        }

        $name = trim((string) config('eform.admin.name', 'Administrator'));
        $email = Str::lower(trim((string) config('eform.admin.email', '')));
        $password = (string) config('eform.admin.initial_password', '');

        if ($name === '') {
            throw new RuntimeException('ADMIN_NAME tidak boleh kosong.');
        }

        if ($email === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('ADMIN_EMAIL wajib diisi pada production.');
            }

            $email = 'admin.it@borneoprima.com';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException("ADMIN_EMAIL tidak valid: {$email}.");
        }

        if ($password !== '' && mb_strlen($password) < 12) {
            throw new RuntimeException('ADMIN_INITIAL_PASSWORD minimal 12 karakter.');
        }

        $existing = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existing !== null) {
            if (! $existing->hasRole('admin')) {
                $existing->assignRole('admin');
            }

            $this->command?->warn("Akun {$email} sudah ada. Password dan profil tidak diubah; role admin dipastikan aktif.");

            return;
        }

        $generatedPassword = false;

        if ($password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('ADMIN_INITIAL_PASSWORD wajib diisi saat membuat akun admin production.');
            }

            $password = Str::password(20);
            $generatedPassword = true;
        }

        DB::transaction(function () use ($name, $email, $password): void {
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'active' => true,
                'email_notifications' => true,
            ])->save();

            $user->assignRole('admin');
        });

        $this->command?->info("Administrator production dibuat: {$email}");

        if ($generatedPassword) {
            $this->command?->warn("Password awal: {$password}");
            $this->command?->warn('Ganti password setelah login pertama.');
        }
    }
}
