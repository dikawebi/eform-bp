import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { formatRupiah } from './helpers';

const input = 'block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100';
const label = 'block text-xs font-semibold text-slate-600 dark:text-slate-300';
const metadataKeys = {
    flight: ['flight_destination', 'departure_time', 'airline', 'ticket_number'],
    hotel: ['check_in_date', 'check_out_date', 'nights'],
    land_transport: ['note'],
    meal: ['note'],
    other: ['note'],
};

export const normalizeMetadata = (category, metadata = {}) => Object.fromEntries(
    (metadataKeys[category] ?? []).filter((key) => metadata?.[key] !== undefined && metadata?.[key] !== null && metadata[key] !== '')
        .map((key) => [key, metadata[key]]),
);

const emptyItem = (category = 'land_transport') => ({ category, transaction_date: '', origin: '', destination: '', description: '', quantity: 1, unit_price: 0, metadata: {} });

function ErrorList({ errors }) {
    const list = Object.entries(errors ?? {});
    return list.length ? <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"><p className="font-semibold">Periksa kembali isian berikut:</p><ul className="mt-2 list-disc space-y-1 pl-5">{list.slice(0, 12).map(([key, value]) => <li key={key}>{String(value)}</li>)}</ul></div> : null;
}

function MetadataFields({ item, setMeta, index, errors }) {
    const field = (key, title, type = 'text', placeholder = '') => <div className="worksheet-cell"><label className={label} htmlFor={`travel-${index}-${key}`}>{title}</label><input id={`travel-${index}-${key}`} type={type} min={type === 'number' ? '0' : undefined} value={item.metadata?.[key] ?? ''} onChange={(event) => setMeta(key, event.target.value)} className={`${input} mt-1`} placeholder={placeholder} /><InputError message={errors[`items.${index}.metadata.${key}`]} className="mt-1" /></div>;

    if (item.category === 'flight') return <>
        {field('flight_destination', 'Tujuan flight', 'text', 'Kota / bandara tujuan')}
        {field('departure_time', 'Jam keberangkatan', 'time')}
        {field('airline', 'Maskapai', 'text', 'Contoh: Garuda Indonesia')}
        {field('ticket_number', 'Nomor tiket / booking')}
    </>;
    if (item.category === 'hotel') return <>
        {field('check_in_date', 'Tanggal check-in', 'date')}
        {field('check_out_date', 'Tanggal check-out', 'date')}
        <div className="worksheet-hint md:col-span-2">Jumlah malam dihitung otomatis dari tanggal menginap, lalu diverifikasi ulang oleh server.</div>
    </>;
    if (['land_transport', 'meal', 'other'].includes(item.category)) return <div className="md:col-span-2">{field('note', 'Catatan tambahan (opsional)', 'text', 'Contoh: rincian lokasi atau kebutuhan biaya')}</div>;
    return null;
}

