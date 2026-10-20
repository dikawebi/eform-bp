import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const input = 'mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900';
const label = 'block text-xs font-semibold text-slate-600 dark:text-slate-300';

export default function Index({ settings = {}, hasPassword = false, hasRow = false }) {
    const errors = usePage().props.errors ?? {};
    const flashSuccess = usePage().props.flash?.success ?? null;
    const [form, setForm] = useState({
        mailer: settings.mailer ?? 'smtp',
        host: settings.host ?? '',
        port: settings.port ?? '',
        encryption: settings.encryption ?? 'tls',
        username: settings.username ?? '',
        password: '',
        from_address: settings.from_address ?? '',
        from_name: settings.from_name ?? '',
        notifications_enabled: Boolean(settings.notifications_enabled),
    });
    const [testAddress, setTestAddress] = useState(settings.from_address ?? '');
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);

    const set = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));
    const save = (event) => {
        event.preventDefault();
        setSaving(true);
        router.put(route('settings.mail.update'), form, { preserveScroll: true, onFinish: () => setSaving(false) });
    };
    const sendTest = () => {
        setTesting(true);
        router.post(route('settings.mail.test'), { test_address: testAddress }, { preserveScroll: true, onFinish: () => setTesting(false) });
    };

    return <AuthenticatedLayout title="Email & Notifikasi" breadcrumbs={['Pengaturan', 'Email & Notifikasi']}>
        <Head title="Email & Notifikasi" />
        <div className="mx-auto max-w-4xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
            <header><p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Pengaturan / Email</p><h1 className="mt-1 text-2xl font-extrabold tracking-tight">Email & Notifikasi</h1><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{hasRow ? 'Nilai berikut menimpa konfigurasi .env saat pengiriman email berjalan (job membaca nilainya per antrian).' : 'Belum ada pengaturan tersimpan di database — email memakai nilai .env. Simpan di sini untuk menimpa tanpa mengubah file .'}</p></header>

            {flashSuccess && <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{flashSuccess}</div>}
            {Object.keys(errors).length > 0 && <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p className="font-semibold">Periksa kembali isian berikut:</p><ul className="mt-2 list-disc space-y-1 pl-5">{Object.entries(errors).map(([key, message]) => <li key={key}>{String(message)}</li>)}</ul></div>}

            <form onSubmit={save} className="ui-card p-5 space-y-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className={label} htmlFor="mailer">Mailer</label>
                        <select id="mailer" value={form.mailer} onChange={(event) => set('mailer', event.target.value)} className={input}>
                            <option value="smtp">SMTP (produksi)</option>
                            <option value="log">Log (cobak lokal)</option>
                        </select>
                    </div>
                    <div>
                        <label className={label} htmlFor="encryption">Enkripsi</label>
                        <select id="encryption" value={form.encryption ?? ''} onChange={(event) => set('encryption', event.target.value)} className={input} disabled={form.mailer === 'log'}>
                            <option value="tls">TLS (umum untuk 587)</option>
                            <option value="ssl">SSL (umum untuk 465)</option>
                            <option value="none">Tanpa enkripsi</option>
                        </select>
                    </div>
                    <div className="sm:col-span-2">
                        <label className={label} htmlFor="host">Host SMTP</label>
                        <input id="host" value={form.host} onChange={(event) => set('host', event.target.value)} className={input} placeholder="smtp.borneoprima.com" disabled={form.mailer === 'log'} maxLength="190" />
                    </div>
                    <div>
                        <label className={label} htmlFor="port">Port</label>
                        <input id="port" type="number" min="1" max="65535" value={form.port} onChange={(event) => set('port', event.target.value)} className={input} placeholder="587" disabled={form.mailer === 'log'} />
                    </div>
                    <div>
                        <label className={label} htmlFor="username">Username SMTP</label>
                        <input id="username" value={form.username} onChange={(event) => set('username', event.target.value)} className={input} placeholder="admin.it@borneoprima.com" disabled={form.mailer === 'log'} maxLength="190" autoComplete="off" />
                    </div>
                    <div>
                        <label className={label} htmlFor="password">Password SMTP</label>
                        <input id="password" type="password" value={form.password} onChange={(event) => set('password', event.target.value)} className={input} placeholder={hasPassword ? '•••••••• (kosongkan = tetap)' : 'password SMTP'} disabled={form.mailer === 'log'} autoComplete="new-password" />
                    </div>
                    <div>
                        <label className={label} htmlFor="from_address">Email pengirim</label>
                        <input id="from_address" value={form.from_address} onChange={(event) => set('from_address', event.target.value)} className={input} placeholder="admin.it@borneoprima.com" maxLength="190" />
                    </div>
                    <div>
                        <label className={label} htmlFor="from_name">Nama pengirim</label>
                        <input id="from_name" value={form.from_name} onChange={(event) => set('from_name', event.target.value)} className={input} placeholder="eForm BP" maxLength="100" />
                    </div>
                </div>

                <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm dark:border-emerald-900 dark:bg-emerald-950/40">
                    <input type="checkbox" checked={form.notifications_enabled} onChange={(event) => set('notifications_enabled', event.target.checked)} className="mt-1 rounded" />
                    <span className="dark:text-emerald-200"><strong className="block text-emerald-800 dark:text-emerald-200">Kirim email untuk setiap notifikasi workflow</strong>kotak "eKill" ini mematikan pengiriman email global untuk mempercepat proses pengembian, sedangkan notifikasi aplikasi (bell) tetap berjalan normal.</span>
                </label>

                <div className="flex justify-end border-t border-slate-200 pt-4 dark:border-slate-800">
                    <button type="submit" disabled={saving} className="rounded-lg bg-gray-900 px-5 py-2 text-sm font-bold text-white disabled:opacity-60">{saving ? 'Menyimpan…' : 'Simpan Pengaturan'}</button>
                </div>
            </form>

            <section className="ui-card p-5">
                <h2 className="text-sm font-bold">Email percobaan</h2>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Kirim email template nyata ke satu alamat untuk memverifikasi SMTP sebelum dipakai produksi. Pengiriman sinkron dan hasilnya langsung tampil.</p>
                <div className="mt-3 flex flex-wrap gap-2">
                    <input aria-label="Alamat email percobaan" value={testAddress} onChange={(event) => setTestAddress(event.target.value)} placeholder="tujuan@borneoprima.com" maxLength="190" className="w-64 rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900" />
                    <button type="button" disabled={testing || !testAddress || Object.keys(errors).length > 0} onClick={sendTest} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-60">{testing ? 'Mengirim…' : 'Kirim Email Cetak'}</button>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>;
}
