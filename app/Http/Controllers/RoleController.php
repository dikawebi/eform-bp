<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('Settings/Roles/Index', [
            'roles' => Role::query()->where('guard_name', 'web')->with('permissions:id,name')->orderBy('name')->get(['id', 'name', 'guard_name']),
            'permissions' => Permission::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);
        $data = $request->validated();

        $role = DB::transaction(function () use ($data, $request): Role {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions'] ?? []);
            activity()->performedOn($role)->causedBy($request->user())->withProperties([
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            ])->log('role.created');

            return $role;
        });

        return redirect()->route('settings.roles.index')->with('success', "Role {$role->name} berhasil dibuat.");
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $role): void {
            $locked = Role::query()->whereKey($role->id)->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            $before = [
                'name' => $locked->name,
                'permissions' => $locked->permissions->pluck('name')->sort()->values()->all(),
            ];
            $permissions = $data['permissions'] ?? [];
            if ($locked->name === 'admin' || $data['name'] === 'admin') {
                $permissions[] = 'role.manage';
                $permissions = array_values(array_unique($permissions));
            }
            $locked->update(['name' => $data['name']]);
            $locked->syncPermissions($permissions);
            activity()->performedOn($locked)->causedBy($request->user())->withProperties([
                'before' => $before,
                'after' => [
                    'name' => $locked->name,
                    'permissions' => $locked->fresh('permissions')->permissions->pluck('name')->sort()->values()->all(),
                ],
            ])->log('role.updated');
        });

        return redirect()->route('settings.roles.index')->with('success', 'Permission role berhasil diperbarui.');
    }
}
