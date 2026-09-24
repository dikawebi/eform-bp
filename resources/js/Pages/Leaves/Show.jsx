import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import StatusBadge from '@/Components/StatusBadge';
import SummaryCard from '@/Components/SummaryCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Fragment, useMemo, useRef, useState } from 'react';
import {
    formatRupiah,
    formatTanggal,
    formatTanggalWaktu,
    isStatusCancellable,
    isStatusEditable,
    isStatusSubmittable,
    labelBiaya,
    labelPeriode,
    labelStatus,
    labelTipeCuti,
    statusValue,
} from './helpers';

const TAHAP = [
    { key: 'draft', label: 'Draf' },
    { key: 'submitted', label: 'Diajukan' },
    { key: 'in_review', label: 'Diperiksa' },
    { key: 'approved', label: 'Disetujui' },
    { key: 'processing', label: 'Diproses HRGA' },
    { key: 'advance_paid', label: 'Advance Dibayar' },
    { key: 'completed', label: 'Selesai' },
];

function Stepper({ status }) {
    const s = statusValue(status);
    const cabang = ['returned', 'rejected', 'cancelled'].includes(s);
    const indeksAktif = TAHAP.findIndex((t) => t.key === s);

    return (
        <div className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <h3 className="text-sm font-semibold text-gray-900">Alur Pengajuan</h3>
            <ol className="mt-4 flex flex-col gap-0 sm:flex-row sm:items-start">
                {TAHAP.map((tahap, i) => {
                    const selesai = !cabang && indeksAktif >= 0 && i < indeksAktif;
                    const aktif = !cabang && i === indeksAktif;
                    return (
                        <li key={tahap.key} className="flex flex-1 gap-3 sm:flex-col sm:gap-1.5">
                            <div className="flex flex-col items-center">
                                <span
                                    className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold ${
                                        aktif
                                            ? 'bg-indigo-600 text-white'
                                            : selesai
                                              ? 'bg-emerald-500 text-white'
                                              : 'bg-gray-200 text-gray-500'
                                    }`}
                                >
                                    {selesai ? '✓' : i + 1}
                                </span>
                                {i < TAHAP.length - 1 && (
                                    <span
                                        className={`mt-1 w-0.5 flex-1 sm:mt-1.5 sm:h-0.5 sm:w-full ${
                                            selesai ? 'bg-emerald-400' : 'bg-gray-200'
                                        }`}
                                        aria-hidden="true"
                                    />
                                )}
                            </div>
                            <p
                                className={`pb-4 text-xs sm:pb-0 ${
                                    aktif
                                        ? 'font-semibold text-indigo-700'
                                        : selesai
                                          ? 'text-gray-700'
                                          : 'text-gray-400'
                                }`}
                            >
                                {tahap.label}
                            </p>
                        </li>
                    );
                })}
            </ol>
            {cabang && (
                <p className="mt-2 text-xs text-gray-500">
                    Status saat ini berada di luar alur normal — lihat keterangan status di bawah.
                </p>
            )}
        </div>
    );
}

function labelAktivitas(deskripsi) {
    const peta = {
        'leave.created': 'Draf pengajuan dibuat',
        'leave.updated': 'Pengajuan diperbarui',
        'leave.submitted': 'Pengajuan dikirim (submit)',
        'leave.cancelled': 'Pengajuan dibatalkan',
    };
    return peta[String(deskripsi)] ?? String(deskripsi ?? 'Aktivitas');
}

/** Ambil alasan return/reject dari audit trail bila tersedia. */
function cariAlasan(activities) {
    for (const a of activities ?? []) {
        const props = a?.properties ?? {};
        const teks =
            props.comments ?? props.reason ?? props.catatan ?? props.alasan ?? null;
        const aksi = String(props.action ?? props.to ?? a?.description ?? '').toLowerCase();
        if (teks && (aksi.includes('return') || aksi.includes('reject'))) {
            return { aksi, teks: String(teks), waktu: a?.created_at };
        }
    }
    return null;
}

export default function Show({
    leave,
    requires_settlement,
    activities,
    canEdit,
    availableActions,
}) {
    const [konfirmasi, setKonfirmasi] = useState(null); // 'submit' | 'cancel' | null
    const [memproses, setMemproses] = useState(false);
    const fileInput = useRef(null);

    const status = statusValue(leave?.status);
    const periode = leave?.periods ?? leave?.period ?? [];
    const biaya = leave?.costItems ?? leave?.cost_items ?? [];
    const karyawan = leave?.employee ?? {};
    const alasan = useMemo(() => cariAlasan(activities), [activities]);

    // Backend Phase 2 belum mengirim availableActions/canEdit — dukung bila
    // kelak dikirim, dan turunkan dari status sebagai fallback tampilan.
    // Otorisasi final tetap di server (policy); tombol di sini bukan kontrol akses.
    const aksi = useMemo(() => {
        if (Array.isArray(availableActions)) return availableActions.map((a) => String(a));
        const daftar = [];
        if (isStatusEditable(status)) daftar.push('edit');
        if (isStatusSubmittable(status)) daftar.push('submit');
        if (isStatusCancellable(status)) daftar.push('cancel');
        if (status === 'advance_paid' || requires_settlement) daftar.push('settlement_info');
        return daftar;
    }, [availableActions, status, requires_settlement]);

    const bolehUbah =
        typeof canEdit === 'boolean' ? canEdit : aksi.includes('edit');

    const jalankan = (jenis) => {
        setMemproses(true);
        const url =
            jenis === 'submit'
                ? route('leaves.submit', leave.id)
                : route('leaves.cancel', leave.id);
        router.post(url, {}, {
            preserveScroll: true,
            onFinish: () => {
                setMemproses(false);
                setKonfirmasi(null);
            },
        });
    };

    const unggah = (event) => {
        event.preventDefault();
        const file = fileInput.current?.files?.[0];
        if (!file) return;
        const form = new FormData();
        form.append('file', file);
        form.append('document_type', 'supporting_document');
        router.post(route('leaves.attachments.store', leave.id), form, { forceFormData: true, preserveScroll: true, onSuccess: () => { fileInput.current.value = ''; } });
    };

    return (
        <AuthenticatedLayout
            title={`Cuti ${leave?.request_number ?? ''}`}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Cuti/Izin', href: '/leaves' },
                { label: leave?.request_number ?? 'Detail' },
            ]}
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {leave?.request_number}
                        </h2>
                        <StatusBadge status={status} />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {bolehUbah && (
                            <Link
                                href={route('leaves.edit', leave.id)}
                                className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Ubah
                            </Link>
                        )}
                        {aksi.includes('submit') && (
                            <button
                                type="button"
                                onClick={() => setKonfirmasi('submit')}
                                className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                            >
                                Ajukan
                            </button>
                        )}
                        {aksi.includes('cancel') && (
                            <button
                                type="button"
                                onClick={() => setKonfirmasi('cancel')}
                                className="rounded-md border border-rose-300 bg-white px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50"
                            >
                                Batalkan
                            </button>
                        )}
                        {availableActions?.can_process_advance && <button type="button" onClick={() => router.post(route('leaves.advance', leave.id), {}, { preserveScroll: true })} className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Proses Advance</button>}
                    </div>
                </div>
            }
        >
            <Head title={leave?.request_number ?? 'Detail Cuti'} />

            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                {(status === 'returned' || status === 'rejected') && (
                    <div
                        className={`rounded-lg border p-4 text-sm ${
                            status === 'returned'
                                ? 'border-orange-200 bg-orange-50 text-orange-900'
                                : 'border-rose-200 bg-rose-50 text-rose-900'
                        }`}
                    >
                        <p className="font-semibold">
                            {status === 'returned'
                                ? 'Pengajuan dikembalikan — perlu diperbaiki'
                                : 'Pengajuan ditolak'}
                        </p>
                        {alasan ? (
                            <p className="mt-1">
                                Alasan: <span className="font-medium">{alasan.teks}</span>{' '}
                                <span className="text-xs opacity-70">
                                    ({formatTanggalWaktu(alasan.waktu)})
                                </span>
                            </p>
                        ) : (
                            <p className="mt-1">
                                Alasan tercatat pada timeline audit di bawah. Perbaiki data lalu
                                ajukan ulang bila status dikembalikan.
                            </p>
                        )}
                    </div>
                )}

                <Stepper status={status} />

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <SummaryCard judul="Total Hari" nilai={`${leave?.total_days ?? 0} hari`} />
                    <SummaryCard
                        judul="Total Advance (final server)"
                        nilai={formatRupiah(leave?.total_advance)}
                    />
                    <SummaryCard
                        judul="Status POH"
                        nilai={leave?.is_local ? 'Lokal' : 'Non-lokal'}
                        sub={
                            leave?.is_local
                                ? 'Aturan biaya lokal berlaku'
                                : 'Biaya dihitung dari item'
                        }
                    />
                </div>

                {requires_settlement && (
                    <div className="rounded-lg border border-fuchsia-200 bg-fuchsia-50 p-4 text-sm text-fuchsia-900">
                        Pengajuan ini memiliki advance sehingga{' '}
                        <span className="font-semibold">membutuhkan settlement</span> setelah proses
                        biaya selesai (modul Settlement — Phase 5).
                    </div>
                )}

                {(leave?.attachments?.length > 0 || availableActions?.can_upload) && <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100"><h3 className="text-sm font-semibold text-gray-900">Lampiran</h3><ul className="mt-2 space-y-1">{(leave.attachments ?? []).map((file) => <li key={file.id}><a className="text-sm text-indigo-600 hover:underline" href={file.download_url}>{file.original_name}</a></li>)}</ul>{availableActions?.can_upload && <form onSubmit={unggah} className="mt-4 flex flex-wrap items-end gap-3 border-t pt-4"><div><label className="block text-xs font-medium text-gray-600">Tambah lampiran</label><input ref={fileInput} required type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" className="mt-1 block text-sm" /></div><button className="rounded bg-gray-900 px-3 py-2 text-sm text-white">Unggah</button></form>}</section>}

                {/* Informasi utama — read-only */}
                <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <h3 className="text-sm font-semibold text-gray-900">Informasi Pengajuan</h3>
                    <dl className="mt-3 grid grid-cols-1 gap-x-6 gap-y-3 text-sm md:grid-cols-2">
                        <div>
                            <dt className="text-xs text-gray-500">Karyawan</dt>
                            <dd className="font-medium text-gray-900">
                                {karyawan.name ?? leave?.employee_name ?? '—'}{' '}
                                <span className="font-normal text-gray-500">
                                    ({karyawan.employee_number ?? leave?.employee_number ?? '—'})
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-gray-500">Departemen</dt>
                            <dd className="text-gray-900">
                                {karyawan.department ?? leave?.department ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-gray-500">Tipe cuti/izin</dt>
                            <dd className="text-gray-900">{labelTipeCuti(leave?.leave_type)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-gray-500">Status</dt>
                            <dd>
                                <StatusBadge status={status} />{' '}
                                <span className="text-xs text-gray-500">{labelStatus(status)}</span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-gray-500">Hari terakhir kerja</dt>
                            <dd className="text-gray-900">
                                {formatTanggal(leave?.last_working_date)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-gray-500">Onsite setelah cuti</dt>
                            <dd className="text-gray-900">{formatTanggal(leave?.onsite_date)}</dd>
                        </div>
                        <div className="md:col-span-2">
                            <dt className="text-xs text-gray-500">Alasan / catatan</dt>
                            <dd className="whitespace-pre-line text-gray-900">
                                {leave?.reason ?? '—'}
                            </dd>
                        </div>
                    </dl>
                </section>

                {/* Periode */}
                <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <h3 className="text-sm font-semibold text-gray-900">
                        Periode Cuti/Izin ({periode.length})
                    </h3>
                    {periode.length === 0 ? (
                        <p className="mt-2 text-sm text-gray-500">Tidak ada periode.</p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full min-w-[560px] text-left text-sm">
                                <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-3 py-2">Kategori</th>
                                        <th className="px-3 py-2">Mulai</th>
                                        <th className="px-3 py-2">Selesai</th>
                                        <th className="px-3 py-2 text-right">Hari</th>
                                        <th className="px-3 py-2">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {periode.map((p, i) => (
                                        <tr key={p.id ?? i} className="border-t border-gray-100">
                                            <td className="px-3 py-2">
                                                {labelPeriode(null, p.category)}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2">
                                                {formatTanggal(p.start_date)}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2">
                                                {formatTanggal(p.end_date)}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {p.day_count ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 text-gray-600">
                                                {p.notes ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {/* Biaya */}
                <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <h3 className="text-sm font-semibold text-gray-900">
                        Biaya Perjalanan ({biaya.length})
                    </h3>
                    {biaya.length === 0 ? (
                        <p className="mt-2 text-sm text-gray-500">
                            Tidak ada komponen biaya — pengajuan tanpa advance.
                        </p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full min-w-[640px] text-left text-sm">
                                <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-3 py-2">Kategori</th>
                                        <th className="px-3 py-2">Keterangan</th>
                                        <th className="px-3 py-2 text-right">Qty</th>
                                        <th className="px-3 py-2 text-right">Harga Satuan</th>
                                        <th className="px-3 py-2 text-right">Jumlah</th>
                                        <th className="px-3 py-2">Eligible</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {biaya.map((it, i) => (
                                        <Fragment key={it.id ?? i}>
                                        <tr className="border-t border-gray-100">
                                            <td className="px-3 py-2">
                                                {labelBiaya(null, it.category)}
                                            </td>
                                            <td className="px-3 py-2">{it.description ?? '—'}</td>
                                            <td className="px-3 py-2 text-right">
                                                {it.quantity ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-right">
                                                {formatRupiah(it.unit_price)}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-right font-medium">
                                                {formatRupiah(it.amount)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {it.eligible_by_policy === false ? (
                                                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">
                                                        Tidak eligible (lokal)
                                                    </span>
                                                ) : (
                                                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">
                                                        Eligible
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                        {(it.origin || it.destination || it.flight_destination || it.service_date || it.check_in_date || it.check_out_date || it.departure_time) && <tr className="border-t border-gray-50 bg-slate-50/60"><td colSpan={6} className="px-3 py-2 text-xs text-slate-600">{[it.origin && `Asal: ${it.origin}`, it.destination && `Tujuan: ${it.destination}`, it.flight_destination && `Tujuan flight: ${it.flight_destination}`, it.service_date && `Tanggal: ${formatTanggal(it.service_date)}`, (it.check_in_date || it.check_out_date) && `Hotel: ${formatTanggal(it.check_in_date)} – ${formatTanggal(it.check_out_date)}`, it.departure_time && `Jam: ${it.departure_time}`].filter(Boolean).join(' · ')}</td></tr>}
                                        </Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {/* Timeline audit */}
                <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <h3 className="text-sm font-semibold text-gray-900">
                        Riwayat &amp; Audit Trail
                    </h3>
                    {(!activities || activities.length === 0) && (
                        <div className="mt-3">
                            <EmptyState
                                title="Belum ada riwayat"
                                description="Setiap pengajuan, pembatalan, dan perubahan status dicatat di sini."
                            />
                        </div>
                    )}
                    {(activities ?? []).length > 0 && (
                        <ol className="mt-4 space-y-4 border-l-2 border-gray-200 pl-4">
                            {activities.map((a) => (
                                <li key={a.id} className="relative text-sm">
                                    <span
                                        className="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-indigo-500 ring-2 ring-white"
                                        aria-hidden="true"
                                    />
                                    <p className="font-medium text-gray-900">
                                        {labelAktivitas(a.description)}
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        {a.causer?.name ?? 'Sistem'} ·{' '}
                                        {formatTanggalWaktu(a.created_at)}
                                    </p>
                                    {a.properties && Object.keys(a.properties).length > 0 && (
                                        <div className="mt-1 flex flex-wrap gap-1.5 text-xs">
                                            {a.properties.from && (
                                                <span className="rounded bg-gray-100 px-2 py-0.5 text-gray-600">
                                                    {labelStatus(a.properties.from)} →{' '}
                                                    {labelStatus(a.properties.to)}
                                                </span>
                                            )}
                                            {(a.properties.comments ||
                                                a.properties.reason) && (
                                                <span className="rounded bg-amber-50 px-2 py-0.5 text-amber-800 ring-1 ring-amber-200">
                                                    “{a.properties.comments ?? a.properties.reason}”
                                                </span>
                                            )}
                                            {a.properties.total_days !== undefined && (
                                                <span className="rounded bg-gray-100 px-2 py-0.5 text-gray-600">
                                                    {a.properties.total_days} hari
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </section>

                <div className="flex flex-wrap gap-2">
                    <Link
                        href={route('leaves.index')}
                        className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    >
                        Kembali ke Daftar
                    </Link>
                </div>
            </div>

            <Modal show={konfirmasi !== null} onClose={() => setKonfirmasi(null)} maxWidth="md">
                <div className="p-6">
                    <h3 className="text-base font-semibold text-gray-900">
                        {konfirmasi === 'submit' ? 'Ajukan pengajuan?' : 'Batalkan pengajuan?'}
                    </h3>
                    <p className="mt-2 text-sm text-gray-600">
                        {konfirmasi === 'submit'
                            ? `Pengajuan ${leave?.request_number} dengan total ${leave?.total_days ?? 0} hari dan advance ${formatRupiah(leave?.total_advance)} akan dikirim ke alur approval dan tidak bisa diubah lagi sampai dikembalikan.`
                            : `Pengajuan ${leave?.request_number} akan dibatalkan. Aksi ini dicatat di audit trail dan tidak menghapus data.`}
                    </p>
                    <div className="mt-3 rounded-md bg-amber-50 p-3 text-xs text-amber-800">
                        Keputusan akhir ada di server sesuai status dan hak akses Anda.
                    </div>
                    <div className="mt-5 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setKonfirmasi(null)}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Kembali
                        </button>
                        <button
                            type="button"
                            disabled={memproses}
                            onClick={() => jalankan(konfirmasi)}
                            className={`rounded-md px-4 py-2 text-sm font-medium text-white disabled:opacity-60 ${
                                konfirmasi === 'submit'
                                    ? 'bg-gray-900 hover:bg-gray-700'
                                    : 'bg-rose-600 hover:bg-rose-500'
                            }`}
                        >
                            {memproses
                                ? 'Memproses…'
                                : konfirmasi === 'submit'
                                  ? 'Ya, Ajukan'
                                  : 'Ya, Batalkan'}
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
