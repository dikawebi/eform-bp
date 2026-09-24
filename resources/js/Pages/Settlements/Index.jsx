import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const sourceLabels = { leave_request: 'Cuti / Izin', travel_request: 'Perjalanan Dinas', new_join: 'New Join', other: 'Lainnya' };
const differenceStyles = { overpayment: 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300', underpayment: 'bg-blue-50 text-blue-800 ring-blue-600/20 dark:bg-blue-950/40 dark:text-blue-300', balanced: 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300' };
const rupiah = (value) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;

export default function Index({ settlements, statuses = [], filters = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [sourceType, setSourceType] = useState(filters.source_type ?? '');
    const list = settlements?.data ?? [];
    const filter = (event) => {
        event.preventDefault();
        router.get(route('settlements.index'), { search, status, source_type: sourceType }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AuthenticatedLayout title="Settlement">
            <Head title="Settlement" />
            <div className="mx-auto max-w-7xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Ruang Kerja / Rekonsiliasi</p><h1 className="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Settlement</h1><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Cocokkan advance dengan realisasi dan bukti biaya.</p></div><Link href={route('settlements.create')} className="ui-button-primary w-fit rounded-lg px-4 py-2.5 text-sm">+ Buat settlement</Link></header>

                <section className="grid grid-cols-1 gap-3 sm:grid-cols-3"><article className="ui-card border-l-4 border-l-blue-600 p-4"><p className="text-xs font-medium text-slate-500">Settlement terdaftar</p><p className="mt-1 text-2xl font-bold tabular-nums">{settlements?.total ?? 0}</p><p className="mt-1 text-xs text-slate-500">Sesuai akses akun Anda</p></article><article className="ui-card border-l-4 border-l-amber-400 p-4"><p className="text-xs font-medium text-slate-500">Sedang ditampilkan</p><p className="mt-1 text-2xl font-bold tabular-nums">{list.length}</p><p className="mt-1 text-xs text-slate-500">Pada halaman ini</p></article><article className="ui-card border-l-4 border-l-emerald-500 p-4"><p className="text-xs font-medium text-slate-500">Jenis sumber</p><p className="mt-1 text-sm font-bold">Cuti · Dinas · Manual</p><p className="mt-1 text-xs text-slate-500">Advance manual bernilai Rp0</p></article></section>

                <section className="ui-card overflow-hidden">
                    <form onSubmit={filter} className="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-700 lg:flex-row">
                        <input aria-label="Cari settlement" value={search} onChange={(event) => setSearch(event.target.value)} className="block min-w-0 flex-1 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" placeholder="Cari nomor settlement, karyawan, atau referensi" />
                        <select aria-label="Filter status" value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"><option value="">Semua status</option>{statuses.map((value) => <option key={value} value={value}>{value.replaceAll('_', ' ')}</option>)}</select>
                        <select aria-label="Filter sumber" value={sourceType} onChange={(event) => setSourceType(event.target.value)} className="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"><option value="">Semua sumber</option>{Object.entries(sourceLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>
                        <button className="ui-button-primary rounded-lg px-4 py-2 text-sm">Terapkan</button>
                    </form>

                    {list.length ? <div className="overflow-x-auto"><table className="w-full min-w-[850px] text-left text-sm"><thead className="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-900/70 dark:text-slate-400"><tr><th className="px-4 py-3">Settlement / sumber</th><th className="px-4 py-3">Karyawan</th><th className="px-4 py-3 text-right">Advance</th><th className="px-4 py-3 text-right">Realisasi</th><th className="px-4 py-3">Rekonsiliasi</th><th className="px-4 py-3">Status</th><th className="px-4 py-3" /></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">{list.map((item) => <tr key={item.id} className="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40"><td className="px-4 py-4"><Link className="font-bold text-blue-700 hover:underline dark:text-blue-400" href={route('settlements.show', item.id)}>{item.settlement_number}</Link><p className="mt-1 text-xs text-slate-500">{sourceLabels[item.source_type] ?? item.source_type}{item.source_reference ? ` · ${item.source_reference}` : ''}</p></td><td className="px-4 py-4 font-medium">{item.employee?.name ?? '—'}</td><td className="px-4 py-4 text-right tabular-nums">{rupiah(item.advance_amount)}</td><td className="px-4 py-4 text-right tabular-nums">{rupiah(item.actual_amount)}</td><td className="px-4 py-4"><span className={`ui-badge ${differenceStyles[item.difference_type] ?? 'bg-slate-100 text-slate-700'}`}>{(item.difference_type ?? 'belum dihitung').replaceAll('_', ' ')}</span><p className="mt-1 text-xs font-semibold tabular-nums">{rupiah(Math.abs(Number(item.difference_amount ?? 0)))}</p></td><td className="px-4 py-4"><StatusBadge status={item.status} /></td><td className="px-4 py-4 text-right"><Link href={route('settlements.show', item.id)} className="rounded-md px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/50">Buka →</Link></td></tr>)}</tbody></table></div> : <div className="p-5"><EmptyState title={filters.search || filters.status || filters.source_type ? 'Settlement tidak ditemukan' : 'Belum ada settlement'} description={filters.search || filters.status || filters.source_type ? 'Ubah filter atau kata kunci pencarian.' : 'Settlement tersedia setelah advance cuti/dinas eligible, atau dengan sumber manual dan nomor referensi.'}>{!filters.search && !filters.status && !filters.source_type && <Link href={route('settlements.create')} className="ui-button-primary rounded-lg px-4 py-2 text-sm">Buat settlement</Link>}</EmptyState></div>}

                    {(settlements?.links?.length ?? 0) > 3 && <nav aria-label="Navigasi settlement" className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-sm dark:border-slate-700"><p className="text-xs text-slate-500">Menampilkan {settlements.from ?? 0}–{settlements.to ?? 0} dari {settlements.total ?? 0}</p><div className="flex gap-1">{settlements.links.map((page, index) => <Link key={`${page.label}-${index}`} href={page.url || '#'} preserveScroll className={`rounded-md border px-2.5 py-1.5 text-xs ${page.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'} ${!page.url ? 'pointer-events-none opacity-40' : 'hover:bg-slate-50 dark:hover:bg-slate-800'}`}>{page.label.replace('&laquo;', '‹').replace('&raquo;', '›')}</Link>)}</div></nav>}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
