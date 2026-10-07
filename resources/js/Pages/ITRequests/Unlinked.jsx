import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Unlinked({ canManageEmployees = false }) {
    return <AuthenticatedLayout title="Profil karyawan belum terhubung" breadcrumbs={['Ruang Kerja', 'IT Request', 'Profil karyawan']}>
        <Head title="Profil karyawan belum terhubung" />
        <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
            <section className="ui-card overflow-hidden">
                <header className="border-b border-amber-200 bg-amber-50 px-6 py-5 dark:border-amber-900 dark:bg-amber-950/30">
                    <p className="text-xs font-bold uppercase tracking-[0.14em] text-amber-700 dark:text-amber-300">IT Request / Persiapan profil</p>
                    <h1 className="mt-1 text-xl font-extrabold text-slate-900 dark:text-white">Akun belum terhubung ke profil karyawan</h1>
                </header>
                <div className="space-y-4 p-6">
                    <p className="text-sm leading-6 text-slate-600 dark:text-slate-300">IT Request menggunakan NIK dan data karyawan dari master untuk menentukan pengaju. Profil karyawan aktif belum dikaitkan dengan akun login ini, jadi formulir belum dapat dimulai.</p>
                    <div className="flex flex-wrap gap-3">{canManageEmployees ? <Link href="/master/employees" className="ui-button-primary rounded-lg px-4 py-2.5 text-sm">Buka Master Karyawan</Link> : <p className="rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">Silakan minta Administrator atau HRGA menautkan akun Anda ke profil karyawan.</p>}<Link href="/it-requests" className="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Kembali ke daftar request</Link></div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>;
}