function ItemRow({ item, index, options, errors, onChange, onRemove }) {
    const checkIn = item.metadata?.check_in_date;
    const checkOut = item.metadata?.check_out_date;
    const derivedNights = item.category === 'hotel' && checkIn && checkOut
        ? Math.max(0, (Date.parse(`${checkOut}T00:00:00Z`) - Date.parse(`${checkIn}T00:00:00Z`)) / 86400000)
        : null;
    const quantity = derivedNights ?? (Number(item.quantity) || 0);
    const amount = quantity * (Number(item.unit_price) || 0);
    const set = (field, value) => onChange({ ...item, [field]: value });
    const setMeta = (field, value) => {
        const metadata = normalizeMetadata(item.category, { ...(item.metadata ?? {}), [field]: value });
        const nights = metadata.check_in_date && metadata.check_out_date
            ? Math.max(0, (Date.parse(`${metadata.check_out_date}T00:00:00Z`) - Date.parse(`${metadata.check_in_date}T00:00:00Z`)) / 86400000)
            : item.quantity;
        onChange({ ...item, quantity: nights, metadata });
    };
    const changeCategory = (category) => onChange({ ...item, category, metadata: {} });

    return <article className="worksheet-row overflow-hidden">
        <header className="worksheet-row-header flex flex-wrap items-center justify-between gap-2 px-3 py-2.5">
            <div className="flex items-center gap-2"><span className="worksheet-row-index">{index + 1}</span><h4 className="text-sm font-bold">Item biaya perjalanan</h4><span className="rounded-md bg-white/80 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-slate-900 dark:text-emerald-300">Estimasi {formatRupiah(amount)}</span></div>
            <button type="button" onClick={onRemove} className="rounded-md px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40">Hapus baris</button>
        </header>
        <div className="grid grid-cols-1 gap-px bg-slate-200 p-px dark:bg-slate-700 md:grid-cols-2">
            <div className="worksheet-cell"><label className={label}>Kategori biaya</label><select value={item.category} onChange={(event) => changeCategory(event.target.value)} className={`${input} mt-1`}>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select><InputError message={errors[`items.${index}.category`]} className="mt-1" /></div>
            <div className="worksheet-cell"><label className={label}>Tanggal transaksi</label><input type="date" value={item.transaction_date ?? ''} onChange={(event) => set('transaction_date', event.target.value)} className={`${input} mt-1`} /><InputError message={errors[`items.${index}.transaction_date`]} className="mt-1" /></div>
            <div className="worksheet-cell"><label className={label}>Lokasi awal</label><input value={item.origin ?? ''} onChange={(event) => set('origin', event.target.value)} className={`${input} mt-1`} placeholder="Kota / lokasi asal" /><InputError message={errors[`items.${index}.origin`]} className="mt-1" /></div>
            <div className="worksheet-cell"><label className={label}>Tujuan</label><input value={item.destination ?? ''} onChange={(event) => set('destination', event.target.value)} className={`${input} mt-1`} placeholder="Kota / lokasi tujuan" /><InputError message={errors[`items.${index}.destination`]} className="mt-1" /></div>
            <div className="worksheet-cell md:col-span-2"><label className={label}>Keterangan / uraian biaya</label><input required value={item.description ?? ''} onChange={(event) => set('description', event.target.value)} className={`${input} mt-1`} placeholder="Contoh: Tiket perjalanan, hotel, atau transport lokal" /><InputError message={errors[`items.${index}.description`]} className="mt-1" /></div>
            {item.category !== 'hotel' && <div className="worksheet-cell"><label className={label}>Kuantitas</label><input type="number" min="0" step="0.01" value={item.quantity} onChange={(event) => set('quantity', event.target.value)} className={`${input} mt-1`} /><InputError message={errors[`items.${index}.quantity`]} className="mt-1" /></div>}
            {item.category === 'hotel' && <div className="worksheet-cell"><label className={label}>Jumlah malam (hasil tanggal)</label><input readOnly type="number" value={derivedNights ?? item.quantity} className={`${input} mt-1 bg-slate-50 dark:bg-slate-800`} /><InputError message={errors[`items.${index}.quantity`]} className="mt-1" /></div>}
            <div className="worksheet-cell"><label className={label}>Harga satuan (Rp)</label><input type="number" min="0" step="0.01" value={item.unit_price} onChange={(event) => set('unit_price', event.target.value)} className={`${input} mt-1`} /><InputError message={errors[`items.${index}.unit_price`]} className="mt-1" /></div>
            <MetadataFields item={item} setMeta={setMeta} index={index} errors={errors} />
        </div>
    </article>;
}

