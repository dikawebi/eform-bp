import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import SummaryCard from '@/Components/SummaryCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatRupiah, labelStatus, labelTipeCuti, statusValue } from './helpers';

export default function Index({ leaves, filters, statuses }) {
    const [search, setSearch] = useState(filters?.search ?? '');
    const [status, setStatus] = useState(filters?.status ?? '');

    const terapkan = (e) => {
        e.preventDefault();
        router.get(
            route('leaves.index'),
            { search, status },
            { preserveState: true, replace: true },
        );
    };

    const aturUlang = () => {
        setSearch('');
        setStatus('');
        router.get(route('leaves.index'), {}, { preserveState: true, replace: true });
    };

    const baris = leaves?.data ?? [];
    const totalSelesai = baris.filter((r) =>
        ['approved', 'advance_paid', 'completed'].includes(statusValue(r.status)),
    ).length;

    return (
        <AuthenticatedLayout
            title="Cuti/Izin"
            breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Cuti/Izin' }]}
        >
            <Head title="Cuti/Izin" />

            <div className="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Ruang Kerja / Cuti & Izin</p><h1 className="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Cuti / Izin</h1><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Kelola periode cuti, kebutuhan biaya, dan status pengajuan.</p></div><Link href={route('leaves.create')} className="ui-button-primary w-fit rounded-lg px-4 py-2.5 text-sm">+ Buat pengajuan</Link></header>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <SummaryCard judul="Total Pengajuan" nilai={leaves?.total ?? baris.length} />
                    <SummaryCard judul="Disetujui / Selesai (halaman ini)" nilai={totalSelesai} />
                    <SummaryCard
                        judul="Perlu Perhatian"
                        nilai={
                            baris.filter((r) => statusValue(r.status) === 'returned').length
                        }
                        sub="Dikembalikan — perlu diperbaiki"
                    />
                </div>

                <form
                    onSubmit={terapkan}
                    className="ui-card flex flex-wrap items-end gap-3 p-4"
                >
                    <div className="min-w-0 flex-1 basis-56">
                        <label className="block text-xs font-medium text-gray-600">
                            Cari nomor / nama karyawan
                        </label>
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                            placeholder="cth. CUTI-202609-0001 / Budi"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600">Status</label>
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="mt-1 rounded-md border-gray-300 text-sm"
                        >
                            <option value="">Semua status</option>
                            {(statuses ?? []).map((s) => (
                                <option key={s} value={s}>
                                    {labelStatus(s)}
                                </option>
                            ))}
                        </select>
                    </div>
                    <button
                        type="submit"
                        className="ui-button-primary rounded-lg px-4 py-2 text-sm"
                    >
                        Cari
                    </button>
                    <button
                        type="button"
                        onClick={aturUlang}
                        className="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Atur Ulang
                    </button>
                </form>

                <div className="ui-card overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-4 py-3">Nomor</th>
                                    <th className="px-4 py-3">Tipe</th>
                                    <th className="px-4 py-3">Karyawan</th>
                                    <th className="px-4 py-3 text-right">Total Hari</th>
                                    <th className="px-4 py-3 text-right">Advance</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {baris.map((cuti) => (
                                    <tr key={cuti.id} className="border-t border-gray-100 hover:bg-gray-50/60">
                                        <td className="whitespace-nowrap px-4 py-2.5 font-mono text-xs">
                                            {cuti.request_number}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            {labelTipeCuti(cuti.leave_type)}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            {cuti.employee?.name ?? cuti.employee_name ?? '—'}
                                            <span className="block text-xs text-gray-500">
                                                {cuti.employee?.department ?? cuti.department ?? ''}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5 text-right">
                                            {cuti.total_days ?? 0} hari
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-right">
                                            {formatRupiah(cuti.total_advance)}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <StatusBadge status={statusValue(cuti.status)} />
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2.5">
                                            <Link
                                                href={route('leaves.show', cuti.id)}
                                                className="font-medium text-indigo-600 hover:underline"
                                            >
                                                Detail
                                            </Link>
                                            {['draft', 'returned'].includes(
                                                statusValue(cuti.status),
                                            ) && (
                                                <Link
                                                    href={route('leaves.edit', cuti.id)}
                                                    className="ml-3 font-medium text-gray-700 hover:underline"
                                                >
                                                    Ubah
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {baris.length === 0 && (
                        <div className="p-4">
                            <EmptyState
                                title="Belum ada pengajuan cuti"
                                description="Buat pengajuan cuti/izin baru. Periode tanggal dan biaya dapat diisi bertahap sebagai draf."
                            >
                                <Link
                                    href={route('leaves.create')}
                                    className="ui-button-primary rounded-lg px-4 py-2 text-sm"
                                >
                                    + Buat Cuti
                                </Link>
                            </EmptyState>
                        </div>
                    )}
                </div>

                {leaves?.links && leaves.last_page > 1 && (
                    <div className="flex flex-wrap items-center gap-1.5">
                        {leaves.links.map((taut, i) => (
                            <Link
                                key={i}
                                href={taut.url ?? '#'}
                                preserveState
                                className={`rounded-md px-3 py-1.5 text-sm ${
                                    taut.active
                                        ? 'bg-gray-900 text-white'
                                        : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'
                                } ${!taut.url ? 'pointer-events-none opacity-50' : ''}`}
                            >
                                <span>{String(taut.label).replace(/&laquo;|&raquo;/g, (value) => value === '&laquo;' ? '«' : '»')}</span>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
