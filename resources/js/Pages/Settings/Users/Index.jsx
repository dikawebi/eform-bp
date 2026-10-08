import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ users, roles = [], employees = [] }) {
    const rows = users?.data ?? [];
    const create = useForm({ name: '', email: '', password: '', password_confirmation: '', roles: ['employee'], employee_id: '', active: true });
    const [editing, setEditing] = useState(null);
    const edit = useForm({ name: '', email: '', password: '', password_confirmation: '', roles: [], employee_id: '', active: true });

    const toggleRole = (form, role) => {
        const current = form.data.roles ?? [];
        form.setData('roles', current.includes(role) ? current.filter((item) => item !== role) : [...current, role]);
    };

    const submitCreate = (event) => {
        event.preventDefault();
        create.post(route('settings.users.store'), { preserveScroll: true, onSuccess: () => create.reset() });
    };
    const startEdit = (user) => {
        setEditing(user.id);
        edit.setData({
            name: user.name ?? '',
            email: user.email ?? '',
            password: '',
            password_confirmation: '',
            roles: (user.roles ?? []).map((role) => role.name),
            employee_id: user.employee?.id ? String(user.employee.id) : '',
            active: Boolean(user.active),
        });
        edit.clearErrors();
    };
    const submitEdit = (id) => {
        edit.put(route('settings.users.update', id), { preserveScroll: true, onSuccess: () => setEditing(null) });
    };

    return <AuthenticatedLayout title="Pengguna" breadcrumbs={['Administrasi', 'Pengaturan', 'Pengguna']}>
        <Head title="Pengguna" />
        <div className="mx-auto max-w-7xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
            <header><p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Administrasi / Pengaturan</p><h1 className="mt-1 text-2xl font-extrabold tracking-tight">Pengguna</h1><p className="mt-1 text-sm text-slate-500">Buat akun @borneoprima.com, tautkan NIK karyawan, dan atur role. Akun tidak dihapus fisik — nonaktifkan bila sudah tidak dipakai.</p></header>

            <section className="ui-card p-5">
                <h2 className="text-base font-bold">Buat akun baru</h2>
                <form onSubmit={submitCreate} className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div><label className="block text-xs font-semibold text-slate-600">Nama lengkap <b className="text-rose-600">*</b></label><input value={create.data.name} onChange={(e) => create.setData('name', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" maxLength="255" /><InputError message={create.errors.name} /></div>
                    <div><label className="block text-xs font-semibold text-slate-600">Email korporat <b className="text-rose-600">*</b></label><input type="email" value={create.data.email} onChange={(e) => create.setData('email', e.target.value)} placeholder="nama@borneoprima.com" className="mt-1 block w-full rounded-md border-slate-300 text-sm" maxLength="255" /><InputError message={create.errors.email} /></div>
                    <div><label className="block text-xs font-semibold text-slate-600">Tautkan NIK (opsional)</label><select value={create.data.employee_id} onChange={(e) => create.setData('employee_id', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm"><option value="">— Tanpa NIK —</option>{employees.map((item) => <option key={item.id} value={item.id}>{item.employee_number} · {item.name}</option>)}</select><InputError message={create.errors.employee_id} /></div>
                    <div><label className="block text-xs font-semibold text-slate-600">Kata sandi <b className="text-rose-600">*</b></label><input type="password" value={create.data.password} onChange={(e) => create.setData('password', e.target.value)} autoComplete="new-password" className="mt-1 block w-full rounded-md border-slate-300 text-sm" /><InputError message={create.errors.password} /></div>
                    <div><label className="block text-xs font-semibold text-slate-600">Konfirmasi kata sandi <b className="text-rose-600">*</b></label><input type="password" value={create.data.password_confirmation} onChange={(e) => create.setData('password_confirmation', e.target.value)} autoComplete="new-password" className="mt-1 block w-full rounded-md border-slate-300 text-sm" /></div>
                    <div className="sm:col-span-2 lg:col-span-3"><p className="block text-xs font-semibold text-slate-600">Role</p><div className="mt-1 flex flex-wrap gap-2">{roles.map((role) => <label key={role} className="flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs"><input type="checkbox" checked={(create.data.roles ?? []).includes(role)} onChange={() => toggleRole(create, role)} /> {role}</label>)}</div><InputError message={create.errors.roles} /></div>
                    <div><button disabled={create.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">+ Buat akun</button></div>
                </form>
            </section>

            <section className="ui-card overflow-hidden">
                <div className="overflow-x-auto"><table className="w-full min-w-[820px] text-left text-sm"><thead className="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-900/70 dark:text-slate-400"><tr><th className="px-4 py-3">Nama / email</th><th className="px-4 py-3">NIK terhubung</th><th className="px-4 py-3">Role</th><th className="px-4 py-3">Status</th><th className="px-4 py-3" /></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {rows.map((user) => <tr key={user.id}>
                        <td className="px-4 py-3"><p className="font-semibold">{editing === user.id ? <input value={edit.data.name} onChange={(e) => edit.setData('name', e.target.value)} className="block w-full rounded-md border-slate-300 text-sm" maxLength="255" /> : user.name}</p><p className="mt-0.5 text-xs text-slate-500">{editing === user.id ? <input type="email" value={edit.data.email} onChange={(e) => edit.setData('email', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" maxLength="255" /> : user.email}</p><InputError message={edit.errors.name} /><InputError message={edit.errors.email} /></td>
                        <td className="px-4 py-3 text-xs">{editing === user.id ? <select value={edit.data.employee_id} onChange={(e) => edit.setData('employee_id', e.target.value)} className="block w-full rounded-md border-slate-300 text-sm"><option value="">— Tanpa NIK —</option>{employees.map((item) => <option key={item.id} value={item.id}>{item.employee_number} · {item.name}</option>)}</select> : (user.employee ? `${user.employee.employee_number} · ${user.employee.name}` : <span className="text-slate-400">Belum terhubung</span>)}<InputError message={edit.errors.employee_id} /></td>
                        <td className="px-4 py-3">{editing === user.id ? <div className="flex max-w-xs flex-wrap gap-1.5">{roles.map((role) => <label key={role} className="flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 text-[11px]"><input type="checkbox" checked={(edit.data.roles ?? []).includes(role)} onChange={() => toggleRole(edit, role)} /> {role}</label>)}</div> : <div className="flex flex-wrap gap-1">{(user.roles ?? []).map((role) => <span key={role.id ?? role.name} className="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700">{role.name}</span>)}</div>}<InputError message={edit.errors.roles} /></td>
                        <td className="px-4 py-3">{editing === user.id ? <label className="flex items-center gap-2 text-xs"><input type="checkbox" checked={edit.data.active} onChange={(e) => edit.setData('active', e.target.checked)} /> Aktif</label> : user.active ? <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">Aktif</span> : <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-600">Nonaktif</span>}<InputError message={edit.errors.active} /><InputError message={edit.errors.roles} /></td>
                        <td className="px-4 py-3 text-right">{editing === user.id ? <div className="flex flex-col justify-end gap-2"><input type="password" value={edit.data.password} onChange={(e) => edit.setData('password', e.target.value)} placeholder="Kata sandi baru (opsional)" autoComplete="new-password" className="block w-full rounded-md border-slate-300 text-sm" /><input type="password" value={edit.data.password_confirmation} onChange={(e) => edit.setData('password_confirmation', e.target.value)} placeholder="Konfirmasi (jika ganti)" autoComplete="new-password" className="block w-full rounded-md border-slate-300 text-sm" /><InputError message={edit.errors.password} /><div className="flex justify-end gap-2"><button onClick={() => submitEdit(user.id)} disabled={edit.processing} className="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-bold text-white disabled:opacity-60">Simpan</button><button onClick={() => setEditing(null)} className="rounded-md border px-3 py-1.5 text-xs">Batal</button></div></div> : <button onClick={() => startEdit(user)} className="rounded-md px-2 py-1 text-xs font-bold text-blue-700 hover:bg-blue-50">Ubah</button>}</td>
                    </tr>)}
                </tbody></table></div>
                {(users?.links?.length ?? 0) > 3 && <nav aria-label="Navigasi pengguna" className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs dark:border-slate-700"><p className="text-slate-500">{users.from ?? 0}–{users.to ?? 0} dari {users.total ?? 0}</p><div className="flex gap-1">{users.links.map((page, index) => <span key={`${page.label}-${index}`} onClick={() => page.url && router.visit(page.url, { preserveScroll: true })} className={`cursor-pointer rounded-md border px-2.5 py-1.5 ${page.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'} ${!page.url ? 'pointer-events-none opacity-40' : ''}`}>{page.label.replace('&laquo;', '‹').replace('&raquo;', '›')}</span>)}</div></nav>}
            </section>
        </div>
    </AuthenticatedLayout>;
}
