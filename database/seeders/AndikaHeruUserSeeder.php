<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AndikaHeruUserSeeder extends Seeder
{
    private const USERS = [
        'andika' => 'andika.kuswidyarto@demo.eform-bp.invalid',
        'heru' => 'heru.meireza@demo.eform-bp.invalid',
    ];

    public function run(): void
    {
        $this->call(RolesPermissionsSeeder::class);

        DB::transaction(function (): void {
            $andika = $this->employee('ANDIKA KUSWIDYARTO');
            $heru = $this->employee('HERU MEIREZA');
            $andikaUser = $this->user('andika', 'Andika Kuswidyarto');
            $heruUser = $this->user('heru', 'Heru Meireza');

            $this->linkUser($andika, $andikaUser);
            $this->linkUser($heru, $heruUser);

            $andika->forceFill([
                'supervisor_id' => null,
                'hod_id' => $heru->id,
            ])->save();

            $andikaUser->syncRoles(array_values(array_unique(array_merge(
                $andikaUser->getRoleNames()->all(),
                ['employee'],
            ))));
            $heruUser->syncRoles(array_values(array_unique(array_merge(
                $heruUser->getRoleNames()->all(),
                ['employee', 'hod'],
            ))));
        });
    }

    private function employee(string $name): Employee
    {
        return Employee::query()->whereRaw('UPPER(name) = ?', [$name])->firstOrFail();
    }

    private function user(string $key, string $name): User
    {
        $user = User::query()->firstOrCreate([
            'email' => self::USERS[$key],
        ], [
            'name' => $name,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active' => true,
        ]);

        $user->forceFill([
            'name' => $name,
            'active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user->fresh();
    }

    private function linkUser(Employee $employee, User $user): void
    {
        if ($employee->user_id !== null && (int) $employee->user_id !== (int) $user->id) {
            throw new RuntimeException("Employee {$employee->employee_number} sudah terhubung ke akun lain.");
        }

        if ($user->employee()->where('id', '!=', $employee->id)->exists()) {
            throw new RuntimeException("Akun {$user->email} sudah terhubung ke employee lain.");
        }

        $employee->forceFill(['user_id' => $user->id])->save();
    }
}
