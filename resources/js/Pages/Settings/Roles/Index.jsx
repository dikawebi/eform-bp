import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const GROUP_LABELS = {
    dashboard: 'Dashboard',
    employee: 'Karyawan',
    leave: 'Cuti / Izin',
    travel: 'Perjalanan Dinas',
    settlement: 'Settlement',
    medical: 'Medical Claim',
    approval: 'Approval',
    workflow: 'Workflow',
    report: 'Laporan',
    role: 'Role dan Permission',
    attachment: 'Lampiran',
};

const titleCase = (value) => String(value || '')
    .replace(/[._-]+/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

const permissionGroups = (permissions) => permissions.reduce((groups, permission) => {
    const key = String(permission.name).split('.')[0] || 'lainnya';
    if (!groups[key]) groups[key] = [];
    groups[key].push(permission);
    return groups;
}, {});

function ErrorText({ message }) {
    return message ? <p className="mt-1 text-xs font-medium text-rose-600">{message}</p> : null;
}

function PermissionPicker({ permissions, selected, onChange, errors }) {
    const groups = useMemo(() => permissionGroups(permissions), [permissions]);
    const toggle = (name) => onChange(selected.includes(name)
        ? selected.filter((item) => item !== name)
        : [...selected, name]);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <h3 className="text-sm font-bold text-slate-900 dark:text-white">Permission</h3>
                    <p className="text-xs text-slate-500">Pilih hak akses yang diberikan kepada role ini.</p>
                </div>
                <span className="shrink-0 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                    {selected.length} dipilih
                </span>
            </div>
            <ErrorText message={errors.permissions || errors['permissions.0']} />
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {Object.entries(groups).map(([group, items]) => (
                    <fieldset key={group} className="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <legend className="px-1 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {GROUP_LABELS[group] || titleCase(group)}
                        </legend>
                        <div className="mt-2 space-y-2">
                            {items.map((permission) => (
                                <label key={permission.id ?? permission.name} className="flex cursor-pointer items-start gap-2 text-sm text-slate-700 dark:text-slate-200">
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(permission.name)}
                                        onChange={() => toggle(permission.name)}
                                        className="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span className="break-all">{permission.label || titleCase(permission.name)}</span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                ))}
            </div>
        </div>
    );
}

function RoleEditor({ role, permissions }) {
    const [open, setOpen] = useState(false);
    const initialPermissions = (role.permissions || []).map((permission) => permission.name || permission);
    const { data, setData, put, processing, errors, isDirty } = useForm({
        name: role.name || '',
        permissions: initialPermissions,
    });

    const save = (event) => {
        event.preventDefault();
        put(route('settings.roles.update', role.id), { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <button type="button" onClick={() => setOpen((value) => !value)} className="flex w-full items-center justify-between gap-4 p-5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/60">
                <span className="min-w-0">
                    <span className="block truncate font-bold text-slate-900 dark:text-white">{role.name}</span>
                    <span className="mt-1 block text-xs text-slate-500">{initialPermissions.length} permission aktif</span>
                </span>
                <span className="shrink-0 text-sm font-semibold text-blue-600">{open ? 'Tutup' : 'Kelola'}</span>
            </button>
            {open && <form onSubmit={save} className="space-y-5 border-t border-slate-100 p-5 dark:border-slate-800">
                <div>
                    <label htmlFor={`role-${role.id}`} className="text-sm font-semibold text-slate-700 dark:text-slate-200">Nama role</label>
                    <input id={`role-${role.id}`} value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950" />
                    <ErrorText message={errors.name} />
                </div>
                <PermissionPicker permissions={permissions} selected={data.permissions} onChange={(value) => setData('permissions', value)} errors={errors} />
                <div className="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end dark:border-slate-800">
                    <button type="button" onClick={() => setOpen(false)} className="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Batal</button>
                    <button type="submit" disabled={processing || !isDirty} className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">{processing ? 'Menyimpan...' : 'Simpan perubahan'}</button>
                </div>
            </form>}
        </section>
    );
}

function CreateRole({ permissions }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', permissions: [] });
    const save = (event) => {
        event.preventDefault();
        post(route('settings.roles.store'), { preserveScroll: true, onSuccess: () => { reset(); setOpen(false); } });
    };

    return (
        <section className="rounded-2xl border border-blue-200 bg-blue-50/70 p-5 dark:border-blue-900 dark:bg-blue-950/20">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 className="font-bold text-slate-900 dark:text-white">Tambah role baru</h2><p className="mt-1 text-sm text-slate-600 dark:text-slate-300">Buat role dan atur permission sesuai kebutuhan organisasi.</p></div>
                <button type="button" onClick={() => setOpen((value) => !value)} className="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{open ? 'Tutup form' : '+ Tambah role'}</button>
            </div>
            {open && <form onSubmit={save} className="mt-5 space-y-5 border-t border-blue-200 pt-5 dark:border-blue-900">
                <div><label htmlFor="new-role-name" className="text-sm font-semibold text-slate-700 dark:text-slate-200">Nama role</label><input id="new-role-name" autoFocus required value={data.name} onChange={(event) => setData('name', event.target.value)} placeholder="Contoh: HRGA Reviewer" className="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950" /><ErrorText message={errors.name} /></div>
                <PermissionPicker permissions={permissions} selected={data.permissions} onChange={(value) => setData('permissions', value)} errors={errors} />
                <div className="flex justify-end"><button type="submit" disabled={processing} className="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">{processing ? 'Membuat...' : 'Buat role'}</button></div>
            </form>}
        </section>
    );
}

export default function Index({ roles = [], permissions = [] }) {
    const { props } = usePage();
    const flash = props.flash || {};
    return (
        <AuthenticatedLayout title="Role dan Permission" breadcrumbs={[{ label: 'Administrasi' }, { label: 'Pengaturan' }, { label: 'Role dan Permission' }]}>
            <Head title="Role dan Permission" />
            <div className="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
                {flash.success && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">{flash.success}</div>}
                {flash.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200">{flash.error}</div>}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900"><h1 className="text-lg font-bold text-slate-900 dark:text-white">Manajemen akses</h1><p className="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Kelola role dan permission pengguna. Perubahan akses tetap divalidasi dan diotorisasi oleh server.</p></div>
                <CreateRole permissions={permissions} />
                <div className="space-y-3"><div className="flex items-end justify-between"><div><h2 className="text-lg font-bold text-slate-900 dark:text-white">Daftar role</h2><p className="text-sm text-slate-500">{roles.length} role terdaftar</p></div></div>{roles.length ? roles.map((role) => <RoleEditor key={role.id} role={role} permissions={permissions} />) : <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900">Belum ada role yang tersedia.</div>}</div>
            </div>
        </AuthenticatedLayout>
    );
}
