import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Index({ notifications }) {
    const rows = notifications?.data ?? [];
    const unread = Number(usePage().props.unreadNotifications ?? 0);
    const open = (item, event) => {
        if (!item.read_at) {
            event.preventDefault();
            router.post(route('notifications.read', item.id), {}, {
                preserveScroll: true,
                onSuccess: () => router.visit(item.url || '/dashboard'),
            });
        }
    };

    return (
        <AuthenticatedLayout title="Notifikasi" breadcrumbs={['Notifikasi']}>
            <Head title="Notifikasi" />
            <div className="mx-auto max-w-4xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Pusat informasi</p>
                        <h1 className="mt-1 text-2xl font-extrabold">Notifikasi</h1>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Pembaruan approval, status, dan proses advance.</p>
                    </div>
                    {unread > 0 && <button type="button" onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">Tandai semua dibaca</button>}
                </header>

                <section className="ui-card overflow-hidden">
                    {rows.length ? <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                        {rows.map((item) => <li key={item.id} className={`flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between ${item.read_at ? '' : 'bg-blue-50/60 dark:bg-blue-950/20'}`}>
                            <div className="flex min-w-0 gap-3">
                                <span className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${item.read_at ? 'bg-slate-300 dark:bg-slate-600' : 'bg-blue-600'}`} />
                                <div className="min-w-0"><h2 className="text-sm font-bold">{item.title}</h2><p className="mt-1 text-sm text-slate-600 dark:text-slate-300">{item.message}</p><p className="mt-1 text-xs text-slate-400">{item.created_at ? new Date(item.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : ''}</p></div>
                            </div>
                            <div className="flex shrink-0 gap-2 pl-5 sm:pl-0">
                                {!item.read_at && <button type="button" onClick={() => router.post(route('notifications.read', item.id), {}, { preserveScroll: true })} className="rounded-md px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">Tandai dibaca</button>}
                                <Link href={item.url || '/dashboard'} onClick={(event) => open(item, event)} className="rounded-md px-2 py-1 text-xs font-bold text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/50">Buka →</Link>
                            </div>
                        </li>)}
                    </ul> : <div className="p-5"><EmptyState title="Belum ada notifikasi" description="Pembaruan penting workflow akan tampil di sini." /></div>}

                    {(notifications?.links?.length ?? 0) > 3 && <nav aria-label="Navigasi notifikasi" className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs dark:border-slate-700"><p className="text-slate-500">{notifications.from ?? 0}–{notifications.to ?? 0} dari {notifications.total ?? 0}</p><div className="flex gap-1">{notifications.links.map((page, index) => <Link key={`${page.label}-${index}`} href={page.url || '#'} preserveScroll className={`rounded-md border px-2.5 py-1.5 ${page.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'} ${!page.url ? 'pointer-events-none opacity-40' : ''}`}>{page.label.replace('&laquo;', '‹').replace('&raquo;', '›')}</Link>)}</div></nav>}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
