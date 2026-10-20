import StatusBadge from '@/Components/StatusBadge';
import ApprovalActions from '@/Components/ApprovalActions';
import ITRequestForm from './ITRequestForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

const approvalLabels = { hod: 'Diketahui HOD', it: 'Tinjauan IT', pm_gm: 'Disetujui PM/GM', coo_ceo: 'Disetujui COO/CEO' };
const approvalStatuses = { pending: 'Menunggu tindakan', approved: 'Disetujui', rejected: 'Ditolak', returned: 'Dikembalikan', skipped: 'Dilewati', cancelled: 'Dibatalkan' };
const activityLabels = { created: 'Dibuat', submitted: 'Diajukan', updated: 'Diperbarui', attachment_uploaded: 'Lampiran diunggah', cancelled: 'Dibatalkan' };
const activityLabel = (value = '') => activityLabels[String(value).split('.').pop()] ?? value;

export default function Show({ request, meta = {}, timeline = [], activities = [], availableActions = {}, approvalActions = null }) {
    const { props } = usePage();
    const errors = props.errors ?? {};
    const input = useRef();
    const [documentType, setDocumentType] = useState('justification');
    const [uploading, setUploading] = useState(false);
    const [prNumber, setPrNumber] = useState(request.pr_msd_number ?? '');
    const [savingPr, setSavingPr] = useState(false);
    const action = (name) => {
        router.post(route(`it-requests.${name}`, request.id), {}, { preserveScroll: true });
    };
    const savePr = (event) => {
        event.preventDefault();
        setSavingPr(true);
        router.post(route('it-requests.pr-number.update', request.id), { pr_msd_number: prNumber }, { preserveScroll: true, onFinish: () => setSavingPr(false) });
    };
    const upload = (event) => {
        event.preventDefault();
        if (!input.current?.files?.[0]) return;
        const data = new FormData();
        data.append('file', input.current.files[0]);
        data.append('document_type', documentType);
        setUploading(true);
        router.post(route('it-requests.attachments.store', request.id), data, { forceFormData: true, onFinish: () => setUploading(false) });
    };
    return <AuthenticatedLayout title={request.request_number} header={<div className="flex items-center justify-between"><h2 className="font-mono text-xl font-semibold">{request.request_number}</h2><StatusBadge status={request.status} /></div>}>
        <Head title={request.request_number} />
         <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
             <ApprovalActions approval={approvalActions} />
             {Object.keys(errors).length > 0 && <div role="alert" className="rounded border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p className="font-semibold">Pengajuan belum dapat diproses.</p><ul className="mt-2 list-disc space-y-1 pl-5">{Object.entries(errors).map(([key, message]) => <li key={key}>{String(message)}</li>)}</ul></div>}
             <div className="flex flex-wrap gap-2">
                {availableActions.can_edit && <Link href={route('it-requests.edit', request.id)} className="rounded border px-3 py-2 text-sm">Ubah</Link>}
                {availableActions.can_submit && <button onClick={() => action('submit')} className="rounded bg-gray-900 px-3 py-2 text-sm text-white">Ajukan</button>}
                {availableActions.can_cancel && <button onClick={() => { const inProcess = ['submitted', 'in_review'].includes(request.status); if (!inProcess || window.confirm('Request sedang dalam proses. Membatalkan akan menghentikan approval dan memberi tahu approver. Lanjutkan?')) action('cancel'); }} className="rounded bg-rose-600 px-3 py-2 text-sm font-bold text-white shadow-sm hover:bg-rose-700">Batalkan</button>}
                {availableActions.can_print && <Link href={route('it-requests.print', { it_request: request.id, format: 'form' })} className="rounded bg-indigo-700 px-3 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Save to PDF</Link>}
             </div>
             <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Persetujuan ini bukan persetujuan pembelian. Setelah mendapat persetujuan akhir, request diteruskan kepada IT untuk proses lanjutan.</div>
             {availableActions.can_fill_pr && <section className="rounded bg-white p-5 shadow-sm">
                 <h3 className="font-semibold">Nomor PR MSD</h3>
                 {!request.pr_msd_number && <p className="mt-1 rounded border border-amber-200 bg-amber-50 p-2 text-sm text-amber-900"><strong>Pengingat:</strong> request sudah selesai. Segera buat PR di MSD, lalu isi nomor PR-nya di bawah.</p>}
                 {request.pr_msd_number && <p className="mt-2 text-sm">Nomor PR saat ini: <strong className="font-mono">{request.pr_msd_number}</strong></p>}
                 <form onSubmit={savePr} className="mt-3 flex flex-wrap gap-2">
                     <input aria-label="Nomor PR MSD" value={prNumber} onChange={(event) => setPrNumber(event.target.value)} placeholder="Nomor PR MSD" maxLength="100" className="rounded border px-3 py-2 text-sm" />
                     <button disabled={savingPr} className="rounded bg-gray-900 px-3 py-2 text-sm text-white">{savingPr ? 'Menyimpan…' : (request.pr_msd_number ? 'Perbarui Nomor PR' : 'Simpan Nomor PR')}</button>
                 </form>
             </section>}
             <ITRequestForm request={request} meta={{ ...meta, software_standard: request.software_standard ?? [] }} readOnly embedded approvalTimeline={timeline} />
             <section className="rounded bg-white p-5 shadow-sm"><h3 className="font-semibold">Lampiran</h3>{request.attachments?.length ? <ul className="mt-3 space-y-2 text-sm">{request.attachments.map((file) => <li key={file.id} className="flex flex-wrap items-center justify-between gap-2"><span>{file.original_name} <span className="text-gray-500">({file.document_type})</span></span>{file.download_url && <a className="text-indigo-600 hover:underline" href={file.download_url}>Unduh</a>}</li>)}</ul> : <p className="mt-3 text-sm text-gray-500">Belum ada lampiran.</p>}{availableActions.can_upload && <form onSubmit={upload} className="mt-4 flex flex-wrap gap-2 border-t pt-4"><select aria-label="Jenis dokumen" value={documentType} onChange={(e) => setDocumentType(e.target.value)} className="rounded border text-sm"><option value="justification">Justifikasi kebutuhan</option><option value="quotation">Penawaran / quotation</option><option value="supporting_document">Dokumen pendukung</option></select><input aria-label="File lampiran" ref={input} type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" /><button disabled={uploading} className="rounded bg-gray-900 px-3 py-2 text-sm text-white">Unggah</button></form>}</section>
             <section className="rounded bg-white p-5 shadow-sm"><h3 className="font-semibold">Proses Approval</h3>{timeline.map((event, index) => <div key={index} className="mt-3 rounded border p-3 text-sm"><p className="font-semibold">{approvalLabels[event.step_code] ?? event.step_code}</p><p className="text-gray-500">Status: {approvalStatuses[event.status] ?? event.status} · Ditugaskan kepada: {event.actor ?? 'Belum ditentukan'}</p>{event.acted_by && <p>Diproses oleh: <strong>{event.acted_by}</strong></p>}{event.comments && <p className="mt-1 text-gray-500">Catatan: {event.comments}</p>}</div>)}</section>
             <section className="rounded bg-white p-5 shadow-sm"><h3 className="font-semibold">Riwayat Aktivitas</h3>{activities.length ? activities.map((item) => <div key={item.id ?? `${item.at}-${item.event}`} className="mt-3 border-l-2 pl-3 text-sm"><p className="font-medium">{activityLabel(item.action ?? item.event)}</p><p className="text-gray-500">{item.actor ?? 'Sistem'}{item.at ? ` · ${item.at}` : ''}</p></div>) : <p className="mt-3 text-sm text-gray-500">Belum ada aktivitas.</p>}</section>
        </div>
    </AuthenticatedLayout>;
}
