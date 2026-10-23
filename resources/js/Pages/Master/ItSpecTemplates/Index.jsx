import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

const blankRow = () => ({ label: '', value: '' });
const initialData = {
    code: '',
    name: '',
    device_type: '',
    specs: [blankRow()],
    is_active: true,
    sort_order: 0,
};

export default function Index({ templates = [], device_options = [] }) {
    const form = useForm(initialData);
    const [editing, setEditing] = useState(null);

    const submit = (event) => {
        event.preventDefault();
        if (editing) {
            form.put(route('master.it-spec-templates.update', editing), {
                preserveScroll: true,
                onSuccess: () => {
                    setEditing(null);
                    form.reset();
                },
            });

            return;
        }

        form.post(route('master.it-spec-templates.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };
    const startEdit = (template) => {
        setEditing(template.id);
        form.setData({
            code: template.code,
            name: template.name,
            device_type: template.device_type ?? '',
            specs: Array.isArray(template.specs_json) && template.specs_json.length ? template.specs_json.map((row) => ({ label: row.label ?? '', value: row.value ?? '' })) : [blankRow()],
            is_active: Boolean(template.is_active),
            sort_order: template.sort_order ?? 0,
        });
        form.clearErrors();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    const cancelEdit = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };
    const setRow = (index, key, value) => {
        form.setData('specs', form.data.specs.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row));
    };
    const addRow = () => form.setData('specs', [...form.data.specs, blankRow()]);
    const removeRow = (index) => {
        if (form.data.specs.length === 1) return;
        form.setData('specs', form.data.specs.filter((_, rowIndex) => rowIndex !== index));
    };
    const deviceLabel = (code) => device_options.find((item) => item.value === code)?.label ?? code;

    return <AuthenticatedLayout title="Template Spesifikasi IT" breadcrumbs={['Administrasi', 'Master Data', 'Template Spesifikasi IT']}>
        <Head title="Template Spesifikasi IT" />
        <div className="mx-auto max-w-6xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
            <header>
                <p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Administrasi / Master Data</p>
                <h1 className="mt-1 text-2xl font-extrabold tracking-tight">Template Spesifikasi IT</h1>
                <p className="mt-1 text-sm text-slate-500">Template internal untuk membantu reviewer IT mengisi spesifikasi teknis. Template tidak terlihat oleh pemohon dan tidak mengubah spesifikasi historis.</p>
            </header>

            <section className="ui-card p-5">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-base font-bold">{editing ? 'Ubah template' : 'Tambah template'}</h2>
                    {editing && <button type="button" onClick={cancelEdit} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold">Batal edit</button>}
                </div>
                <form onSubmit={submit} className="mt-4 space-y-4">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div><label className="block text-xs font-semibold text-slate-600">Kode <b className="text-rose-600">*</b></label><input value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))} maxLength="50" className="mt-1 block w-full rounded-md border-slate-300 text-sm" placeholder="laptop_standard" /><InputError message={form.errors.code} /></div>
                        <div><label className="block text-xs font-semibold text-slate-600">Nama <b className="text-rose-600">*</b></label><input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} maxLength="100" className="mt-1 block w-full rounded-md border-slate-300 text-sm" placeholder="Laptop Standard Office" /><InputError message={form.errors.name} /></div>
                        <div><label className="block text-xs font-semibold text-slate-600">Jenis perangkat</label><select value={form.data.device_type} onChange={(event) => form.setData('device_type', event.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm"><option value="">Semua perangkat</option>{device_options.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select><InputError message={form.errors.device_type} /></div>
                        <div><label className="block text-xs font-semibold text-slate-600">Urutan</label><input type="number" min="0" max="9999" value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" /><InputError message={form.errors.sort_order} /></div>
                    </div>

                    <div className="overflow-hidden rounded-lg border border-slate-200">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500"><tr><th className="px-3 py-2">Komponen</th><th className="px-3 py-2">Spesifikasi</th><th className="w-20 px-3 py-2" /></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {form.data.specs.map((row, index) => <tr key={index}>
                                    <td className="px-3 py-2"><input aria-label={`Komponen ${index + 1}`} value={row.label} onChange={(event) => setRow(index, 'label', event.target.value)} maxLength="100" className="block w-full rounded-md border-slate-300 text-sm" placeholder="CPU" /><InputError message={form.errors[`specs.${index}.label`]} /></td>
                                    <td className="px-3 py-2"><input aria-label={`Spesifikasi ${index + 1}`} value={row.value} onChange={(event) => setRow(index, 'value', event.target.value)} maxLength="500" className="block w-full rounded-md border-slate-300 text-sm" placeholder="Intel Core i7" /><InputError message={form.errors[`specs.${index}.value`]} /></td>
                                    <td className="px-3 py-2 text-right"><button type="button" onClick={() => removeRow(index)} disabled={form.data.specs.length === 1} className="rounded border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 disabled:opacity-40">Hapus</button></td>
                                </tr>)}
                            </tbody>
                        </table>
                    </div>
                    <InputError message={form.errors.specs} />
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <button type="button" onClick={addRow} className="rounded-lg border border-blue-200 px-3 py-2 text-sm font-semibold text-blue-700">+ Tambah baris</button>
                            <label className="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> Aktif</label>
                        </div>
                        <button disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{form.processing ? 'Menyimpan...' : editing ? 'Simpan perubahan' : '+ Tambah template'}</button>
                    </div>
                </form>
            </section>

            <section className="ui-card overflow-hidden">
                <header className="border-b border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-700 dark:bg-slate-900"><h2 className="text-sm font-bold">Daftar template</h2></header>
                <div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left text-sm">
                    <thead className="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-900/70 dark:text-slate-400"><tr><th className="px-4 py-3">Kode</th><th className="px-4 py-3">Nama</th><th className="px-4 py-3">Perangkat</th><th className="px-4 py-3">Baris</th><th className="px-4 py-3">Status</th><th className="px-4 py-3" /></tr></thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {templates.map((template) => <tr key={template.id}>
                            <td className="px-4 py-3 font-mono text-xs">{template.code}</td>
                            <td className="px-4 py-3 font-semibold">{template.name}</td>
                            <td className="px-4 py-3 text-xs">{template.device_type ? deviceLabel(template.device_type) : 'Semua perangkat'}</td>
                            <td className="px-4 py-3"><details><summary className="cursor-pointer text-xs font-semibold text-blue-700">{template.specs_json?.length ?? 0} baris</summary><ul className="mt-2 space-y-1 text-xs text-slate-600">{(template.specs_json ?? []).map((row, index) => <li key={`${row.label}-${index}`}><b>{row.label}</b>: {row.value}</li>)}</ul></details></td>
                            <td className="px-4 py-3">{template.is_active ? <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">Aktif</span> : <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-600">Nonaktif</span>}</td>
                            <td className="px-4 py-3 text-right"><button type="button" onClick={() => startEdit(template)} className="rounded-md px-2 py-1 text-xs font-bold text-blue-700 hover:bg-blue-50">Ubah</button></td>
                        </tr>)}
                    </tbody>
                </table></div>
            </section>
        </div>
    </AuthenticatedLayout>;
}
