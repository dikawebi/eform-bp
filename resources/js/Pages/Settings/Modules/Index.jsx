import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ modules = [] }) {
    const errors = usePage().props.errors ?? {};
    const [rows, setRows] = useState(() => Object.fromEntries((modules ?? []).map((item) => [item.key, { is_enabled: Boolean(item.is_enabled), message: item.message ?? '' }])));
    const [saving, setSaving] = useState(false);

    const setRow = (key, patch) => setRows((prev) => ({ ...prev, [key]: { ...(prev[key] ?? { is_enabled: true, message: '' }), ...patch } }));
    const save = (event) => {
        event.preventDefault();
        setSaving(true);
        router.put(route('settings.modules.update'), { modules: rows }, { preserveScroll: true, onFinish: () => setSaving(false) });
    };

    return <AuthenticatedLayout title="Modul & Maintenance" breadcrumbs={['Pengaturan', 'Modul & Maintenance']}>
        <Head title="Modul & Maintenance" />
        <div className="mx-auto max-w-4xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
            <header><p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Pengaturan / Akses modul</p><h1 className="mt-1 text-2xl font-extrabold tracking-tight">Modul & Maintenance</h1><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Matikan modul untuk member biasa atau tampilkan pesan maintenance. Akun dengan hak kelola modul tetap bisa membuka semuanya untuk verifikasi.</p></header>

            {Object.keys(errors).length > 0 && <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p className="font-semibold">Periksa kembali isian berikut:</p><ul className="mt-2 list-disc space-y-1 pl-5">{Object.entries(errors).map(([key, message]) => <li key={key}>{String(message)}</li>)}</ul></div>}

            <form onSubmit={save} className="ui-card overflow-hidden">
                <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                    {(modules ?? []).map((item) => {
                        const row = rows[item.key] ?? { is_enabled: true, message: '' };
                        return <li key={item.key} className="space-y-3 p-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div><p className="text-sm font-bold">{item.label}</p><p className="mt-0.5 font-mono text-[11px] text-slate-400">{item.key}</p></div>
                                <label className={`flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold ${row.is_enabled ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300' : 'border-slate-300 bg-slate-100 text-slate-500 dark:border-slate-700 dark:bg-slate-800'}`}><input type="checkbox" checked={row.is_enabled} onChange={(event) => setRow(item.key, { is_enabled: event.target.checked })} className="rounded" /> {row.is_enabled ? 'Aktif' : 'Maintenance'}</label>
                            </div>
                            {!row.is_enabled && <div><label className="block text-xs font-semibold text-slate-600 dark:text-slate-300" htmlFor={`msg-${item.key}`}>Pesan maintenance (opsional)</label><input id={`msg-${item.key}`} value={row.message} onChange={(event) => setRow(item.key, { message: event.target.value })} className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Mis. Modul dibuka kembali Senin pukul 08.00." maxLength="500" /></div>}
                        </li>;
                    })}
                </ul>
                <div className="flex justify-end gap-2 border-t border-slate-200 px-4 py-3 dark:border-slate-700"><button type="submit" disabled={saving} className="rounded-lg bg-gray-900 px-5 py-2 text-sm font-bold text-white disabled:opacity-60">{saving ? 'Menyimpan…' : 'Simpan'}</button></div>
            </form>
        </div>
    </AuthenticatedLayout>;
}
