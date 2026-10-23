import InputError from '@/Components/InputError';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const blankRow = () => ({ label: '', value: '' });

const formatDate = (value) => value
    ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
    : '—';

export default function ItSpecificationEditor({ specification = null, templates = [], canEdit = false, saveUrl = null }) {
    const { errors = {} } = usePage().props;
    const [templateId, setTemplateId] = useState(specification?.template_id ? String(specification.template_id) : '');
    const [rows, setRows] = useState(
        Array.isArray(specification?.specs) && specification.specs.length > 0
            ? specification.specs.map((row) => ({ label: row.label ?? '', value: row.value ?? '' }))
            : [blankRow()],
    );
    const [notes, setNotes] = useState(specification?.notes ?? '');
    const [processing, setProcessing] = useState(false);
    const isLocked = Boolean(specification?.is_locked);

    const applyTemplate = (value) => {
        setTemplateId(value);
        const template = templates.find((item) => String(item.id) === String(value));
        if (template) {
            setRows((template.specs ?? []).map((row) => ({ label: row.label ?? '', value: row.value ?? '' })));
        }
    };
    const setRow = (index, key, value) => {
        setRows((current) => current.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row));
    };
    const addRow = () => setRows((current) => [...current, blankRow()]);
    const removeRow = (index) => setRows((current) => current.length > 1 ? current.filter((_, rowIndex) => rowIndex !== index) : current);
    const moveRow = (index, direction) => {
        setRows((current) => {
            const target = index + direction;
            if (target < 0 || target >= current.length) return current;
            const next = [...current];
            [next[index], next[target]] = [next[target], next[index]];
            return next;
        });
    };

    const save = (event) => {
        event.preventDefault();
        if (!saveUrl) return;
        setProcessing(true);
        router.post(saveUrl, {
            template_id: templateId ? Number(templateId) : null,
            specs: rows,
            notes,
        }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return <section className="ui-card p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p className="text-xs font-bold uppercase tracking-[0.13em] text-blue-600">Penetapan IT</p>
                <h2 className="mt-1 text-lg font-bold">Spesifikasi Teknis</h2>
                <p className="mt-1 text-sm text-slate-500">Spesifikasi ini ditetapkan oleh IT dan menjadi dasar proses pengadaan.</p>
            </div>
            <span className={`rounded-full px-2.5 py-1 text-[11px] font-bold ${isLocked ? 'bg-emerald-100 text-emerald-700' : specification ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'}`}>
                {isLocked ? 'Final' : specification ? 'Draft' : 'Belum diisi'}
            </span>
        </div>

        {canEdit && <form onSubmit={save} className="mt-5 space-y-4">
            {isLocked && <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Spesifikasi sudah final. Menyimpan perubahan akan membuka kunci dan wajib disetujui ulang pada tahap IT.</div>}
            <div>
                <label className="block text-sm font-semibold" htmlFor="it-spec-template">Template spesifikasi</label>
                <select id="it-spec-template" value={templateId} onChange={(event) => applyTemplate(event.target.value)} className="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Isi manual</option>
                    {templates.map((template) => <option key={template.id} value={template.id}>{template.name}</option>)}
                </select>
                <InputError message={errors.template_id} />
                <p className="mt-1 text-xs text-slate-500">Template hanya membantu mengisi baris. Nilai yang disimpan tetap menjadi snapshot untuk request ini.</p>
            </div>

            <div className="overflow-hidden rounded-lg border border-slate-200">
                <table className="w-full text-left text-sm">
                    <thead className="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                        <tr><th className="px-3 py-2">Komponen</th><th className="px-3 py-2">Spesifikasi</th><th className="w-32 px-3 py-2 text-right">Aksi</th></tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.map((row, index) => <tr key={index}>
                            <td className="px-3 py-2 align-top"><input aria-label={`Komponen ${index + 1}`} value={row.label} onChange={(event) => setRow(index, 'label', event.target.value)} maxLength="100" className="block w-full rounded-md border-slate-300 text-sm" placeholder="CPU / RAM / Garansi" /><InputError message={errors[`specs.${index}.label`]} /></td>
                            <td className="px-3 py-2 align-top"><input aria-label={`Spesifikasi ${index + 1}`} value={row.value} onChange={(event) => setRow(index, 'value', event.target.value)} maxLength="500" className="block w-full rounded-md border-slate-300 text-sm" placeholder="Contoh: 32 GB" /><InputError message={errors[`specs.${index}.value`]} /></td>
                            <td className="px-3 py-2 align-top"><div className="flex justify-end gap-1">
                                <button type="button" onClick={() => moveRow(index, -1)} disabled={index === 0} className="rounded border border-slate-200 px-2 py-1 text-xs disabled:opacity-40" aria-label="Naikkan baris">↑</button>
                                <button type="button" onClick={() => moveRow(index, 1)} disabled={index === rows.length - 1} className="rounded border border-slate-200 px-2 py-1 text-xs disabled:opacity-40" aria-label="Turunkan baris">↓</button>
                                <button type="button" onClick={() => removeRow(index)} disabled={rows.length === 1} className="rounded border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 disabled:opacity-40">Hapus</button>
                            </div></td>
                        </tr>)}
                    </tbody>
                </table>
            </div>
            <button type="button" onClick={addRow} className="rounded-lg border border-blue-200 px-3 py-2 text-sm font-semibold text-blue-700">+ Tambah baris</button>
            <InputError message={errors.specs} />

            <div>
                <label className="block text-sm font-semibold" htmlFor="it-spec-notes">Catatan teknis</label>
                <textarea id="it-spec-notes" value={notes} onChange={(event) => setNotes(event.target.value)} rows="3" maxLength="2000" className="mt-1 block w-full rounded-lg border-slate-300 text-sm" placeholder="Catatan tambahan untuk approver atau proses pengadaan." />
                <InputError message={errors.notes} />
            </div>

            <InputError message={errors.specification} />
            <div className="flex flex-wrap items-center gap-2">
                <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{processing ? 'Menyimpan...' : 'Simpan Spesifikasi'}</button>
                {specification && <span className="text-xs text-slate-500">Terakhir disimpan oleh {specification.specified_by ?? '—'} · {formatDate(specification.specified_at)}</span>}
            </div>
        </form>}

        {!canEdit && <div className="mt-4">
            {specification ? <>
                <dl className="divide-y divide-slate-100 rounded-lg border border-slate-200">
                    {(specification.specs ?? []).map((row, index) => <div key={`${row.label}-${index}`} className="grid gap-1 px-3 py-2 text-sm sm:grid-cols-[180px_1fr]"><dt className="font-semibold text-slate-600">{row.label}</dt><dd>{row.value}</dd></div>)}
                </dl>
                {specification.notes && <p className="mt-3 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{specification.notes}</p>}
                <p className="mt-3 text-xs text-slate-500">Ditetapkan oleh {specification.locked_by ?? specification.specified_by ?? '—'} · {formatDate(specification.locked_at ?? specification.specified_at)}</p>
            </> : <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">Spesifikasi teknis belum diisi oleh IT.</p>}
        </div>}
    </section>;
}
