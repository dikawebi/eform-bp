import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

const input = 'mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900';
const textarea = input + ' min-h-[96px]';
const label = 'block text-xs font-semibold text-slate-600 dark:text-slate-300';

export default function Index({
    module = null,
    settings = {},
    templates = [],
    placeholders = {},
    rows = [],
    hasPassword = false,
}) {
    const modules = usePage().props.modules ?? {};
    const moduleOptions = Object.entries(modules).map(([value, label]) => ({
        value: value === 'null' ? null : value,
        label,
    }));
    const errors = usePage().props.errors ?? {};
    const flashSuccess = usePage().props.flash?.success ?? null;
    const [currentModule, setCurrentModule] = useState(module);
    const [activeTab, setActiveTab] = useState('smtp');

    const formDefault = {
        mailer: settings.mailer ?? 'smtp',
        host: settings.host ?? '',
        port: settings.port ?? '',
        encryption: settings.encryption ?? 'tls',
        username: settings.username ?? '',
        password: '',
        from_address: settings.from_address ?? '',
        from_name: settings.from_name ?? '',
        notifications_enabled: Boolean(settings.notifications_enabled),
    };
    const [form, setForm] = useState(formDefault);
    const [testAddress, setTestAddress] = useState(settings.from_address ?? '');
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);
    const lastField = useRef(null);

    const rememberField = (event) => {
        lastField.current = event.target;
    };
    const insertPlaceholder = (token) => {
        const el = lastField.current;
        if (!el) return;
        const start = el.selectionStart ?? el.value.length;
        const end = el.selectionEnd ?? start;
        el.value = el.value.slice(0, start) + token + el.value.slice(end);
        const caret = start + token.length;
        el.setSelectionRange(caret, caret);
        el.focus();
    };

    const set = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));
    const selectModule = (value) => {
        setCurrentModule(value);
        setActiveTab('smtp');
        router.get(route('settings.mail.index', { module: value }), { preserveScroll: true });
    };
    const saveSmtp = (event) => {
        event.preventDefault();
        setSaving(true);
        router.put(route('settings.mail.update'), { ...form, module: currentModule }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };
    const saveTemplate = (event) => {
        event.preventDefault();
        setSaving(true);

        const payload = { module: currentModule, subject: {}, message: {}, subject_en: {}, message_en: {} };
        templates.forEach((t) => {
            const elSubj = document.getElementById(`subj-${t.event}`);
            const elMsg = document.getElementById(`msg-${t.event}`);
            const elSubjEn = document.getElementById(`subjen-${t.event}`);
            const elMsgEn = document.getElementById(`msgen-${t.event}`);
            payload.subject[t.event] = elSubj ? elSubj.value : '';
            payload.message[t.event] = elMsg ? elMsg.value : '';
            payload.subject_en[t.event] = elSubjEn ? elSubjEn.value : '';
            payload.message_en[t.event] = elMsgEn ? elMsgEn.value : '';
        });

        router.put(route('settings.mail.templates.update'), payload, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };
    const sendTest = () => {
        setTesting(true);
        router.post(route('settings.mail.test'), { module: currentModule, test_address: testAddress }, {
            preserveScroll: true,
            onFinish: () => setTesting(false),
        });
    };

    const sourceLabel = (source) => {
        if (source === 'env') return 'Mengikuti .env';
        if (source === 'default') return 'Mengikuti default';
        return 'Pengaturan khusus modul';
    };

    return <AuthenticatedLayout title="Email & Notifikasi per Modul" breadcrumbs={['Pengaturan', 'Email & Notifikasi']}>
        <Head title="Email & Notifikasi per Modul" />
        <div className="mx-auto max-w-5xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
            <header>
                <p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Pengaturan / Email</p>
                <h1 className="mt-1 text-2xl font-extrabold tracking-tight">Email & Notifikasi per Modul</h1>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Setiap modul dapat memiliki pengaturan SMTP dan template isi email sendiri. Modul yang tidak diatur mengikuti{' '}
                    <strong>default</strong>, dan default mengikuti file <strong>.env</strong>.
                </p>
            </header>

            {flashSuccess && <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{flashSuccess}</div>}
            {Object.keys(errors).length > 0 && <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p className="font-semibold">Periksa kembali isian berikut:</p><ul className="mt-2 list-disc space-y-1 pl-5">{Object.entries(errors).map(([key, message]) => <li key={key}>{String(message)}</li>)}</ul></div>}

            <section className="ui-card p-5 space-y-5">
                <div className="flex flex-wrap gap-1 mb-5">
                    {moduleOptions.map((option) => (
                        <button
                            key={String(option.value)}
                            type="button"
                            onClick={() => selectModule(option.value)}
                            className={`px-4 py-2 text-sm font-medium rounded-md transition border ${
                                currentModule === option.value
                                    ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                                    : 'bg-white text-slate-600 border-slate-300 hover:border-slate-400 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700 dark:hover:border-slate-600'
                            }`}
                        >
                            {option.label}
                        </button>
                    ))}
                    <p className="ml-3 mt-1 text-xs text-slate-500 dark:text-slate-400 self-center">
                        {currentModule === null ? 'Semua modul (default)' : 'Modul: ' + (modules[currentModule] ?? currentModule)}
                    </p>
                </div>

                <p className="mb-3 text-xs text-slate-500 dark:text-slate-400">
                    Sumber konfigurasi saat ini: <strong>{sourceLabel(settings.source ?? 'env')}</strong>.{' '}
                    {settings.source === 'env' && <span>Belum ada baris database untuk modul ini — mengikuti file <code className="text-blue-600 dark:text-blue-400">.env</code> sebagai fallback.</span>}
                </p>

                <div className="flex gap-1 border-b border-slate-200 dark:border-slate-800 mb-4">
                    <button
                        type="button"
                        onClick={() => setActiveTab('smtp')}
                        className={`px-4 py-2 text-sm font-medium border-b-2 transition ${
                            activeTab === 'smtp'
                                ? 'border-blue-600 text-blue-700 dark:border-blue-400 dark:text-blue-400'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                        }`}
                    >
                        SMTP
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('template')}
                        className={`px-4 py-2 text-sm font-medium border-b-2 transition ${
                            activeTab === 'template'
                                ? 'border-blue-600 text-blue-700 dark:border-blue-400 dark:text-blue-400'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                        }`}
                    >
                        Template Isi Email
                    </button>
                </div>

                {activeTab === 'smtp' && (
                    <>
                        <form onSubmit={saveSmtp} id="mail-form" className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className={label} htmlFor="mailer">Mailer</label>
                                <select id="mailer" value={form.mailer} onChange={(event) => set('mailer', event.target.value)} className={input}>
                                    <option value="smtp">SMTP (produksi)</option>
                                    <option value="log">Log (coba lokal)</option>
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
                                <input id="host" value={form.host} onChange={(event) => set('host', event.target.value)} className={input} placeholder="smtp.gmail.com" disabled={form.mailer === 'log'} maxLength="190" />
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
                            <div className="sm:col-span-2">
                                <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm dark:border-emerald-900 dark:bg-emerald-950/40">
                                    <input type="checkbox" checked={form.notifications_enabled} onChange={(event) => set('notifications_enabled', event.target.checked)} className="mt-1 rounded" />
                                    <span className="dark:text-emerald-200"><strong className="block text-emerald-800 dark:text-emerald-200">Aktifkan notifikasi email untuk modul ini</strong>kosongkan untuk mematikan pengiriman email hanya pada modul ini (notifikasi aplikasi/bell tetap berjalan).</span>
                                </label>
                            </div>
                        </form>

                        <div className="flex justify-end border-t border-slate-200 pt-4 dark:border-slate-800">
                            <button type="submit" form="mail-form" disabled={saving} className="rounded-lg bg-gray-900 px-5 py-2 text-sm font-bold text-white disabled:opacity-60">{saving ? 'Menyimpan…' : 'Simpan Pengaturan SMTP'}</button>
                        </div>
                    </>
                )}

                {activeTab === 'template' && (
                    <>
                        <p className="mb-4 text-xs text-slate-500 dark:text-slate-400">
                            Sesuaikan subjek dan isi pesan email per event notifikasi. Kosongkan semua field pada satu
                            event untuk kembali ke isi notifikasi asli dari sistem.
                        </p>
                        <div className="mb-4 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                            <p className="mb-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                Placeholder tersedia — klik untuk menyisipkan ke kolom yang sedang aktif
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {Object.entries(placeholders).map(([token, description]) => (
                                    <button
                                        key={token}
                                        type="button"
                                        onClick={() => insertPlaceholder(token)}
                                        title={description}
                                        className="rounded-md border border-blue-200 bg-blue-50 px-2 py-1 font-mono text-[11px] font-semibold text-blue-700 transition hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300 dark:hover:bg-blue-900/50"
                                    >
                                        {token}
                                    </button>
                                ))}
                            </div>
                            <p className="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                                Placeholder diisi otomatis saat email dikirim. Alias Inggris juga didukung, misalnya{' '}
                                <code className="font-mono">{'{document_number}'}</code> dan{' '}
                                <code className="font-mono">{'{step}'}</code>.
                            </p>
                        </div>
                        <form onSubmit={saveTemplate} className="space-y-4">
                            <div className="space-y-4">
                                {templates.map((t) => (
                                    <div key={t.event} className="rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-800 dark:bg-slate-900/40">
                                        <div className="mb-4 flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-bold text-slate-900 dark:text-slate-100">{t.label}</p>
                                                <p className="font-mono text-[11px] text-slate-500 dark:text-slate-400">{t.event}</p>
                                            </div>
                                            <span className={`inline-block rounded-full px-2.5 py-1 text-[10px] font-bold ${t.source === 'notifikasi asli' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'}`}>
                                                {t.source === 'notifikasi asli' ? 'Isi asli' : (t.source === 'default' ? 'Default' : 'Modul')}
                                            </span>
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label className={label} htmlFor={`subj-${t.event}`}>Subjek — Bahasa Indonesia</label>
                                                <input id={`subj-${t.event}`} defaultValue={t.subject} maxLength="255" className={input} onFocus={rememberField} />
                                            </div>
                                            <div>
                                                <label className={label} htmlFor={`subjen-${t.event}`}>Subjek — English</label>
                                                <input id={`subjen-${t.event}`} defaultValue={t.subject_en} maxLength="255" className={input} placeholder="Kosongkan bila tidak perlu" onFocus={rememberField} />
                                            </div>
                                            <div>
                                                <label className={label} htmlFor={`msg-${t.event}`}>Pesan — Bahasa Indonesia</label>
                                                <textarea id={`msg-${t.event}`} defaultValue={t.message} rows="3" className={textarea} onFocus={rememberField} />
                                            </div>
                                            <div>
                                                <label className={label} htmlFor={`msgen-${t.event}`}>Pesan — English</label>
                                                <textarea id={`msgen-${t.event}`} defaultValue={t.message_en} rows="3" className={textarea} placeholder="Kosongkan bila tidak perlu" onFocus={rememberField} />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Mengosongkan semua field pada satu event akan menghapus template kustom dan mengembalikan isi notifikasi asli.
                            </p>
                            <div className="flex justify-end border-t border-slate-200 pt-4 dark:border-slate-800">
                                <button type="submit" disabled={saving} className="rounded-lg bg-gray-900 px-5 py-2 text-sm font-bold text-white disabled:opacity-60">{saving ? 'Menyimpan…' : 'Simpan Semua Template'}</button>
                            </div>
                        </form>
                    </>
                )}
            </section>

            <section className="ui-card p-5">
                <div className="flex items-center justify-between mb-3">
                    <h2 className="text-sm font-bold">Ringkasan semua modul</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm border-collapse">
                        <thead>
                            <tr className="text-left border-b border-slate-200 dark:border-slate-800">
                                <th className="pb-2 font-semibold text-slate-600 dark:text-slate-300">Modul</th>
                                <th className="pb-2 font-semibold text-slate-600 dark:text-slate-300">Mailer</th>
                                <th className="pb-2 font-semibold text-slate-600 dark:text-slate-300">Host</th>
                                <th className="pb-2 font-semibold text-slate-600 dark:text-slate-300">Port</th>
                                <th className="pb-2 font-semibold text-slate-600 dark:text-slate-300">Dari</th>
                                <th className="pb-2 font-semibold text-slate-600 dark:text-slate-300">Notifikasi</th>
                                <th className="pb-2 font-semibold text-slate-600 dark:text-slate-300">Sumber</th>
                                <th className="pb-2 font-semibold text-slate-600 dark:text-slate-300"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                            {rows.map((row) => (
                                <tr key={row.module ?? 'default'}>
                                    <td className="py-3">{row.label}</td>
                                    <td className="py-3">{row.mailer}</td>
                                    <td className="py-3">{row.host}</td>
                                    <td className="py-3">{row.port}</td>
                                    <td className="py-3">{row.from_address}</td>
                                    <td className="py-3">{row.notifications_enabled ? 'Aktif' : 'Mati'}</td>
                                    <td className="py-3 text-xs">{sourceLabel(row.source)}</td>
                                    <td className="py-3">
                                        <button
                                            type="button"
                                            onClick={() => selectModule(row.module)}
                                            className="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900"
                                        >
                                            Pilih
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="ui-card p-5">
                <h2 className="text-sm font-bold mb-3">Email percobaan</h2>
                <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">Kirim email template nyata untuk memverifikasi SMTP modul yang dipilih.</p>
                <div className="flex flex-wrap gap-2">
                    <input aria-label="Alamat email percobaan" value={testAddress} onChange={(event) => setTestAddress(event.target.value)} placeholder="tujuan@borneoprima.com" maxLength="190" className="w-64 rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900" />
                    <button type="button" disabled={testing || !testAddress || Object.keys(errors).length > 0} onClick={sendTest} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-60">{testing ? 'Mengirim…' : 'Kirim Email Percobaan'}</button>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>;
}
