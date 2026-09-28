import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import SummaryCard from '@/Components/SummaryCard';
import StatusBadge from '@/Components/StatusBadge';

function DashboardIcon({ type }) {
    const paths = {
        request: <><path d="M6 3h9l3 3v15H6z" /><path d="M15 3v4h4M9 12h6M9 16h4" /></>,
        clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
        check: <><circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" /></>,
        leave: <><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M8 3v4M16 3v4M3 10h18" /></>,
        travel: <><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></>,
        settlement: <><path d="M3 7a2 2 0 0 1 2-2h14v4H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16v-8" /><circle cx="16" cy="14" r="1" /></>,
    };

    return <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{paths[type]}</svg>;
}

export default function Dashboard({ summary = {}, statusBreakdown = {}, pendingApproval = null, pendingActions = [], loading = false, error = null }) {
    const total = summary.total_pengajuan ?? Object.values(summary).reduce((sum, item) => sum + Object.values(item ?? {}).reduce((a, b) => a + Number(b), 0), 0);
    const statusCounts = Object.values(statusBreakdown).flatMap((items) => Object.entries(items ?? {}));
    const inProcess = summary.dalam_proses ?? statusCounts.filter(([status]) => ['submitted', 'in_review', 'processing', 'payment_processing'].includes(status)).reduce((sum, [, count]) => sum + Number(count), 0);
    const completed = summary.selesai ?? statusCounts.filter(([status]) => ['approved', 'completed'].includes(status)).reduce((sum, [, count]) => sum + Number(count), 0);
    const modules = [
        ['leave', 'Cuti / Izin', 'Kelola pengajuan cuti dan izin', 'leaves', '/leaves', 'leave'],
        ['travel', 'Perjalanan Dinas', 'Ajukan perjalanan dan advance', 'travels', '/travels', 'travel'],
        ['settlement', 'Settlement', 'Rekonsiliasi realisasi biaya', 'settlements', '/settlements', 'settlement'],
        ['medical', 'Medical Claim', 'Kelola klaim kesehatan', 'medical-claims', '/medical-claims', 'check'],
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dasbor
                </h2>
            }
        >
            <Head title="Dasbor" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-7 sm:px-6 lg:px-8">
                {loading && <div className="rounded-lg bg-white p-5 text-sm text-gray-500" role="status">Memuat ringkasan...</div>}
                {error && <div className="rounded-lg border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700" role="alert">Ringkasan tidak dapat dimuat. Silakan coba lagi.</div>}
                {!loading && !error && <>
                    <section className="overflow-hidden rounded-2xl bg-gradient-to-br from-[#0066FF] via-[#0757d8] to-[#0F172A] px-6 py-7 text-white shadow-lg shadow-blue-900/10 sm:px-8">
                        <div className="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                            <div className="max-w-2xl">
                                <p className="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-blue-200">eForm BP / Command Center</p>
                                <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Selamat datang kembali.</h1>
                                <p className="mt-2 max-w-xl text-sm leading-6 text-blue-100">Pantau pengajuan, approval, dan penyelesaian biaya Anda dari satu ruang kerja yang terarah.</p>
                            </div>
                            <Link href="/leaves/create" className="ui-button-primary inline-flex w-fit items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-bold text-[#0066FF] hover:bg-blue-50">
                                <span className="text-lg leading-none">+</span> Buat pengajuan
                            </Link>
                        </div>
                    </section>

                    <section className={`grid grid-cols-1 gap-4 ${pendingApproval !== null ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3'}`}>
                        <SummaryCard title="Total pengajuan" value={total} subtitle="Seluruh modul" className="border-l-4 border-l-[#0066FF]" />
                        <SummaryCard title="Dalam proses" value={inProcess} subtitle="Menunggu langkah berikutnya" className="border-l-4 border-l-amber-400" />
                        <SummaryCard title="Selesai" value={completed} subtitle="Pengajuan telah ditutup" className="border-l-4 border-l-emerald-500" />
                        {pendingApproval !== null && (
                            <Link href="/approvals" className="ui-card group relative overflow-hidden border-l-4 border-l-rose-500 p-5 transition hover:-translate-y-0.5 hover:border-rose-400 hover:shadow-md">
                                <span className="absolute right-4 top-4 rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">Pending approval</span>
                                <p className="text-sm font-medium text-gray-500">Approval saya</p>
                                <p className="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{Number(pendingApproval).toLocaleString('id-ID')}</p>
                                <p className="mt-1 text-sm text-gray-500 group-hover:text-rose-700 dark:text-gray-400 dark:group-hover:text-rose-300">Menunggu tindakan Anda</p>
                            </Link>
                        )}
                    </section>

                    {pendingActions.length > 0 && <section className="ui-card p-5 sm:p-6"><div className="mb-5 flex items-end justify-between gap-4"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-amber-600">Pending action</p><h2 className="mt-1 text-lg font-bold text-slate-900 dark:text-white">Tindakan yang perlu dikerjakan</h2></div><span className="text-xs text-slate-500">Sesuai role dan status transaksi</span></div><div className="grid grid-cols-1 gap-3 md:grid-cols-3">{pendingActions.map((item) => <Link key={item.key} href={item.href} className={`group rounded-xl border p-4 transition hover:-translate-y-0.5 hover:shadow-sm ${item.tone === 'rose' ? 'border-rose-200 hover:bg-rose-50' : item.tone === 'emerald' ? 'border-emerald-200 hover:bg-emerald-50' : 'border-amber-200 hover:bg-amber-50'}`}><div className="flex items-start justify-between gap-3"><div><h3 className="text-sm font-bold text-slate-900 group-hover:text-[#0066FF] dark:text-slate-100">{item.label}</h3><p className="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{item.description}</p></div><span className={`rounded-full px-2.5 py-1 text-xs font-extrabold ${item.tone === 'rose' ? 'bg-rose-100 text-rose-700' : item.tone === 'emerald' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>{item.count}</span></div><p className="mt-4 text-xs font-semibold text-slate-500 group-hover:text-[#0066FF]">Buka tindakan →</p></Link>)}</div></section>}

                    <section className="ui-card p-5 sm:p-6">
                        <div className="mb-5 flex items-end justify-between gap-4">
                            <div><p className="text-xs font-bold uppercase tracking-[0.14em] text-blue-600">Akses cepat</p><h2 className="mt-1 text-lg font-bold text-slate-900 dark:text-white">Ruang kerja Anda</h2></div>
                            <span className="hidden text-xs text-slate-500 sm:inline">Pilih modul untuk melanjutkan</span>
                        </div>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {modules.map(([, label, description, key, href, icon]) => (
                                <Link key={key} href={href} className="group rounded-xl border border-slate-200 p-4 transition hover:-translate-y-0.5 hover:border-blue-300 hover:bg-blue-50/50 hover:shadow-sm dark:border-slate-700 dark:hover:border-blue-500 dark:hover:bg-blue-950/30">
                                    <span className="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-[#0066FF] dark:bg-blue-950/60"><DashboardIcon type={icon} /></span>
                                    <h3 className="text-sm font-bold text-slate-900 group-hover:text-[#0066FF] dark:text-slate-100">{label}</h3>
                                    <p className="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{description}</p>
                                </Link>
                            ))}
                        </div>
                    </section>

                    <section className="ui-card p-5 sm:p-6">
                        <div className="mb-5 flex items-center justify-between"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-blue-600">Monitoring</p><h2 className="mt-1 text-lg font-bold text-slate-900 dark:text-white">Status transaksi</h2></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{total} total</span></div>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {modules.map(([module, label, , key, href, icon]) => {
                                const statuses = statusBreakdown[module] ?? {};
                                return <Link key={key} href={href} className="rounded-xl border border-slate-200 p-4 dark:border-slate-700"><div className="flex items-center gap-3"><span className="text-slate-400"><DashboardIcon type={icon} /></span><h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">{label}</h3></div>{Object.entries(statuses).length ? <div className="mt-4 space-y-2">{Object.entries(statuses).map(([status, count]) => <div key={status} className="flex items-center justify-between text-xs"><StatusBadge status={status} /><span className="font-bold text-slate-700 dark:text-slate-300">{count}</span></div>)}</div> : <p className="mt-4 text-xs text-slate-500 dark:text-slate-400">Belum ada transaksi.</p>}</Link>;
                            })}
                        </div>
                    </section>
                </>}
            </div>
        </AuthenticatedLayout>
    );
}
