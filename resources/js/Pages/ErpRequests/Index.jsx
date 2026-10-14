import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const actions = { new_account: 'Akun baru', modify_role: 'Perubahan role', reset_auth: 'Reset akses' };
const statusLabels = { draft: 'Draft', submitted: 'Diajukan', in_review: 'Dalam Review', returned: 'Dikembalikan', rejected: 'Ditolak', approved: 'Disetujui', processing: 'Diproses', advance_paid: 'Advance Dibayar', settlement_required: 'Perlu Settlement', payment_processing: 'Proses Pembayaran', completed: 'Selesai', cancelled: 'Dibatalkan' };
const fmtDate = (value) => value ? new Date(value).toLocaleDateString('id-ID', { dateStyle: 'medium' }) : '—';

const th = 'px-4 py-3 align-top';
const thButton = 'font-bold uppercase tracking-wide hover:text-blue-700 dark:hover:text-blue-400';
const filterInput = 'mt-1 block w-full rounded-md border-slate-300 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-900';

export default function Index({ requests, canCreate, filters = {}, statuses = [], sort = 'created_at', direction = 'desc' }) {
    const rows = requests?.data ?? [];
    const waiting = rows.filter((item) => ['submitted', 'in_review', 'processing'].includes(item.status)).length;
    const [search, setSearch] = useState(filters?.search ?? '');
    const [status, setStatus] = useState(filters?.status ?? '');
    const [actionType, setActionType] = useState(filters?.action_type ?? '');
    const [dateFrom, setDateFrom] = useState(filters?.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters?.date_to ?? '');

    const query = (overrides = {}) => router.get(route('erp-requests.index'), { search, status, action_type: actionType, date_from: dateFrom, date_to: dateTo, sort, direction, ...overrides }, { preserveState: true, replace: true });
    const aturUlang = () => { setSearch(''); setStatus(''); setActionType(''); setDateFrom(''); setDateTo(''); router.get(route('erp-requests.index'), {}, { preserveState: true, replace: true }); };
    const sortir = (key) => {
        if (sort === key) query({ direction: direction === 'asc' ? 'desc' : 'asc' });
        else query({ sort: key, direction: 'desc' });
    };
    const panah = (key) => sort === key ? (direction === 'asc' ? ' ▲' : ' ▼') : '';
    const hasFilter = (filters?.search ?? '') !== '' || (filters?.status ?? '') !== '' || (filters?.action_type ?? '') !== '' || (filters?.date_from ?? '') !== '' || (filters?.date_to ?? '') !== '';

    return <AuthenticatedLayout title="ERP Request" breadcrumbs={['Ruang Kerja', 'ERP Request']}>
        <Head title="ERP Request" />
        <div className="mx-auto max-w-7xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
            <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Ruang Kerja / Sistem ERP</p><h1 className="mt-1 text-2xl font-extrabold tracking-tight">ERP Request</h1><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Permohonan akun dan otorisasi Microsoft Dynamics 365 F&amp;O.</p></div>{canCreate && <Link href={route('erp-requests.create')} className="ui-button-primary w-fit rounded-lg px-4 py-2.5 text-sm">+ Buat request</Link>}</header>
            <section className="grid grid-cols-1 gap-3 sm:grid-cols-3"><article className="ui-card border-l-4 border-l-blue-600 p-4"><p className="text-xs text-slate-500">Request terdaftar</p><p className="mt-1 text-2xl font-extrabold">{requests?.total ?? rows.length}</p><p className="mt-1 text-xs text-slate-500">Sesuai cakupan akses</p></article><article className="ui-card border-l-4 border-l-amber-400 p-4"><p className="text-xs text-slate-500">Menunggu proses</p><p className="mt-1 text-2xl font-extrabold">{waiting}</p><p className="mt-1 text-xs text-slate-500">Pada halaman ini</p></article><article className="ui-card border-l-4 border-l-emerald-500 p-4"><p className="text-xs text-slate-500">Selesai</p><p className="mt-1 text-2xl font-extrabold">{rows.filter((item) => item.status === 'completed').length}</p><p className="mt-1 text-xs text-slate-500">Pada halaman ini</p></article></section>

            <section className="ui-card overflow-hidden">
                <div className="overflow-x-auto"><table className="w-full min-w-[780px] text-left text-sm"><thead className="bg-slate-50 text-[11px] text-slate-500 dark:bg-slate-900/70 dark:text-slate-400"><tr><th className={th}><button type="button" onClick={() => sortir('request_number')} className={thButton}>Nomor request{panah('request_number')}</button></th><th className={th}>Penerima</th><th className={th}><button type="button" onClick={() => sortir('action_type')} className={thButton}>Tipe{panah('action_type')}</button></th><th className={th}><button type="button" onClick={() => sortir('status')} className={thButton}>Status{panah('status')}</button></th><th className={th}><button type="button" onClick={() => sortir('created_at')} className={thButton}>Tanggal{panah('created_at')}</button></th><th className={th}>{hasFilter && <button type="button" onClick={aturUlang} className="text-[11px] font-bold normal-case tracking-normal text-rose-600 hover:underline">Atur ulang</button>}</th></tr><tr><th className={th} colSpan={2}><input value={search} onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); query(); } }} className={filterInput} placeholder="Cari nomor / penerima…" maxLength="100" aria-label="Cari nomor atau penerima" /></th><th className={th}><select value={actionType} onChange={(event) => { setActionType(event.target.value); query({ action_type: event.target.value }); }} className={filterInput} aria-label="Filter tipe"><option value="">Semua</option>{Object.entries(actions).map(([value, text]) => <option key={value} value={value}>{text}</option>)}</select></th><th className={th}><select value={status} onChange={(event) => { setStatus(event.target.value); query({ status: event.target.value }); }} className={filterInput} aria-label="Filter status"><option value="">Semua</option>{statuses.map((value) => <option key={value} value={value}>{statusLabels[value] ?? value}</option>)}</select></th><th className={th}><div className="flex flex-col gap-1"><input type="date" value={dateFrom} max={dateTo || undefined} onChange={(event) => { setDateFrom(event.target.value); query({ date_from: event.target.value }); }} className={filterInput} aria-label="Tanggal dari" /><input type="date" value={dateTo} min={dateFrom || undefined} onChange={(event) => { setDateTo(event.target.value); query({ date_to: event.target.value }); }} className={filterInput} aria-label="Tanggal sampai" /></div></th><th className={th} /></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">{rows.map((item) => <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40"><td className="px-4 py-3 font-mono text-xs font-semibold">{item.request_number}</td><td className="px-4 py-3">{item.recipient?.name ?? <span className="text-xs text-slate-400">—</span>}</td><td className="px-4 py-3">{actions[item.action_type] ?? item.action_type}</td><td className="px-4 py-3"><StatusBadge status={item.status} /></td><td className="px-4 py-3 text-xs text-slate-500">{fmtDate(item.created_at)}</td><td className="px-4 py-3 text-right"><Link className="rounded-md px-2 py-1 text-xs font-bold text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/50" href={route('erp-requests.show', item.id)}>Buka →</Link></td></tr>)}</tbody></table></div>
                {!rows.length && <div className="p-5"><EmptyState title="Belum ada ERP request" description="Tidak ada data yang cocok dengan pencarian atau filter.">{canCreate && <Link href={route('erp-requests.create')} className="ui-button-primary rounded-lg px-4 py-2 text-sm">Buat request</Link>}</EmptyState></div>}
                {(requests?.links?.length ?? 0) > 3 && <nav aria-label="Navigasi ERP request" className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs dark:border-slate-700"><p className="text-slate-500">{requests.from ?? 0}–{requests.to ?? 0} dari {requests.total ?? 0}</p><div className="flex gap-1">{requests.links.map((page, index) => <Link key={`${page.label}-${index}`} href={page.url || '#'} preserveScroll className={`rounded-md border px-2.5 py-1.5 ${page.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'} ${!page.url ? 'pointer-events-none opacity-40' : ''}`}>{page.label.replace('&laquo;', '‹').replace('&raquo;', '›')}</Link>)}</div></nav>}
            </section>
        </div>
    </AuthenticatedLayout>;
}
