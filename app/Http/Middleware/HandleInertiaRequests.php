<?php

namespace App\Http\Middleware;

use App\Models\ApprovalRequest;
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
            'pendingApprovalCount' => fn () => $user?->can('approval.inbox.view')
                ? ApprovalRequest::query()
                    ->currentChain()
                    ->pending()
                    ->actionable()
                    ->where('approver_user_id', $user->getKey())
                    ->count()
                : null,
            'unreadNotifications' => fn () => $user?->unreadNotifications()->count() ?? 0,
            'notifications' => fn () => $user?->notifications()->latest()->limit(8)->get()->map(fn ($notification) => [
                'id' => $notification->id,
                'event' => (string) data_get($notification->data, 'event', ''),
                'title' => (string) data_get($notification->data, 'title', 'Notifikasi eForm BP'),
                'message' => (string) data_get($notification->data, 'message', ''),
                'url' => \App\Services\InAppNotifier::resolveUrl($notification->data),
                'meta' => (array) data_get($notification->data, 'meta', []),
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at?->toISOString(),
            ])->values()->all() ?? [],
        ];
    }

    /**
     * Menu dasar difilter by permission di server.
     * Frontend hanya me-render apa yang dikirim server (bukan kontrol akses utama).
     *
     * @return list<array{label: string, url: string, permission: string|list<string>|null, group?: string, key?: string, module?: string}>
     */
    protected function menus(Request $request): array
    {
        $user = $request->user();

        $menus = [
            ['label' => 'Dashboard', 'url' => '/dashboard', 'permission' => 'dashboard.view'],
            ['label' => 'Cuti/Izin', 'url' => '/leaves', 'permission' => 'leave.view.own', 'module' => 'leave'],
            ['label' => 'Perjalanan Dinas', 'url' => '/travels', 'permission' => 'travel.view.own', 'module' => 'travel'],
            ['label' => 'Settlement', 'url' => '/settlements', 'permission' => 'settlement.create.own', 'module' => 'settlement'],
            ['label' => 'Medical Claim', 'url' => '/medical-claims', 'permission' => 'medical.view.own', 'module' => 'medical_claim'],
            ['label' => 'Hardware & Software (Non-ERP) Request', 'url' => '/it-requests', 'permission' => 'it_request.create.own', 'module' => 'it_request'],
            ['label' => 'ERP Request', 'url' => '/erp-requests', 'permission' => 'erp_request.create.own', 'module' => 'erp_request'],
            ['label' => 'Approval', 'url' => '/approvals', 'permission' => 'approval.inbox.view'],
            ['label' => 'Laporan', 'url' => '/reports', 'permission' => 'report.view', 'module' => 'report'],
            ['key' => 'master', 'label' => 'Karyawan', 'url' => '/master/employees', 'group' => 'Administrasi', 'permission' => ['employee.view.any', 'employee.manage']],
            ['key' => 'master-it-items', 'label' => 'Perangkat & Accessories IT', 'url' => '/master/it-items', 'group' => 'Administrasi', 'permission' => 'it.master.manage'],
            ['key' => 'settings-workflows', 'label' => 'Workflow', 'url' => '/settings/workflows', 'group' => 'Pengaturan', 'permission' => 'workflow.manage'],
            ['key' => 'settings-roles', 'label' => 'Role dan Permission', 'url' => '/settings/roles', 'group' => 'Pengaturan', 'permission' => 'role.manage'],
            ['key' => 'settings-users', 'label' => 'Pengguna', 'url' => '/settings/users', 'group' => 'Pengaturan', 'permission' => 'user.manage'],
            ['key' => 'settings-modules', 'label' => 'Modul & Maintenance', 'url' => '/settings/modules', 'group' => 'Pengaturan', 'permission' => 'module.manage'],
            ['key' => 'settings-mail', 'label' => 'Email & Notifikasi', 'url' => '/settings/mail', 'group' => 'Pengaturan', 'permission' => 'mail.manage'],
        ];

        if (! $user) {
            return [];
        }

        return array_values(array_filter($menus, function (array $menu) use ($user): bool {
            if ($menu['permission'] === null) {
                return true;
            }

            $permissions = is_array($menu['permission']) ? $menu['permission'] : [$menu['permission']];

            if (! collect($permissions)->contains(fn (string $permission): bool => $user->can($permission))) {
                return false;
            }

            // Modul yang dimatikan admin disembunyikan; pemegang
            // module.manage tetap melihatnya untuk verifikasi.
            if (isset($menu['module']) && ! \App\Models\ModuleAvailability::isEnabled($menu['module']) && ! $user->can('module.manage')) {
                return false;
            }

            return true;
        }));
    }
}
