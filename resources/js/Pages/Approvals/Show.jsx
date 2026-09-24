import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import StatusBadge from '@/Components/StatusBadge';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const labels = { hrga: 'HRGA', finance: 'Finance', supervisor: 'Supervisor', hod: 'HOD', project_manager: 'Project Manager', document_validation: 'Validasi Dokumen', document: 'Validasi Dokumen', approve: 'Disetujui', return: 'Dikembalikan', reject: 'Ditolak', delegate: 'Didelegasikan', annual_leave: 'Cuti Tahunan', roster_leave: 'Cuti Roster/OS', permission: 'Izin', coff: 'C-Off', travel_home: 'Perjalanan ke Rumah/Lokasi', travel_to_site: 'Perjalanan ke Site' };
const actionLabels = { approve: 'Setujui', return: 'Kembalikan', reject: 'Tolak', delegate: 'Delegasikan' };
const kindLabels = { leave: 'Cuti / Izin', travel: 'Perjalanan Dinas', settlement: 'Settlement', medical_claim: 'Medical Claim' };
const formatRupiah = (value) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;
const formatDate = (value) => value ? new Date(value).toLocaleDateString('id-ID', { dateStyle: 'medium' }) : '—';

function SummaryField({ label, value, currency = false }) {
    if (value === null || value === undefined || value === '') return null;
    return <div className="rounded-lg bg-slate-50 p-3 dark:bg-slate-900"><dt className="text-[11px] font-medium text-slate-500">{label}</dt><dd className="mt-1 text-sm font-semibold">{currency ? formatRupiah(value) : value}</dd></div>;
}

