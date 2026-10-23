import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const input = 'mt-1 block w-full rounded-md border-slate-300 font-mono text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900';
const labelClass = 'block text-xs font-semibold text-slate-600 dark:text-slate-300';

function currentPeriod(format) {
    if (!format) return '';
    const now = new Date();
    const values = {
        Y: String(now.getFullYear()),
        y: String(now.getFullYear()).slice(-2),
        m: String(now.getMonth() + 1).padStart(2, '0'),
        d: String(now.getDate()).padStart(2, '0'),
    };

    return format
        .split('')
        .map((token) => values[token] ?? token)
        .join('');
}

function preview(row) {
    const separator = row.separator || '-';
    const period = currentPeriod(row.period_format);
    const number = Math.max(1, Number.parseInt(row.next_number, 10) || 1);
    const padded = String(number).padStart(Math.max(1, Number.parseInt(row.padding, 10) || 1), '0');
    const parts = [];

    if (row.prefix.trim() !== '') parts.push(row.prefix.trim());
    if (period !== '') parts.push(period);
    parts.push(padded);

    return parts.join(separator);
}

export default function Index({ sequences = [], period_formats = [] }) {
    const { props } = usePage();
    const errors = props.errors ?? {};
    const flashSuccess = props.flash?.success ?? null;
    const [rows, setRows] = useState(() => Object.fromEntries(sequences.map((item) => [
        item.module,
        {
            prefix: item.prefix ?? '',
            separator: item.separator ?? '-',
            period_format: item.period_format ?? '',
            padding: String(item.padding ?? 4),
            next_number: String(item.next_number ?? 1),
        },
    ])));
    const [saving, setSaving] = useState(false);

    const setRow = (module, key, value) => setRows((prev) => ({
        ...prev,
        [module]: { ...(prev[module] ?? {}), [key]: value },
    }));

    const save = (event) => {
        event.preventDefault();
        setSaving(true);
        router.put(route('settings.sequences.update'), { sequences: rows }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <AuthenticatedLayout title="Number Sequence" breadcrumbs={['Pengaturan', 'Number Sequence']}>
            <Head title="Number Sequence" />
            <div className="mx-auto max-w-5xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
                <header>
                    <p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Pengaturan / Penomoran</p>
                    <h1 className="mt-1 text-2xl font-extrabold tracking-tight">Number Sequence</h1>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Atur prefix, format periode, padding, dan nomor berikutnya untuk setiap jenis dokumen. Nomor dibuat saat draft dibuat, bukan saat submit.
                    </p>
                </header>

                {flashSuccess && <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{flashSuccess}</div>}
                {Object.keys(errors).length > 0 && <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p className="font-semibold">Periksa kembali isian berikut:</p><ul className="mt-2 list-disc space-y-1 pl-5">{Object.entries(errors).map(([key, message]) => <li key={key}>{String(message)}</li>)}</ul></div>}

                <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100">
                    Format: <code className="font-mono">prefix + pemisah + periode + pemisah + nomor</code>. Contoh: <code className="font-mono">CUTI-202610-0001</code>.
                    Sequence otomatis kembali ke <strong>1</strong> ketika periode berubah. Kosongkan format periode untuk penomoran tanpa reset.
                </div>

                <form onSubmit={save} className="space-y-4">
                    <datalist id="period-formats">
                        {period_formats.map((item) => <option key={item.value || 'none'} value={item.value}>{item.label}</option>)}
                    </datalist>

                    <div className="grid gap-4 md:grid-cols-2">
                        {sequences.map((item) => {
                            const row = rows[item.module] ?? {};
                            return (
                                <section key={item.module} className="ui-card space-y-4 p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <h2 className="text-sm font-bold">{item.label}</h2>
                                            <p className="mt-0.5 font-mono text-[11px] text-slate-400">{item.module}</p>
                                        </div>
                                        <div className="rounded-lg bg-slate-100 px-3 py-1.5 text-right dark:bg-slate-800">
                                            <p className="text-[10px] font-bold uppercase tracking-wide text-slate-500">Berikutnya</p>
                                            <code className="text-xs font-bold text-slate-800 dark:text-slate-100">{preview(row)}</code>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className={labelClass} htmlFor={`prefix-${item.module}`}>Prefix</label>
                                            <input id={`prefix-${item.module}`} value={row.prefix ?? ''} onChange={(event) => setRow(item.module, 'prefix', event.target.value)} className={input} maxLength="20" required />
                                        </div>
                                        <div>
                                            <label className={labelClass} htmlFor={`separator-${item.module}`}>Pemisah</label>
                                            <input id={`separator-${item.module}`} value={row.separator ?? '-'} onChange={(event) => setRow(item.module, 'separator', event.target.value)} className={input} maxLength="3" required />
                                        </div>
                                        <div>
                                            <label className={labelClass} htmlFor={`period-${item.module}`}>Format periode</label>
                                            <input id={`period-${item.module}`} list="period-formats" value={row.period_format ?? ''} onChange={(event) => setRow(item.module, 'period_format', event.target.value)} className={input} placeholder="Ym" maxLength="10" />
                                            <p className="mt-1 text-[11px] text-slate-400">Kosongkan untuk tanpa reset.</p>
                                        </div>
                                        <div>
                                            <label className={labelClass} htmlFor={`padding-${item.module}`}>Padding</label>
                                            <input id={`padding-${item.module}`} type="number" min="1" max="8" value={row.padding ?? 4} onChange={(event) => setRow(item.module, 'padding', event.target.value)} className={input} required />
                                        </div>
                                        <div className="col-span-2">
                                            <label className={labelClass} htmlFor={`next-${item.module}`}>Nomor berikutnya</label>
                                            <input id={`next-${item.module}`} type="number" min="1" max="999999999" value={row.next_number ?? 1} onChange={(event) => setRow(item.module, 'next_number', event.target.value)} className={input} required />
                                            <p className="mt-1 text-[11px] text-slate-400">Gunakan nomor yang belum pernah dipakai pada periode berjalan.</p>
                                        </div>
                                    </div>

                                    <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400">
                                        Periode aktif: <strong>{currentPeriod(row.period_format) || 'tanpa reset'}</strong>
                                    </div>
                                </section>
                            );
                        })}
                    </div>

                    <div className="sticky bottom-4 flex justify-end">
                        <button type="submit" disabled={saving} className="rounded-lg bg-gray-900 px-6 py-2.5 text-sm font-bold text-white shadow-lg disabled:opacity-60">
                            {saving ? 'Menyimpan...' : 'Simpan Number Sequence'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
