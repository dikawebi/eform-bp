import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const kinds = { device: 'Perangkat utama', accessory: 'Accessories' };

export default function Index({ options = [] }) {
    const create = useForm({ code: '', label: '', kind: 'accessory', requires_note: false, sort_order: 0 });
    const [editing, setEditing] = useState(null);
    const [editLabel, setEditLabel] = useState('');
    const [editOrder, setEditOrder] = useState(0);
    const [editNote, setEditNote] = useState(false);
    const [editActive, setEditActive] = useState(true);

    const submitCreate = (event) => {
        event.preventDefault();
        create.post(route('master.it-items.store'), { preserveScroll: true, onSuccess: () => create.reset() });
    };
    const startEdit = (item) => {
        setEditing(item.id);
        setEditLabel(item.label);
        setEditOrder(item.sort_order ?? 0);
        setEditNote(Boolean(item.requires_note));
        setEditActive(Boolean(item.is_active));
    };
    const submitEdit = (id) => {
        router.put(route('master.it-items.update', id), { label: editLabel, requires_note: editNote, sort_order: Number(editOrder) || 0, is_active: editActive }, { preserveScroll: true, onSuccess: () => setEditing(null) });
    };

    const groups = ['device', 'accessory'];

    return <AuthenticatedLayout title="Perangkat & Accessories IT" breadcrumbs={['Administrasi', 'Master Data', 'Perangkat & Accessories IT']}>
        <Head title="Perangkat & Accessories IT" />
        <div className="mx-auto max-w-6xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
            <header><p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Administrasi / Master Data</p><h1 className="mt-1 text-2xl font-extrabold tracking-tight">Perangkat &amp; Accessories IT</h1><p className="mt-1 text-sm text-slate-500">Opsi yang tampil pada form Hardware &amp; Software (Non-ERP) Request. Penonaktifan tidak menghapus riwayat transaksi.</p></header>

            <section className="ui-card p-5">
                <h2 className="text-base font-bold">Tambah opsi baru</h2>
                <form onSubmit={submitCreate} className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div><label className="block text-xs font-semibold text-slate-600">Kode <b className="text-rose-600">*</b></label><input value={create.data.code} onChange={(e) => create.setData('code', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))} className="mt-1 block w-full rounded-md border-slate-300 text-sm" placeholder="cth. monitor_24" maxLength="50" /><InputError message={create.errors.code} /></div>
                    <div><label className="block text-xs font-semibold text-slate-600">Label <b className="text-rose-600">*</b></label><input value={create.data.label} onChange={(e) => create.setData('label', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" placeholder="cth. Monitor 24 inci" maxLength="100" /><InputError message={create.errors.label} /></div>
                    <div><label className="block text-xs font-semibold text-slate-600">Jenis <b className="text-rose-600">*</b></label><select value={create.data.kind} onChange={(e) => create.setData('kind', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm"><option value="device">Perangkat utama</option><option value="accessory">Accessories</option></select><InputError message={create.errors.kind} /></div>
                    <div><label className="block text-xs font-semibold text-slate-600">Urutan</label><input type="number" min="0" max="9999" value={create.data.sort_order} onChange={(e) => create.setData('sort_order', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" /></div>
                    <div className="flex items-end gap-3"><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={create.data.requires_note} onChange={(e) => create.setData('requires_note', e.target.checked)} /> Wajib keterangan</label><button disabled={create.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">+ Tambah</button></div>
                </form>
            </section>

            {groups.map((kind) => <section key={kind} className="ui-card overflow-hidden">
                <header className="border-b border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-700 dark:bg-slate-900"><h2 className="text-sm font-bold">{kind === 'device' ? 'Perangkat utama (pilih satu)' : 'Accessories (multi-select)'}</h2></header>
                <div className="overflow-x-auto"><table className="w-full min-w-[640px] text-left text-sm"><thead className="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-900/70 dark:text-slate-400"><tr><th className="px-4 py-3">Kode</th><th className="px-4 py-3">Label</th><th className="px-4 py-3">Urutan</th><th className="px-4 py-3">Status</th><th className="px-4 py-3" /></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {options.filter((item) => item.kind === kind).map((item) => <tr key={item.id}>
                        <td className="px-4 py-3 font-mono text-xs">{item.code}</td>
                        <td className="px-4 py-3">{editing === item.id ? <input value={editLabel} onChange={(e) => setEditLabel(e.target.value)} className="block w-full rounded-md border-slate-300 text-sm" maxLength="100" /> : <span className="font-semibold">{item.label}</span>}{item.requires_note && <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">Wajib keterangan</span>}</td>
                        <td className="px-4 py-3">{editing === item.id ? <input type="number" min="0" max="9999" value={editOrder} onChange={(e) => setEditOrder(e.target.value)} className="block w-20 rounded-md border-slate-300 text-sm" /> : item.sort_order}</td>
                        <td className="px-4 py-3">{editing === item.id ? <label className="flex items-center gap-2 text-xs"><input type="checkbox" checked={editActive} onChange={(e) => setEditActive(e.target.checked)} /> Aktif</label> : item.is_active ? <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">Aktif</span> : <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-600">Nonaktif</span>}</td>
                        <td className="px-4 py-3 text-right">{editing === item.id ? <div className="flex justify-end gap-2"><button onClick={() => submitEdit(item.id)} className="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-bold text-white">Simpan</button><button onClick={() => setEditing(null)} className="rounded-md border px-3 py-1.5 text-xs">Batal</button></div> : <div className="flex justify-end gap-2"><button onClick={() => startEdit(item)} className="rounded-md px-2 py-1 text-xs font-bold text-blue-700 hover:bg-blue-50">Ubah</button>{item.is_active && <button onClick={() => { if (window.confirm(`Nonaktifkan opsi "${item.label}"?`)) router.delete(route('master.it-items.destroy', item.id), { preserveScroll: true }); }} className="rounded-md px-2 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50">Nonaktifkan</button>}</div>}</td>
                    </tr>)}
                </tbody></table></div>
            </section>)}
        </div>
    </AuthenticatedLayout>;
}
