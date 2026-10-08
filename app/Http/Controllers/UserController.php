<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\InAppNotifier;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->with(['roles:id,name', 'employee:id,user_id,employee_number,name,department'])
            ->orderBy('name')->paginate(20)->withQueryString();

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all(),
            'employees' => Employee::query()->select(['id', 'employee_number', 'name', 'department'])
                ->where('active', true)->orderBy('name')->limit(500)->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $request): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->forceFill(['active' => (bool) $data['active']])->save();
            $user->syncRoles($data['roles'] ?? ['employee']);
            if (! empty($data['employee_id'])) {
                Employee::query()->whereKey($data['employee_id'])->update(['user_id' => $user->id]);
            }
            activity()->causedBy($request->user())->withProperties(['email' => $user->email])->log('user.created');

            return $user;
        });

        return redirect()->route('settings.users.index')->with('success', "Akun {$user->email} berhasil dibuat.");
    }

    /**
     * Akun yang belum tertaut NIK meminta admin/HRGA menautkannya.
     * Dibatasi untuk akun yang benar-benar belum tertaut agar tidak spam.
     */
    public function requestLink(Request $request, InAppNotifier $notifier): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->active, 403);
        $linked = Employee::query()->where('user_id', $user->id)->where('active', true)->exists();
        abort_if($linked, 422, 'Akun Anda sudah terhubung ke NIK karyawan.');

        User::query()->where('active', true)->role(['admin', 'hrga', 'hrga_manager'])
            ->whereKeyNot($user->getKey())->orderBy('id')->cursor()
            ->each(fn (User $recipient) => $notifier->notifyUser(
                $recipient->getKey(),
                'user.link.requested',
                'Permintaan penautan akun ke NIK',
                "Akun {$user->name} ({$user->email}) meminta penautan ke NIK karyawan. Proses melalui Pengaturan > Pengguna.",
                route('settings.users.index'),
            ));

        return back()->with('success', 'Permintaan penautan terkirim ke admin dan HRGA.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $user): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->authorize('update', $locked);
            $locked->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'active' => (bool) $data['active'],
            ]);
            if (! empty($data['password'])) {
                $locked->forceFill(['password' => Hash::make($data['password'])]);
            }
            $locked->save();
            if (array_key_exists('roles', $data)) {
                $locked->syncRoles($data['roles'] ?? []);
            }
            // Pindahkan tautan NIK: lepas dari karyawan lama bila diganti/dikosongkan.
            Employee::query()->where('user_id', $locked->id)
                ->when(filled($data['employee_id'] ?? null), fn ($query) => $query->where('id', '!=', $data['employee_id']))
                ->update(['user_id' => null]);
            if (! empty($data['employee_id'])) {
                Employee::query()->whereKey($data['employee_id'])->update(['user_id' => $locked->id]);
            }
            activity()->causedBy($request->user())->withProperties(['email' => $locked->email])->log('user.updated');
        });

        return redirect()->route('settings.users.index')->with('success', 'Akun berhasil diperbarui.');
    }
}