export default function TravelForm({ data, setData, errors, processing, meta, travelEmployee = null, submitLabel = 'Simpan Draft', batalHref, onSubmit }) {
    const [confirm, setConfirm] = useState(false);
    const options = meta?.cost_categories ?? [{ value: 'land_transport', label: 'Transport Darat' }, { value: 'flight', label: 'Tiket Pesawat' }, { value: 'hotel', label: 'Penginapan' }, { value: 'meal', label: 'Makan' }, { value: 'other', label: 'Lainnya' }];
    const employees = meta?.employees ?? [];
    const employee = employees.find((item) => String(item.id) === String(data.employee_id)) ?? travelEmployee ?? employees[0];
    const total = useMemo(() => (data.items ?? []).reduce((sum, item) => {
        const nights = item.category === 'hotel' && item.metadata?.check_in_date && item.metadata?.check_out_date
            ? Math.max(0, (Date.parse(`${item.metadata.check_out_date}T00:00:00Z`) - Date.parse(`${item.metadata.check_in_date}T00:00:00Z`)) / 86400000)
            : Number(item.quantity) || 0;
        return sum + nights * (Number(item.unit_price) || 0);
    }, 0), [data.items]);
    const updateItem = (index, item) => { const items = [...(data.items ?? [])]; items[index] = { ...item, metadata: item.metadata ?? {} }; setData('items', items); };
    const add = () => setData('items', [...(data.items ?? []), emptyItem(options[0]?.value ?? 'land_transport')]);
    const remove = (index) => setData('items', (data.items ?? []).filter((_, itemIndex) => itemIndex !== index));
    const submit = (event) => { event.preventDefault(); setConfirm(true); };

    return <form onSubmit={submit} className="worksheet-form space-y-5">
        <header className="worksheet-banner"><div><p className="worksheet-banner-kicker">Formulir karyawan · Perjalanan Dinas</p><h2 className="text-xl font-extrabold">Form Perjalanan Dinas</h2><p className="mt-1 text-sm text-blue-100">Isi header perjalanan terlebih dahulu, lalu masukkan biaya per item seperti lembar dinas.</p></div><span className="worksheet-banner-code">FORM DINAS</span></header>
        <ErrorList errors={errors} />
        <section className="worksheet-section overflow-hidden">
            <header className="worksheet-section-header"><div><p className="worksheet-eyebrow">Sheet Dinas · Bagian 01</p><h3 className="text-base font-bold">Data Perjalanan</h3><p className="text-xs text-slate-500">Isi data karyawan, keperluan, periode, dan rute sesuai formulir dinas.</p></div></header>
            <div className="grid grid-cols-1 gap-px bg-slate-200 p-px dark:bg-slate-700 md:grid-cols-2">
                {employees.length > 1 && <div className="worksheet-cell md:col-span-2"><label className={label}>Karyawan / NIK</label><select value={data.employee_id ?? ''} onChange={(event) => setData('employee_id', event.target.value)} className={`${input} mt-1`}><option value="">Pilih karyawan</option>{employees.map((item) => <option key={item.id} value={item.id}>{item.employee_number} · {item.name} · {item.department}</option>)}</select><InputError message={errors.employee_id} className="mt-1" /></div>}
                {employee && <div className="worksheet-cell md:col-span-2"><p className={label}>Profil karyawan dari master</p><p className="mt-1 text-sm font-bold">{employee.employee_number} · {employee.name}</p><p className="mt-0.5 text-xs text-slate-500">{employee.department} · {employee.poh_status === 'local' ? 'Lokal' : 'Non-lokal'} · Roster {employee.roster ?? '—'}</p></div>}
                <div className="worksheet-cell md:col-span-2"><label className={label}>Keperluan perjalanan</label><textarea required rows={3} value={data.purpose ?? ''} onChange={(event) => setData('purpose', event.target.value)} className={`${input} mt-1`} /><InputError message={errors.purpose} className="mt-1" /></div>
                <div className="worksheet-cell"><label className={label}>Tanggal mulai</label><input required type="date" value={data.start_date ?? ''} onChange={(event) => setData('start_date', event.target.value)} className={`${input} mt-1`} /><InputError message={errors.start_date} className="mt-1" /></div>
                <div className="worksheet-cell"><label className={label}>Tanggal selesai</label><input required type="date" value={data.end_date ?? ''} onChange={(event) => setData('end_date', event.target.value)} className={`${input} mt-1`} /><InputError message={errors.end_date} className="mt-1" /></div>
                <div className="worksheet-cell"><label className={label}>Lokasi asal</label><input required value={data.origin ?? ''} onChange={(event) => setData('origin', event.target.value)} className={`${input} mt-1`} /><InputError message={errors.origin} className="mt-1" /></div>
                <div className="worksheet-cell"><label className={label}>Lokasi tujuan</label><input required value={data.destination ?? ''} onChange={(event) => setData('destination', event.target.value)} className={`${input} mt-1`} /><InputError message={errors.destination} className="mt-1" /></div>
            </div>
        </section>

        <section className="worksheet-section overflow-hidden">
            <header className="worksheet-section-header flex flex-wrap items-center justify-between gap-3"><div><p className="worksheet-eyebrow">Sheet Dinas · Bagian 02</p><h3 className="text-base font-bold">Rincian Biaya</h3><p className="text-xs text-slate-500">Tambahkan satu baris untuk setiap biaya transport, flight, hotel, makan, atau lainnya.</p></div><span className="worksheet-total">Estimasi {formatRupiah(total)}</span></header>
            <div className="space-y-3 p-4">{(data.items ?? []).map((item, index) => <ItemRow key={index} item={{ ...item, metadata: item.metadata ?? {} }} index={index} options={options} errors={errors} onChange={(next) => updateItem(index, next)} onRemove={() => remove(index)} />)}<button type="button" onClick={add} className="worksheet-add-row">+ Tambah baris biaya</button><p className="worksheet-hint">Total akhir dihitung ulang server dari kuantitas × harga satuan. Lampiran undangan/dokumen pendukung dapat ditambahkan setelah draf tersimpan.</p></div>
        </section>

        <section className="worksheet-summary"><div><p className="text-xs font-bold uppercase tracking-[0.13em] text-blue-100">Total advance estimasi</p><p className="mt-1 text-2xl font-extrabold tabular-nums">{formatRupiah(total)}</p><p className="mt-1 text-xs text-blue-100">Hasil akhir dihitung ulang oleh server.</p></div><div className="flex gap-2"><Link href={batalHref} className="rounded-lg border border-white/40 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">Batal</Link><button disabled={processing} className="rounded-lg bg-white px-4 py-2 text-sm font-bold text-blue-700 disabled:opacity-60">{processing ? 'Menyimpan…' : submitLabel}</button></div></section>

        <Modal show={confirm} onClose={() => setConfirm(false)} maxWidth="md"><div className="p-6"><h2 className="text-lg font-semibold">Simpan pengajuan perjalanan?</h2><p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Draf akan disimpan dengan estimasi advance {formatRupiah(total)}. Total final dihitung ulang oleh server.</p><div className="mt-5 flex justify-end gap-2"><button type="button" onClick={() => setConfirm(false)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">Periksa lagi</button><button type="button" disabled={processing} onClick={() => { setConfirm(false); onSubmit(); }} className="ui-button-primary rounded-lg px-4 py-2 text-sm">Ya, simpan draf</button></div></div></Modal>
    </form>;
}

export { emptyItem };
