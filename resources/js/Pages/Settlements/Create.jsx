import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';

const empty = () => ({ transaction_date: '', description: '', category: 'other', amount: '', receipt_no: '' });
const input = 'block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100';
const categoryLabels = { transport: 'Transport', hotel: 'Hotel', meal: 'Makan', other: 'Lainnya' };
const sourceLabels = { leave_request: 'Cuti / Izin', travel_request: 'Perjalanan Dinas', new_join: 'New Join', other: 'Lainnya' };

export default function Create({ sources = [], categories = [], settlement = null }) {
    const { data, setData, post, put, processing, errors } = useForm({
        source_type: settlement?.source_type || '',
        source_id: settlement?.source_id || '',
        source_reference: settlement?.source_reference || '',
        items: settlement?.items?.map((item) => ({ ...item, transaction_date: item.transaction_date?.slice(0, 10) })) || [empty()],
    });

    const selectedSource = sources.find((source) => source.source_type === data.source_type && String(source.source_id) === String(data.source_id));
    const isManualSource = ['new_join', 'other'].includes(data.source_type);
    const totalActual = data.items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
    const advance = Number(selectedSource?.advance ?? settlement?.advance_amount ?? 0);
    const difference = advance - totalActual;
    const differenceLabel = difference > 0 ? 'OVERPAYMENT' : difference < 0 ? 'UNDERPAYMENT' : 'BALANCED';

    const add = () => setData('items', [...data.items, empty()]);
    const remove = (index) => setData('items', data.items.filter((_, row) => row !== index));
    const update = (index, key, value) => setData('items', data.items.map((row, i) => i === index ? { ...row, [key]: value } : row));
    const selectSource = (value) => {
        const [type, id] = value.split(':');
        setData((current) => ({ ...current, source_type: type, source_id: id, source_reference: '' }));
    };
    const submit = (event) => {
        event.preventDefault();
        settlement ? put(route('settlements.update', settlement.id)) : post(route('settlements.store'));
    };

    return (
        <AuthenticatedLayout title={settlement ? 'Ubah Settlement' : 'Buat Settlement'}>
            <Head title={settlement ? 'Ubah Settlement' : 'Buat Settlement'} />
            <form onSubmit={submit} className="worksheet-form mx-auto max-w-5xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
                <section className="overflow-hidden rounded-2xl bg-gradient-to-br from-[#0066FF] to-[#0F172A] p-6 text-white shadow-md">
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-blue-200">Settlement / Rekonsiliasi</p>
                    <h1 className="mt-1 text-2xl font-extrabold">{settlement ? 'Perbarui settlement' : 'Buat settlement'}</h1>
                    <p className="mt-2 text-sm text-blue-100">Pilih sumber, catat realisasi biaya, lalu periksa selisih sebelum menyimpan draf.</p>
                </section>

                <section className="ui-card space-y-4 p-5 sm:p-6">
                    <div><p className="text-xs font-bold uppercase tracking-[0.13em] text-blue-600">01 / Sumber</p><h2 className="mt-1 text-base font-bold">Sumber advance atau referensi</h2></div>
                    <div><label className="text-sm font-medium">Jenis dan karyawan sumber</label><select disabled={!!settlement} required value={`${data.source_type}:${data.source_id}`} onChange={(event) => selectSource(event.target.value)} className={`${input} mt-1`}><option value=":">Pilih sumber</option>{sources.map((source) => <option key={`${source.source_type}:${source.source_id}`} value={`${source.source_type}:${source.source_id}`}>{sourceLabels[source.source_type] ?? source.source_type} · {source.number} · Advance Rp {Number(source.advance || 0).toLocaleString('id-ID')}</option>)}</select><InputError message={errors.source_id || errors.source_type} className="mt-1" /></div>
                    {selectedSource && <div className="grid grid-cols-1 gap-3 rounded-xl border border-blue-100 bg-blue-50/70 p-4 dark:border-blue-900 dark:bg-blue-950/30 sm:grid-cols-3"><div><p className="text-xs text-slate-500 dark:text-slate-400">Karyawan / referensi</p><p className="mt-1 text-sm font-semibold">{selectedSource.number}</p></div><div><p className="text-xs text-slate-500 dark:text-slate-400">Jenis sumber</p><p className="mt-1 text-sm font-semibold">{sourceLabels[data.source_type]}</p></div><div><p className="text-xs text-slate-500 dark:text-slate-400">Advance sumber</p><p className="mt-1 text-sm font-bold tabular-nums">Rp {advance.toLocaleString('id-ID')}</p></div></div>}
                    {isManualSource && <div><label className="text-sm font-medium">Nomor referensi sumber</label><input required maxLength={100} value={data.source_reference} onChange={(event) => setData('source_reference', event.target.value)} className={`${input} mt-1`} placeholder="Masukkan nomor dokumen / referensi" /><p className="mt-1 text-xs text-slate-500 dark:text-slate-400">Sumber manual mengikuti workbook: advance Rp0 dan wajib memiliki referensi.</p><InputError message={errors.source_reference} className="mt-1" /></div>}
                </section>

                <section className="ui-card space-y-4 p-5 sm:p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-[0.13em] text-blue-600">02 / Realisasi</p><h2 className="mt-1 text-base font-bold">Rincian biaya aktual</h2></div><button type="button" onClick={add} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">+ Tambah item</button></div>
                    <InputError message={errors.items} />
                    {data.items.map((item, index) => <article key={index} className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/50"><div className="mb-4 flex items-center justify-between"><h3 className="text-sm font-bold">Item biaya {index + 1}</h3>{data.items.length > 1 && <button type="button" onClick={() => remove(index)} className="rounded-md px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40">Hapus</button>}</div><div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"><div><label className="text-sm font-medium">Tanggal transaksi</label><input required type="date" value={item.transaction_date} onChange={(event) => update(index, 'transaction_date', event.target.value)} className={`${input} mt-1`} /><InputError message={errors[`items.${index}.transaction_date`]} /></div><div><label className="text-sm font-medium">Kategori</label><select value={item.category} onChange={(event) => update(index, 'category', event.target.value)} className={`${input} mt-1`}>{categories.map((category) => <option key={category} value={category}>{categoryLabels[category] ?? category}</option>)}</select><InputError message={errors[`items.${index}.category`]} /></div><div><label className="text-sm font-medium">Nominal aktual (Rp)</label><input required min="0" step="0.01" type="number" value={item.amount} onChange={(event) => update(index, 'amount', event.target.value)} className={`${input} mt-1`} placeholder="0" /><InputError message={errors[`items.${index}.amount`]} /></div><div className="md:col-span-2 lg:col-span-2"><label className="text-sm font-medium">Deskripsi biaya</label><input required maxLength={255} value={item.description} onChange={(event) => update(index, 'description', event.target.value)} className={`${input} mt-1`} placeholder="Contoh: Transport dari bandara ke lokasi" /><InputError message={errors[`items.${index}.description`]} /></div><div><label className="text-sm font-medium">Nomor nota</label><input maxLength={100} value={item.receipt_no ?? ''} onChange={(event) => update(index, 'receipt_no', event.target.value)} className={`${input} mt-1`} placeholder="Opsional" /><InputError message={errors[`items.${index}.receipt_no`]} /></div></div></article>)}
                </section>

                <section className="ui-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"><div><p className="text-xs font-bold uppercase tracking-[0.13em] text-blue-600">03 / Ringkasan sementara</p><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Kalkulasi final akan divalidasi ulang oleh server.</p></div><div className="grid grid-cols-3 gap-4 text-right"><div><p className="text-xs text-slate-500">Advance</p><p className="mt-1 text-sm font-bold tabular-nums">Rp {advance.toLocaleString('id-ID')}</p></div><div><p className="text-xs text-slate-500">Realisasi</p><p className="mt-1 text-sm font-bold tabular-nums">Rp {totalActual.toLocaleString('id-ID')}</p></div><div><p className="text-xs text-slate-500">{differenceLabel}</p><p className="mt-1 text-sm font-bold tabular-nums">Rp {Math.abs(difference).toLocaleString('id-ID')}</p></div></div></section>

                <div className="flex flex-wrap justify-end gap-3"><Link href={route('settlements.index')} className="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Batal</Link><button disabled={processing} className="ui-button-primary rounded-lg px-5 py-2.5 text-sm disabled:opacity-60">{processing ? 'Menyimpan…' : 'Simpan Draft'}</button></div>
            </form>
        </AuthenticatedLayout>
    );
}
