<?php

namespace App\Http\Controllers;

use App\Models\ModuleAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ModuleAvailabilityController extends Controller
{
    public function index(): Response
    {
        $rows = ModuleAvailability::query()->get()->keyBy('module');
        $modules = collect(ModuleAvailability::MODULES)->map(fn (string $label, string $key) => [
            'key' => $key,
            'label' => $label,
            'is_enabled' => $rows->has($key) ? (bool) $rows->get($key)->is_enabled : true,
            'message' => $rows->has($key) ? $rows->get($key)->maintenance_message : null,
        ])->values()->all();

        return Inertia::render('Settings/Modules/Index', ['modules' => $modules]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*.is_enabled' => ['nullable', 'boolean'],
            'modules.*.message' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $before = [];
            $after = [];
            foreach (ModuleAvailability::MODULES as $key => $label) {
                $row = ModuleAvailability::query()->where('module', $key)->lockForUpdate()->first();
                $before[$key] = $row !== null
                    ? ['is_enabled' => (bool) $row->is_enabled, 'message' => $row->maintenance_message]
                    : ['is_enabled' => true, 'message' => null];

                // Checkbox yang tidak dicentang tidak terkirim → anggap nonaktif.
                $enabled = (bool) ($data['modules'][$key]['is_enabled'] ?? false);
                $rawMessage = trim((string) ($data['modules'][$key]['message'] ?? ''));
                $message = $rawMessage !== '' ? mb_substr($rawMessage, 0, 500) : null;

                ModuleAvailability::updateOrCreate(['module' => $key], ['is_enabled' => $enabled, 'maintenance_message' => $message]);
                $after[$key] = ['is_enabled' => $enabled, 'message' => $message];
            }

            activity()->causedBy($request->user())->withProperties(['before' => $before, 'after' => $after])->log('module.updated');
        });

        return back()->with('success', 'Ketersediaan modul berhasil diperbarui.');
    }
}