export default function Show({ approval, availableActions = {} }) {
    const { errors = {} } = usePage().props;
    const [action, setAction] = useState(null);
    const [comments, setComments] = useState('');
    const [delegateUserId, setDelegateUserId] = useState('');
    const [processing, setProcessing] = useState(false);
    const document = approval.approvable ?? {};
    const number = document.request_number ?? document.claim_number ?? `#${approval.approvable_id}`;

    const submit = (event) => {
        event.preventDefault();
        setProcessing(true);
        router.post(route('approvals.action', [approval.id, action]), { comments, delegate_user_id: delegateUserId || null }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => { setAction(null); setComments(''); setDelegateUserId(''); },
        });
    };

    return <AuthenticatedLayout title="Detail Approval" breadcrumbs={['Kendali', 'Approval', number]}>
        <Head title={`Review ${number}`} />
        <div className="mx-auto max-w-6xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
            <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-blue-600">{kindLabels[document.type] ?? 'Approval'} / Pemeriksaan</p><h1 className="mt-1 text-2xl font-extrabold tracking-tight">{number}</h1><p className="mt-1 text-sm text-slate-500">Tahap {labels[approval.step_code] ?? approval.step_code} · Role {labels[approval.approver_role] ?? approval.approver_role}</p></div><div className="flex gap-2"><Link href={route('approvals.index')} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold dark:border-slate-700">Kembali ke inbox</Link>{document.url && <Link href={document.url} className="ui-button-primary rounded-lg px-3 py-2 text-sm">Buka transaksi</Link>}</div></header>

            <section className="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_320px]">
                <article className="ui-card p-5 sm:p-6"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-[0.13em] text-blue-600">Ringkasan transaksi</p><h2 className="mt-1 text-lg font-bold">{kindLabels[document.type] ?? 'Dokumen pengajuan'}</h2></div><StatusBadge status={document.status ?? approval.status} /></div>
                    {document.employee && <div className="mt-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700"><p className="text-xs text-slate-500">Karyawan</p><p className="mt-1 font-bold">{document.employee.name}</p><p className="mt-0.5 text-xs text-slate-500">{[document.employee.employee_number, document.employee.department].filter(Boolean).join(' · ')}</p></div>}
                    <dl className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <SummaryField label="Jenis cuti" value={labels[document.leave_type] ?? document.leave_type} />
                        <SummaryField label="Total hari" value={document.total_days} />
                        <SummaryField label="Tanggal mulai" value={formatDate(document.start_date)} />
                        <SummaryField label="Tanggal selesai" value={formatDate(document.end_date)} />
                        <SummaryField label="Rute" value={document.origin || document.destination ? `${document.origin ?? '—'} → ${document.destination ?? '—'}` : null} />
                        <SummaryField label="Advance" value={document.total_advance ?? document.advance_amount} currency />
                        <SummaryField label="Realisasi" value={document.actual_amount} currency />
                        <SummaryField label="Selisih" value={document.difference_amount} currency />
                        <SummaryField label="Sumber settlement" value={document.source_reference || (document.source_type ? `${kindLabels[document.source_type] ?? document.source_type}` : null)} />
                    </dl>
                    {document.purpose && <div className="mt-4"><p className="text-xs font-semibold text-slate-500">Keperluan perjalanan</p><p className="mt-1 text-sm leading-6">{document.purpose}</p></div>}
                    {document.reason && <div className="mt-4"><p className="text-xs font-semibold text-slate-500">Alasan pengajuan</p><p className="mt-1 text-sm leading-6">{document.reason}</p></div>}
                    {document.periods?.length > 0 && <div className="mt-5"><h3 className="text-sm font-bold">Rincian periode</h3><ul className="mt-2 space-y-2">{document.periods.map((period, index) => <li key={`${period.start_date}-${index}`} className="flex flex-wrap justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-slate-900"><span className="font-semibold">{labels[period.category] ?? period.category}</span><span>{formatDate(period.start_date)} – {formatDate(period.end_date)} · {period.day_count} hari</span></li>)}</ul></div>}
                    {approval.comments && <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"><p className="text-xs font-bold uppercase tracking-wide">Catatan tahap saat ini</p><p className="mt-1">{approval.comments}</p></div>}
                </article>

                <aside className="space-y-5"><section className="ui-card p-5"><p className="text-xs font-bold uppercase tracking-[0.13em] text-blue-600">Tahap pemeriksaan</p><h2 className="mt-1 text-lg font-extrabold">{labels[approval.step_code] ?? approval.step_code}</h2><p className="mt-1 text-sm text-slate-500">Tenggat {formatDate(approval.due_at)}</p><div className="mt-5 grid gap-2">{['approve', 'return', 'reject', 'delegate'].filter((name) => availableActions[name]).map((name) => <button key={name} type="button" onClick={() => setAction(name)} className={`rounded-lg px-4 py-2.5 text-sm font-bold transition ${name === 'approve' ? 'bg-blue-600 text-white hover:bg-blue-700' : name === 'reject' ? 'border border-rose-200 text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40' : name === 'return' ? 'border border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200' : 'border border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800'}`}>{actionLabels[name]}</button>)}</div></section>
                    <section className="ui-card p-5"><h2 className="text-base font-bold">Riwayat tindakan</h2>{approval.actions?.length ? <ol className="mt-4 space-y-4">{approval.actions.map((item) => <li key={item.id} className="border-l-2 border-blue-100 pl-3 dark:border-slate-700"><p className="text-sm font-semibold">{labels[item.action] ?? 'Tindakan'}</p><p className="mt-1 text-xs text-slate-500">{item.actor ?? 'Sistem'} · {item.at ? new Date(item.at).toLocaleString('id-ID') : '—'}</p>{item.comments && <p className="mt-2 text-xs leading-5">{item.comments}</p>}</li>)}</ol> : <p className="mt-3 text-sm text-slate-500">Belum ada tindakan sebelumnya pada tahap ini.</p>}</section></aside>
            </section>
        </div>

        <Modal show={Boolean(action)} onClose={() => setAction(null)} maxWidth="md"><form onSubmit={submit} className="p-6"><p className="text-xs font-bold uppercase tracking-[0.13em] text-blue-600">Approval / Tindakan</p><h2 className="mt-1 text-lg font-bold text-slate-900 dark:text-white">{action === 'approve' ? 'Setujui pengajuan?' : action === 'return' ? 'Kembalikan untuk perbaikan' : action === 'reject' ? 'Tolak pengajuan?' : 'Delegasikan approval'}</h2>{action === 'delegate' && <div className="mt-4"><label className="text-sm font-medium" htmlFor="delegate-user">Delegasikan kepada</label><select id="delegate-user" required value={delegateUserId} onChange={(event) => setDelegateUserId(event.target.value)} className="mt-1 block w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"><option value="">Pilih pengguna</option>{(availableActions.eligibleDelegates ?? []).map((delegate) => <option key={delegate.id} value={delegate.id}>{delegate.name}{delegate.employee_name ? ` · ${delegate.employee_name}` : ''}</option>)}</select>{errors.delegate_user_id && <p className="mt-1 text-sm text-rose-600">{errors.delegate_user_id}</p>}</div>}{action !== 'approve' && <div className="mt-4"><label className="text-sm font-medium" htmlFor="approval-comments">Catatan{action === 'delegate' ? ' (opsional)' : ' wajib'}</label><textarea id="approval-comments" required={action !== 'delegate'} value={comments} onChange={(event) => setComments(event.target.value)} className="mt-1 block min-h-28 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Tuliskan catatan untuk pemohon..." />{errors.comments && <p className="mt-1 text-sm text-rose-600">{errors.comments}</p>}</div>}{errors.approval && <p className="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">{errors.approval}</p>}{action === 'approve' && <p className="mt-3 text-sm text-slate-500">Keputusan ini akan dicatat pada audit trail.</p>}<div className="mt-6 flex justify-end gap-2"><button type="button" onClick={() => setAction(null)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold dark:border-slate-700">Batal</button><button disabled={processing} className={`rounded-lg px-4 py-2 text-sm font-bold text-white disabled:opacity-60 ${action === 'reject' ? 'bg-rose-600 hover:bg-rose-700' : action === 'return' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-blue-600 hover:bg-blue-700'}`}>{processing ? 'Memproses…' : 'Konfirmasi'}</button></div></form></Modal>
    </AuthenticatedLayout>;
}
