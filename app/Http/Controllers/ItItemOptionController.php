<?php

namespace App\Http\Controllers;

use App\Models\ItItemOption;
use App\Models\ItRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ItItemOptionController extends Controller
{
    use AuthorizesRequests;

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('it.master.manage'), 403);
    }

    public function index(Request $request): Response
    {
        $this->guard($request);

        return Inertia::render('Master/ItItems/Index', [
            'options' => ItItemOption::query()->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:it_item_options,code'],
            'label' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'string', Rule::in(['device', 'accessory'])],
            'requires_note' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        ItItemOption::create([
            'code' => $data['code'],
            'label' => $data['label'],
            'kind' => $data['kind'],
            'requires_note' => (bool) ($data['requires_note'] ?? false),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => true,
        ]);
        activity()->causedBy($request->user())->withProperties(['code' => $data['code']])->log('it.master.created');

        return back()->with('success', 'Opsi perangkat berhasil ditambahkan.');
    }

    public function update(Request $request, ItItemOption $it_item): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'requires_note' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ]);

        $it_item->forceFill([
            'label' => $data['label'],
            'requires_note' => (bool) ($data['requires_note'] ?? false),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool) $data['is_active'],
        ])->save();
        activity()->causedBy($request->user())->withProperties(['code' => $it_item->code])->log('it.master.updated');

        return back()->with('success', 'Opsi perangkat berhasil diperbarui.');
    }

    public function destroy(Request $request, ItItemOption $it_item): RedirectResponse
    {
        $this->guard($request);

        if ($this->isUsed($it_item)) {
            throw ValidationException::withMessages([
                'code' => "Opsi \"{$it_item->label}\" sudah dipakai pada transaksi dan tidak dapat dihapus. Nonaktifkan saja agar tidak muncul di form baru.",
            ]);
        }

        $code = $it_item->code;
        $it_item->delete();
        activity()->causedBy($request->user())->withProperties(['code' => $code])->log('it.master.deleted');

        return back()->with('success', 'Opsi perangkat dihapus permanen.');
    }

    /**
     * Kode tersimpan sebagai snapshot pada transaksi (device_type / accessories_json).
     */
    protected function isUsed(ItItemOption $option): bool
    {
        return ItRequest::query()
            ->where('device_type', $option->code)
            ->orWhereJsonContains('accessories_json', $option->code)
            ->exists();
    }
}
