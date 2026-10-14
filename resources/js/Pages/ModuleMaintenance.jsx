import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function ModuleMaintenance({ label = 'Modul', message = '' }) {
    return <AuthenticatedLayout title={`${label} dalam maintenance`} breadcrumbs={['Maintenance']}>
        <Head title={`${label} dalam maintenance`} />
        <div className="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
            <p className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950"><svg className="h-7 w-7 text-amber-700 dark:text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M14.7 6.3a4.5 4.5 0 0 0-6 6L3 18l3 3 5.7-5.7a4.5 4.5 0 0 0 6-6L14 13l-3-3 3.7-3.7z" /></svg></p>
            <h1 className="mt-4 text-2xl font-extrabold tracking-tight">{label} dalam maintenance</h1>
            <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">{message !== '' ? message : 'Modul ini sedang dinonaktifkan oleh administrator. Silakan coba lagi nanti.'}</p>
            <div className="mt-6 flex justify-center gap-2">
                <Link href="/dashboard" className="rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-bold text-white">Kembali ke Dashboard</Link>
                <Link href="/approvals" className="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">Buka Approval</Link>
            </div>
        </div>
    </AuthenticatedLayout>;
}
