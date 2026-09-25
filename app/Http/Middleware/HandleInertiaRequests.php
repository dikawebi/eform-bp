<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                ] : null,
            ],
            'menus' => $this->menus($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'import_result' => fn () => $request->session()->get('import_result'),
            'unreadNotifications' => fn () => $user?->unreadNotifications()->count() ?? 0,
            'notifications' => fn () => $user?->notifications()->latest()->limit(8)->get()->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => (string) data_get($notification->data, 'title', 'Notifikasi eForm BP'),
                'message' => (string) data_get($notification->data, 'message', ''),
                'url' => str_starts_with((string) data_get($notification->data, 'url', ''), '/') ? data_get($notification->data, 'url') : route('dashboard'),
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at?->toISOString(),
            ])->values()->all() ?? [],
        ];
    }

    /**
     * Menu dasar difilter by permission di server.
     * Frontend hanya me-render apa yang dikirim server (bukan kontrol akses utama).
     *
     * @return list<array{label: string, url: string, permission: string|list<string>|null, group?: string, key?: string}>
     */
    protected function menus(Request $request): array
    {
        $user = $request->user();

        $menus = [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'permission' => 'dashboard.view'],
            ['label' => 'Cuti/Izin', 'url' => '/leaves', 'permission' => 'leave.view.own'],
            ['label' => 'Perjalanan Dinas', 'url' => '/travels', 'permission' => 'travel.view.own'],
            ['label' => 'Settlement', 'url' => '/settlements', 'permission' => 'settlement.create.own'],
            ['label' => 'Medical Claim', 'url' => '/medical-claims', 'permission' => 'medical.view.own'],
            ['label' => 'Approval', 'url' => '/approvals', 'permission' => 'approval.inbox.view'],
            ['label' => 'Laporan', 'url' => '/reports', 'permission' => 'report.view'],
            ['key' => 'master', 'label' => 'Karyawan', 'url' => '/master/employees', 'group' => 'Administrasi', 'permission' => ['employee.view.any', 'employee.manage']],
            ['key' => 'settings-leave', 'label' => 'Workflow Cuti/Izin', 'url' => '/settings/workflows/leave', 'group' => 'Pengaturan', 'permission' => 'workflow.manage'],
            ['key' => 'settings-travel', 'label' => 'Workflow Perjalanan Dinas', 'url' => '/settings/workflows/travel', 'group' => 'Pengaturan', 'permission' => 'workflow.manage'],
            ['key' => 'settings-settlement', 'label' => 'Workflow Settlement', 'url' => '/settings/workflows/settlement', 'group' => 'Pengaturan', 'permission' => 'workflow.manage'],
            ['key' => 'settings-medical', 'label' => 'Workflow Medical Claim', 'url' => '/settings/workflows/medical-claim', 'group' => 'Pengaturan', 'permission' => 'workflow.manage'],
            ['key' => 'settings-roles', 'label' => 'Role dan Permission', 'url' => '/settings/roles', 'group' => 'Pengaturan', 'permission' => 'role.manage'],
        ];

        if (! $user) {
            return [];
        }

        return array_values(array_filter($menus, function (array $menu) use ($user): bool {
            if ($menu['permission'] === null) {
                return true;
            }

            $permissions = is_array($menu['permission']) ? $menu['permission'] : [$menu['permission']];

            return collect($permissions)->contains(fn (string $permission): bool => $user->can($permission));
        }));
    }
}
