import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    estimasiHari,
    formatRupiah,
    labelBiaya,
    labelPeriode,
    labelTipeCuti,
} from './helpers';

const inputCls =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
const labelCls = 'block text-xs font-medium text-gray-600';
const estimasiMalam = (checkIn, checkOut) => {
    if (!checkIn || !checkOut) return null;
    const utcDate = (value) => Date.UTC(...value.split('-').map((part, index) => Number(part) - (index === 1 ? 1 : 0)));
    return Math.max(0, (utcDate(checkOut) - utcDate(checkIn)) / 86400000);
};

function PeriodeRow({
    index,
    baris,
    opsiPeriode,
    errors,
    bisaHapus,
    onUbah,
    onHapus,
}) {
    const hari = estimasiHari(baris.start_date, baris.end_date);
    return (
        <div className="worksheet-row p-4">
            <div className="worksheet-row-header -mx-4 -mt-4 mb-4 flex items-center justify-between gap-2 rounded-t-lg px-4 py-2.5">
                <p className="text-sm font-semibold text-gray-800">
                    Periode {index + 1}
                    <span className="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                        Estimasi {hari} hari
                    </span>
                </p>
                {bisaHapus && (
                    <button
                        type="button"
                        onClick={onHapus}
                        className="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"
                    >
                        Hapus
                    </button>
                )}
            </div>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div className="md:col-span-2">
                    <label className={labelCls}>Kategori periode</label>
                    <select
                        value={baris.category}
                        onChange={(e) => onUbah('category', e.target.value)}
                        className={inputCls}
                    >
                        {(opsiPeriode ?? []).map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors[`periods.${index}.category`]} className="mt-1" />
                </div>
                <div>
                    <label className={labelCls}>Tanggal mulai</label>
                    <input
                        type="date"
                        value={baris.start_date}
                        onChange={(e) => onUbah('start_date', e.target.value)}
                        className={inputCls}
                    />
                    <InputError message={errors[`periods.${index}.start_date`]} className="mt-1" />
                </div>
                <div>
                    <label className={labelCls}>Tanggal selesai</label>
                    <input
                        type="date"
                        value={baris.end_date}
                        onChange={(e) => onUbah('end_date', e.target.value)}
                        className={inputCls}
                    />
                    <InputError message={errors[`periods.${index}.end_date`]} className="mt-1" />
                </div>
                <div className="md:col-span-2">
                    <label className={labelCls}>Catatan periode (opsional)</label>
                    <input
                        value={baris.notes ?? ''}
                        onChange={(e) => onUbah('notes', e.target.value)}
                        className={inputCls}
                        placeholder="cth. Perjalanan pulang ke POH"
                        maxLength={1000}
                    />
                    <InputError message={errors[`periods.${index}.notes`]} className="mt-1" />
                </div>
            </div>
        </div>
    );
}

