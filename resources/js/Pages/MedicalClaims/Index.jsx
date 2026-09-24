import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const money = (value) => value == null ? null : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
const benefits = { obat_vitamin: 'Obat / Vitamin', rawat_jalan: 'Rawat Jalan', rawat_inap: 'Rawat Inap', lensa_kacamata: 'Lensa Kacamata', frame_kacamata: 'Frame Kacamata', medical_check_up: 'Medical Check Up', kacamata: 'Kacamata', persalinan: 'Persalinan', lainnya: 'Lainnya' };

export default function Index({ claims, canCreate }) {
    const rows = claims?.data ?? [];
    const waiting = rows.filter((claim) => ['submitted', 'in_review', 'processing'].includes(claim.status)).length;
    const completed = rows.filter((claim) => claim.status === 'completed').length;

    return <AuthenticatedLayout title="Medical Claim" breadcrumbs={['Ruang Kerja', 'Medical Claim']}>
        <Head title="Medical Claim" />
        <div className="mx-auto max-w-7xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
            <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Ruang Kerja / Kesehatan</p><h1 className="mt-1 text-2xl font-extrabold tracking-tight">Medical Claim</h1><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Detail medis dan nominal ditampilkan sesuai hak akses.</p></div>{canCreate && <Link href={route('medical-claims.create')} className="ui-button-primary w-fit rounded-lg px-4 py-2.5 text-sm">+ Buat klaim</Link>}</header>
            <section className="grid grid-cols-1 gap-3 sm:grid-cols-3"><article className="ui-card border-l-4 border-l-blue-600 p-4"><p className="text-xs text-slate-500">Klaim terdaftar</p><p className="mt-1 text-2xl font-extrabold">{claims?.total ?? rows.length}</p><p className="mt-1 text-xs text-slate-500">Sesuai cakupan akses</p></article><article className="ui-card border-l-4 border-l-amber-400 p-4"><p className="text-xs text-slate-500">Menunggu proses</p><p className="mt-1 text-2xl font-extrabold">{waiting}</p><p className="mt-1 text-xs text-slate-500">Pada halaman ini</p></article><article className="ui-card border-l-4 border-l-emerald-500 p-4"><p className="text-xs text-slate-500">Selesai</p><p className="mt-1 text-2xl font-extrabold">{completed}</p><p className="mt-1 text-xs text-slate-500">Pada halaman ini</p></article></section>

            <section className="ui-card overflow-hidden">
                {rows.length ? <div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left text-sm"><thead className="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-900/70 dark:text-slate-400"><tr><th className="px-4 py-3">Nomor klaim</th><th className="px-4 py-3">Karyawan</th><th className="px-4 py-3">Manfaat</th><th className="px-4 py-3 text-right">Total</th><th className="px-4 py-3">Status</th><th className="px-4 py-3" /></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">{rows.map((claim) => {
                    const selectedBenefits = claim.benefit_types?.length ? claim.benefit_types : (claim.benefit_type && claim.benefit_type !== 'medical_claim' ? [claim.benefit_type] : []);
                    return <tr key={claim.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40"><td className="px-4 py-3 font-mono text-xs font-semibold">{claim.claim_number}</td><td className="px-4 py-3">{claim.employee?.name ?? <span className="text-xs text-slate-400">Detail dibatasi</span>}</td><td className="px-4 py-3">{selectedBenefits.length ? <div className="flex flex-wrap gap-1">{selectedBenefits.map((benefit) => <span key={benefit} className="ui-badge bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">{benefits[benefit] ?? benefit}</span>)}</div> : <span className="text-xs text-slate-400">Detail dibatasi</span>}</td><td className="px-4 py-3 text-right tabular-nums">{money(claim.total_amount) ?? <span className="text-xs text-slate-400">Terbatas</span>}</td><td className="px-4 py-3"><StatusBadge status={claim.status} /></td><td className="px-4 py-3 text-right"><Link className="rounded-md px-2 py-1 text-xs font-bold text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/50" href={route('medical-claims.show', claim.id)}>Buka →</Link></td></tr>;
                })}</tbody></table></div> : <div className="p-5"><EmptyState title="Belum ada medical claim" description="Buat klaim kesehatan baru untuk diproses HRGA.">{canCreate && <Link href={route('medical-claims.create')} className="ui-button-primary rounded-lg px-4 py-2 text-sm">Buat klaim</Link>}</EmptyState></div>}
                {(claims?.links?.length ?? 0) > 3 && <nav aria-label="Navigasi medical claim" className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs dark:border-slate-700"><p className="text-slate-500">{claims.from ?? 0}–{claims.to ?? 0} dari {claims.total ?? 0}</p><div className="flex gap-1">{claims.links.map((page, index) => <Link key={`${page.label}-${index}`} href={page.url || '#'} preserveScroll className={`rounded-md border px-2.5 py-1.5 ${page.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'} ${!page.url ? 'pointer-events-none opacity-40' : ''}`}>{page.label.replace('&laquo;', '‹').replace('&raquo;', '›')}</Link>)}</div></nav>}
            </section>
        </div>
    </AuthenticatedLayout>;
}
