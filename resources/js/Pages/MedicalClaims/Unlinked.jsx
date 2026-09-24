import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Unlinked({ canManageEmployees = false }) {
    return <AuthenticatedLayout title="Profil karyawan belum terhubung" breadcrumbs={['Ruang Kerja', 'Medical Claim', 'Profil karyawan']}>
        <Head title="Profil karyawan belum terhubung" />
        <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
            <section className="ui-card overflow-hidden">
                <header className="border-b border-amber-200 bg-amber-50 px-6 py-5 dark:border-amber-900 dark:bg-amber-950/30">
                    <p className="text-xs font-bold uppercase tracking-[0.14em] text-amber-700 dark:text-amber-300">Medical Claim / Persiapan profil</p>
                    <h1 className="mt-1 text-xl font-extrabold text-slate-900 dark:text-white">Akun belum terhubung ke profil karyawan</h1>
                </header>
                <div className="space-y-4 p-6">
                    <p className="text-sm leading-6 text-slate-600 dark:text-slate-300">Medical Claim menggunakan NIK dan data karyawan dari master untuk menentukan pemilik klaim. Profil karyawan aktif belum dikaitkan dengan akun login ini, jadi formulir belum dapat dimulai.</p>
                    <div className="rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-900"><p className="font-bold">Yang perlu dilakukan</p><ol className="mt-2 list-decimal space-y-1 pl-5 text-slate-600 dark:text-slate-300"><li>Cari NIK Anda di Master Data Karyawan.</li><li>Edit profil tersebut dan tautkan ke akun login Anda.</li><li>Buka kembali menu Medical Claim setelah tautan tersimpan.</li></ol></div>
                    <div className="flex flex-wrap gap-3">{canManageEmployees ? <Link href="/master/employees" className="ui-button-primary rounded-lg px-4 py-2.5 text-sm">Buka Master Karyawan</Link> : <p className="rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">Silakan minta Administrator atau HRGA menautkan akun Anda ke profil karyawan.</p>}<Link href="/medical-claims" className="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Kembali ke daftar klaim</Link></div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>;
}
