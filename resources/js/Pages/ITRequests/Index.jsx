import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const types = { new_item: 'Barang baru', replacement: 'Penggantian' };
const devices = { laptop: 'Laptop', desktop: 'Desktop', workstation_cad: 'Workstation / CAD', upgrade: 'Upgrade' };

export default function Index({ requests, canCreate }) {
    const rows = requests?.data ?? [];
    const waiting = rows.filter((item) => ['submitted', 'in_review', 'processing'].includes(item.status)).length;

    return <AuthenticatedLayout title="Hardware & Software (Non-ERP) Request" breadcrumbs={['Ruang Kerja', 'Hardware & Software (Non-ERP) Request']}>
        <Head title="Hardware & Software (Non-ERP) Request" />
        <div className="mx-auto max-w-7xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
            <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Ruang Kerja / Teknologi Informasi</p><h1 className="mt-1 text-2xl font-extrabold tracking-tight">Hardware &amp; Software (Non-ERP) Request</h1><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Pengajuan perangkat IT dan telekomunikasi. Persetujuan ini bukan persetujuan pembelian.</p></div>{canCreate && <Link href={route('it-requests.create')} className="ui-button-primary w-fit rounded-lg px-4 py-2.5 text-sm">+ Buat request</Link>}</header>
            <section className="grid grid-cols-1 gap-3 sm:grid-cols-3"><article className="ui-card border-l-4 border-l-blue-600 p-4"><p className="text-xs text-slate-500">Request terdaftar</p><p className="mt-1 text-2xl font-extrabold">{requests?.total ?? rows.length}</p><p className="mt-1 text-xs text-slate-500">Sesuai cakupan akses</p></article><article className="ui-card border-l-4 border-l-amber-400 p-4"><p className="text-xs text-slate-500">Menunggu proses</p><p className="mt-1 text-2xl font-extrabold">{waiting}</p><p className="mt-1 text-xs text-slate-500">Pada halaman ini</p></article><article className="ui-card border-l-4 border-l-emerald-500 p-4"><p className="text-xs text-slate-500">Selesai</p><p className="mt-1 text-2xl font-extrabold">{rows.filter((item) => item.status === 'completed').length}</p><p className="mt-1 text-xs text-slate-500">Pada halaman ini</p></article></section>

            <section className="ui-card overflow-hidden">
                {rows.length ? <div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left text-sm"><thead className="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-900/70 dark:text-slate-400"><tr><th className="px-4 py-3">Nomor request</th><th className="px-4 py-3">Penerima</th><th className="px-4 py-3">Jenis</th><th className="px-4 py-3">Perangkat</th><th className="px-4 py-3">Status</th><th className="px-4 py-3" /></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">{rows.map((item) => <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40"><td className="px-4 py-3 font-mono text-xs font-semibold">{item.request_number}</td><td className="px-4 py-3">{item.recipient?.name ?? <span className="text-xs text-slate-400">—</span>}</td><td className="px-4 py-3">{types[item.request_type] ?? item.request_type}</td><td className="px-4 py-3">{devices[item.device_type] ?? <span className="text-xs text-slate-400">—</span>}</td><td className="px-4 py-3"><StatusBadge status={item.status} /></td><td className="px-4 py-3 text-right"><Link className="rounded-md px-2 py-1 text-xs font-bold text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/50" href={route('it-requests.show', item.id)}>Buka →</Link></td></tr>)}</tbody></table></div> : <div className="p-5"><EmptyState title="Belum ada IT request" description="Buat pengajuan perangkat IT baru untuk diproses.">{canCreate && <Link href={route('it-requests.create')} className="ui-button-primary rounded-lg px-4 py-2 text-sm">Buat request</Link>}</EmptyState></div>}
                {(requests?.links?.length ?? 0) > 3 && <nav aria-label="Navigasi IT request" className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs dark:border-slate-700"><p className="text-slate-500">{requests.from ?? 0}–{requests.to ?? 0} dari {requests.total ?? 0}</p><div className="flex gap-1">{requests.links.map((page, index) => <Link key={`${page.label}-${index}`} href={page.url || '#'} preserveScroll className={`rounded-md border px-2.5 py-1.5 ${page.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'} ${!page.url ? 'pointer-events-none opacity-40' : ''}`}>{page.label.replace('&laquo;', '‹').replace('&raquo;', '›')}</Link>)}</div></nav>}
            </section>
        </div>
    </AuthenticatedLayout>;
}