function BiayaRow({ index, baris, opsiBiaya, errors, eligible, onUbah, onHapus }) {
    const malam = baris.category === 'hotel' ? estimasiMalam(baris.check_in_date, baris.check_out_date) : null;
    const qty = malam ?? (Number(baris.quantity) || 0);
    const harga = Number(baris.unit_price) || 0;
    const jumlah = eligible ? qty * harga : 0;
    return (
        <div className="worksheet-row p-4">
            <div className="worksheet-row-header -mx-4 -mt-4 mb-4 flex items-center justify-between gap-2 rounded-t-lg px-4 py-2.5">
                <p className="text-sm font-semibold text-gray-800">
                    Biaya {index + 1}
                    <span className="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                        Estimasi {formatRupiah(jumlah)}
                    </span>
                </p>
                <button
                    type="button"
                    onClick={onHapus}
                    className="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"
                >
                    Hapus
                </button>
            </div>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label className={labelCls}>Kategori biaya</label>
                    <select
                        value={baris.category}
                        onChange={(e) => onUbah('category', e.target.value)}
                        className={inputCls}
                    >
                        {(opsiBiaya ?? []).map((o) => (
                            <option key={o.value} value={o.value}>
                                {labelBiaya(null, o.value) === o.value ? o.value : o.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors[`cost_items.${index}.category`]} className="mt-1" />
                </div>
                {baris.category === 'land_transport' && <>
                    <div><label className={labelCls}>Lokasi awal (opsional)</label><input value={baris.origin ?? ''} onChange={(e) => onUbah('origin', e.target.value)} className={inputCls} placeholder="Rumah / kota asal" /><InputError message={errors[`cost_items.${index}.origin`]} /></div>
                    <div><label className={labelCls}>Tujuan (opsional)</label><input value={baris.destination ?? ''} onChange={(e) => onUbah('destination', e.target.value)} className={inputCls} placeholder="Bandara / site" /><InputError message={errors[`cost_items.${index}.destination`]} /></div>
                    <div><label className={labelCls}>Tujuan flight (opsional)</label><input value={baris.flight_destination ?? ''} onChange={(e) => onUbah('flight_destination', e.target.value)} className={inputCls} placeholder="Kota / bandara" /><InputError message={errors[`cost_items.${index}.flight_destination`]} /></div>
                    <div><label className={labelCls}>Jam berangkat (opsional)</label><input type="time" value={baris.departure_time ?? ''} onChange={(e) => onUbah('departure_time', e.target.value)} className={inputCls} /><InputError message={errors[`cost_items.${index}.departure_time`]} /></div>
                    <div><label className={labelCls}>Tanggal perjalanan (opsional)</label><input type="date" value={baris.service_date ?? ''} onChange={(e) => onUbah('service_date', e.target.value)} className={inputCls} /><InputError message={errors[`cost_items.${index}.service_date`]} /></div>
                </>}
                {baris.category === 'hotel' && <>
                    <div><label className={labelCls}>Check-in (opsional)</label><input type="date" value={baris.check_in_date ?? ''} onChange={(e) => onUbah('check_in_date', e.target.value)} className={inputCls} /><InputError message={errors[`cost_items.${index}.check_in_date`]} /></div>
                    <div><label className={labelCls}>Check-out (opsional)</label><input type="date" value={baris.check_out_date ?? ''} onChange={(e) => onUbah('check_out_date', e.target.value)} className={inputCls} /><InputError message={errors[`cost_items.${index}.check_out_date`]} /></div>
                </>}
                <div>
                    <label className={labelCls}>Keterangan</label>
                    <input
                        value={baris.description ?? ''}
                        onChange={(e) => onUbah('description', e.target.value)}
                        className={inputCls}
                        placeholder="cth. Bus site – kota"
                        maxLength={255}
                    />
                    <InputError
                        message={errors[`cost_items.${index}.description`]}
                        className="mt-1"
                    />
                </div>
                <div>
                    <label className={labelCls}>{malam === null ? 'Kuantitas' : 'Jumlah malam (dihitung dari tanggal)'}</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={malam ?? baris.quantity}
                        readOnly={malam !== null}
                        onChange={(e) => onUbah('quantity', e.target.value)}
                        className={inputCls}
                    />
                    <InputError message={errors[`cost_items.${index}.quantity`]} className="mt-1" />
                </div>
                <div>
                    <label className={labelCls}>Harga satuan (Rp)</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={baris.unit_price}
                        onChange={(e) => onUbah('unit_price', e.target.value)}
                        className={inputCls}
                    />
                    <InputError
                        message={errors[`cost_items.${index}.unit_price`]}
                        className="mt-1"
                    />
                </div>
            </div>
        </div>
    );
}

/**
 * Form cuti/izin bersama untuk Create & Edit.
 * Angka yang tampil = estimasi lokal; total final dihitung server.
 */
export default function LeaveForm({
    data,
    setData,
    errors,
    processing,
    meta,
    karyawanFallback = null,
    submitLabel = 'Simpan',
    batalHref = '/leaves',
    onSubmit,
}) {
    const [konfirmasi, setKonfirmasi] = useState(false);

    const daftarKaryawan = meta?.employees ?? [];
    const karyawanTerpilih =
        daftarKaryawan.find((k) => String(k.id) === String(data.employee_id)) ??
        karyawanFallback ??
        null;
    const adalahLokal = karyawanTerpilih?.poh_status === 'local';
    const lokalEligible = Boolean(meta?.leave_local_eligible);
    const biayaEligible = !adalahLokal || lokalEligible;

    const opsiPeriode = meta?.period_categories ?? [];
    const opsiBiaya = meta?.cost_categories ?? [];
    const tipeCuti = meta?.leave_types ?? [];

    const totalHari = useMemo(
        () =>
            (data.periods ?? []).reduce(
                (acc, p) => acc + estimasiHari(p.start_date, p.end_date),
                0,
            ),
        [data.periods],
    );

    const totalAdvance = useMemo(() => {
        if (!biayaEligible) return 0;
        return (data.cost_items ?? []).reduce(
            (acc, it) => acc + (Number(it.quantity) || 0) * (Number(it.unit_price) || 0),
            0,
        );
    }, [data.cost_items, biayaEligible]);

    const ubahPeriode = (index, field, value) => {
        const next = [...(data.periods ?? [])];
        next[index] = { ...next[index], [field]: value };
        setData('periods', next);
    };

    const tambahPeriode = () => {
        setData('periods', [
            ...(data.periods ?? []),
            {
                category: opsiPeriode[0]?.value ?? 'annual_leave',
                start_date: '',
                end_date: '',
                notes: '',
            },
        ]);
    };

    const hapusPeriode = (index) => {
        setData(
            'periods',
            (data.periods ?? []).filter((_, i) => i !== index),
        );
    };

    const ubahBiaya = (index, field, value) => {
        const next = [...(data.cost_items ?? [])];
        next[index] = { ...next[index], [field]: value };
        if (next[index].category === 'hotel' && (field === 'check_in_date' || field === 'check_out_date')) {
            const nights = estimasiMalam(next[index].check_in_date, next[index].check_out_date);
            if (nights !== null) next[index].quantity = nights;
        }
        setData('cost_items', next);
    };

    const tambahBiaya = () => {
        setData('cost_items', [
            ...(data.cost_items ?? []),
            {
                category: opsiBiaya[0]?.value ?? 'land_transport',
                description: '',
                quantity: 1,
                unit_price: 0,
            },
        ]);
    };

    const hapusBiaya = (index) => {
        setData(
            'cost_items',
            (data.cost_items ?? []).filter((_, i) => i !== index),
        );
    };

    const daftarError = Object.entries(errors ?? {});
    const kirim = (e) => {
        e.preventDefault();
        setKonfirmasi(true);
    };

    return (
        <form onSubmit={kirim} className="worksheet-form space-y-5">
            <header className="worksheet-banner"><div><p className="worksheet-banner-kicker">Formulir karyawan · Cuti / Izin</p><h2 className="text-xl font-extrabold">Form Cuti / Izin</h2><p className="mt-1 text-sm text-blue-100">Susunan data mengikuti lembar form perusahaan: data pengajuan, periode, lalu rincian biaya.</p></div><span className="worksheet-banner-code">FORM CUTI</span></header>
            {daftarError.length > 0 && (
                <div className="rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p className="text-sm font-semibold text-rose-800">
                        Periksa kembali isian berikut:
                    </p>
                    <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-rose-700">
                        {daftarError.slice(0, 12).map(([key, msg]) => (
                            <li key={key}>{String(msg)}</li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Informasi pengajuan */}
            <section className="worksheet-section p-5">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Sheet Cuti / Izin · Bagian 01</p><h3 className="text-sm font-semibold text-gray-900">Data Karyawan & Pengajuan</h3><p className="mt-0.5 text-xs text-gray-500">Profil ditarik dari master karyawan; lengkapi informasi pengajuan seperti pada formulir sumber.</p></div>
                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    {daftarKaryawan.length > 1 ? (
                        <div>
                            <label className={labelCls}>Karyawan</label>
                            <select
                                value={data.employee_id ?? ''}
                                onChange={(e) => setData('employee_id', e.target.value)}
                                className={inputCls}
                            >
                                <option value="">— Pilih karyawan —</option>
                                {daftarKaryawan.map((k) => (
                                    <option key={k.id} value={k.id}>
                                        {k.name} ({k.employee_number}) — {k.department}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.employee_id} className="mt-1" />
                        </div>
                    ) : (
                        <div className="rounded-md bg-gray-50 p-3 text-sm text-gray-700 md:col-span-2">
                            <p className="font-medium">
                                {karyawanTerpilih?.name ?? 'Karyawan'} (
                                {karyawanTerpilih?.employee_number ?? '—'})
                            </p>
                            <p className="text-xs text-gray-500">
                                {karyawanTerpilih?.department ?? ''} · POH:{' '}
                                {karyawanTerpilih?.poh_status === 'local' ? 'Lokal' : 'Non-lokal'}
                            </p>
                        </div>
                    )}
                    <div>
                        <label className={labelCls}>Tipe cuti/izin</label>
                        <select
                            value={data.leave_type ?? ''}
                            onChange={(e) => setData('leave_type', e.target.value)}
                            className={inputCls}
                        >
                            <option value="">— Pilih tipe —</option>
                            {tipeCuti.map((t) => (
                                <option key={t} value={t}>
                                    {labelTipeCuti(t)}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.leave_type} className="mt-1" />
                    </div>
                    <div>
                        <label className={labelCls}>Hari terakhir kerja</label>
                        <input
                            type="date"
                            value={data.last_working_date ?? ''}
                            onChange={(e) => setData('last_working_date', e.target.value)}
                            className={inputCls}
                        />
                        <InputError message={errors.last_working_date} className="mt-1" />
                    </div>
                    <div>
                        <label className={labelCls}>Tanggal onsite setelah cuti</label>
                        <input
                            type="date"
                            value={data.onsite_date ?? ''}
                            onChange={(e) => setData('onsite_date', e.target.value)}
                            className={inputCls}
                        />
                        <InputError message={errors.onsite_date} className="mt-1" />
                    </div>
                    <div className="md:col-span-2">
                        <label className={labelCls}>Alasan / catatan</label>
                        <textarea
                            value={data.reason ?? ''}
                            onChange={(e) => setData('reason', e.target.value)}
                            rows={3}
                            className={inputCls}
                            placeholder="Tulis alasan pengajuan cuti/izin"
                        />
                        <InputError message={errors.reason} className="mt-1" />
                    </div>
                </div>
            </section>

            {/* Periode */}
            <section className="worksheet-section p-5">
                <div className="worksheet-section-heading mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p className="worksheet-eyebrow">Sheet Cuti / Izin · Bagian 02</p>
                        <h3 className="text-sm font-semibold text-gray-900">Rincian Periode Cuti/Izin</h3>
                        <p className="mt-0.5 text-xs text-gray-500">
                            Tambahkan satu baris untuk setiap rentang tanggal. Estimasi hari dihitung
                            inklusif — final dari server.
                        </p>
                    </div>
                    <span className="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                        Estimasi total {totalHari} hari
                    </span>
                </div>
                <InputError message={errors.periods} className="mt-2" />
                <div className="mt-4 space-y-3">
                    {(data.periods ?? []).map((baris, i) => (
                        <PeriodeRow
                            key={i}
                            index={i}
                            baris={baris}
                            opsiPeriode={opsiPeriode}
                            errors={errors}
                            bisaHapus={(data.periods ?? []).length > 1}
                            onUbah={(f, v) => ubahPeriode(i, f, v)}
                            onHapus={() => hapusPeriode(i)}
                        />
                    ))}
                </div>
                <button
                    type="button"
                    onClick={tambahPeriode}
                    className="mt-3 rounded-md border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-700"
                >
                    + Tambah Periode
                </button>
            </section>

            {/* Biaya */}
            <section className="worksheet-section p-5">
                <div className="worksheet-section-heading mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p className="worksheet-eyebrow">Sheet Cuti / Izin · Bagian 03</p>
                        <h3 className="text-sm font-semibold text-gray-900">
                            Biaya Perjalanan &amp; Akomodasi (opsional)
                        </h3>
                        <p className="mt-0.5 text-xs text-gray-500">
                            Jumlah per baris = kuantitas × harga satuan. Estimasi — final dari server.
                        </p>
                    </div>
                    <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        Estimasi {formatRupiah(totalAdvance)}
                    </span>
                </div>

                {adalahLokal && !lokalEligible && (
                    <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <p className="font-semibold">Karyawan berstatus lokal</p>
                        <p className="mt-0.5">
                            Sesuai kebijakan, biaya perjalanan karyawan lokal tidak eligible sehingga
                            advance bernilai Rp0. Baris biaya tetap tersimpan sebagai arsip, tetapi
                            total final ditentukan server.
                        </p>
                    </div>
                )}
                {adalahLokal && lokalEligible && (
                    <div className="mt-3 rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">
                        Karyawan berstatus lokal, namun kebijakan saat ini membolehkan komponen
                        biaya. Total final tetap dihitung server.
                    </div>
                )}
                {!adalahLokal && (
                    <div className="mt-3 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
                        Karyawan berstatus non-lokal: biaya dihitung dari kuantitas × harga satuan
                        untuk setiap baris.
                    </div>
                )}

                <InputError message={errors.cost_items} className="mt-2" />
                <div className="mt-4 space-y-3">
                    {(data.cost_items ?? []).map((baris, i) => (
                        <BiayaRow
                            key={i}
                            index={i}
                            baris={baris}
                            opsiBiaya={opsiBiaya}
                            errors={errors}
                            eligible={biayaEligible}
                            onUbah={(f, v) => ubahBiaya(i, f, v)}
                            onHapus={() => hapusBiaya(i)}
                        />
                    ))}
                    {(data.cost_items ?? []).length === 0 && (
                        <p className="rounded-md border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
                            Belum ada baris biaya. Lewati bagian ini bila pengajuan tidak
                            membutuhkan advance.
                        </p>
                    )}
                </div>
                <button
                    type="button"
                    onClick={tambahBiaya}
                    className="mt-3 rounded-md border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-700"
                >
                    + Tambah Biaya
                </button>
            </section>

            <div className="flex flex-wrap gap-3">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-gray-900 px-5 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-60"
                >
                    {processing ? 'Menyimpan…' : submitLabel}
                </button>
                <Link
                    href={batalHref}
                    className="rounded-md border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Batal
                </Link>
            </div>

            <Modal show={konfirmasi} onClose={() => setKonfirmasi(false)} maxWidth="md">
                <div className="p-6">
                    <h3 className="text-base font-semibold text-gray-900">
                        Konfirmasi penyimpanan
                    </h3>
                    <p className="mt-2 text-sm text-gray-600">
                        Data akan disimpan sebagai draf dengan estimasi{' '}
                        <span className="font-semibold">{totalHari} hari</span> dan advance{' '}
                        <span className="font-semibold">{formatRupiah(totalAdvance)}</span>.
                    </p>
                    <div className="mt-3 rounded-md bg-amber-50 p-3 text-xs text-amber-800">
                        Angka di atas hanya estimasi lokal. Total hari dan total advance final
                        dihitung ulang oleh server saat data disimpan — pengajuan belum terkirim ke
                        approval sebelum Anda menekan tombol Ajukan di halaman detail.
                    </div>
                    <div className="mt-5 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setKonfirmasi(false)}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Periksa Lagi
                        </button>
                        <button
                            type="button"
                            disabled={processing}
                            onClick={() => {
                                setKonfirmasi(false);
                                onSubmit();
                            }}
                            className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-60"
                        >
                            Ya, Simpan
                        </button>
                    </div>
                </div>
            </Modal>
        </form>
    );
}

export { labelBiaya, labelPeriode };
