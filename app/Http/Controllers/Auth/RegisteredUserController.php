<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class, 'ends_with:@borneoprima.com'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.ends_with' => 'Pendaftaran hanya dibuka untuk email korporat @borneoprima.com.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->assignRole('employee');

        event(new Registered($user));

        // Minta admin/HRGA menautkan akun baru ke NIK karyawan.
        $notifier = app(\App\Services\InAppNotifier::class);
        User::query()->where('active', true)->role(['admin', 'hrga', 'hrga_manager'])
            ->whereKeyNot($user->getKey())->orderBy('id')->cursor()
            ->each(fn (User $recipient) => $notifier->notifyUser(
                $recipient->getKey(),
                'user.registered',
                'Akun baru perlu ditautkan ke NIK',
                "Akun {$user->name} ({$user->email}) baru saja mendaftar. Tautkan ke NIK karyawan melalui Pengaturan > Pengguna.",
                route('settings.users.index'),
            ));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
