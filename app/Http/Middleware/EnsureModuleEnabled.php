<?php

namespace App\Http\Middleware;

use App\Models\ModuleAvailability;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    /**
     * Blokir modul yang dinonaktifkan admin (maintenance mode).
     * Pemegang `module.manage` tetap dilewatkan agar bisa memverifikasi.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (! ModuleAvailability::isEnabled($module) && ! ($user && $user->can('module.manage'))) {
            $message = ModuleAvailability::message($module) ?? 'Modul '.(ModuleAvailability::MODULES[$module] ?? $module).' sedang dalam maintenance. Silakan coba lagi nanti.';

            if (! $request->isMethod('get') || $request->expectsJson()) {
                abort(503, $message);
            }

            return Inertia::render('ModuleMaintenance', [
                'module' => $module,
                'label' => ModuleAvailability::MODULES[$module] ?? $module,
                'message' => $message,
            ])->toResponse($request)->setStatusCode(503);
        }

        return $next($request);
    }
}
