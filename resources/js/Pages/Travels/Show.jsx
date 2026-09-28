import StatusBadge from '@/Components/StatusBadge';
import ApprovalActions from '@/Components/ApprovalActions';
import SummaryCard from '@/Components/SummaryCard';
import WorkflowStepper from '@/Components/WorkflowStepper';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import TravelForm from './TravelForm';
import { formatRupiah, formatTanggal, statusValue } from './helpers';

const approvalLabels = { supervisor: 'Pemeriksaan Supervisor', hod: 'Persetujuan HOD', pm: 'Persetujuan Project Manager', hrga: 'Proses HRGA' };
const activityLabels = { 'travel.created': 'Draf perjalanan dibuat', 'travel.updated': 'Data perjalanan diperbarui', 'travel.submitted': 'Perjalanan diajukan untuk approval', 'travel.cancelled': 'Pengajuan perjalanan dibatalkan', 'travel.advance_paid': 'Advance perjalanan diproses' };

export default function Show({ travel, requires_settlement, activities, availableActions, approvalActions = null, timeline = [] }) {
    const [confirm, setConfirm] = useState(null);
    const [uploading, setUploading] = useState(false);
    const fileInput = useRef(null);
    const actions = availableActions && typeof availableActions === 'object' && !Array.isArray(availableActions) ? availableActions : {};
    const status = statusValue(travel?.status);
    const canUpload = actions.can_upload === true;
    const readOnlyData = {
        employee_id: travel?.employee?.id ?? '',
        purpose: travel?.purpose ?? '',
        start_date: travel?.start_date ?? '',
        end_date: travel?.end_date ?? '',
        origin: travel?.origin ?? '',
        destination: travel?.destination ?? '',
        is_project_trip: Boolean(travel?.is_project_trip),
        items: (travel?.items ?? []).map((item) => ({
            ...item,
            metadata: item.metadata_json ?? item.metadata ?? {},
        })),
    };
    const run = (action) => router.post(route(`travels.${action}`, travel.id), {}, { preserveScroll: true, onFinish: () => setConfirm(null) });
    const upload = (event) => {
        event.preventDefault();
        const file = fileInput.current?.files?.[0];
        if (!file) return;
        const form = new FormData();
        form.append('file', file);
        form.append('document_type', 'supporting_document');
        setUploading(true);
        router.post(route('travels.attachments.store', travel.id), form, { forceFormData: true, preserveScroll: true, onFinish: () => setUploading(false), onSuccess: () => { if (fileInput.current) fileInput.current.value = ''; } });
    };
    return <AuthenticatedLayout title={travel.request_number} header={<div className="flex items-center justify-between"><h2 className="font-mono text-xl font-semibold">{travel.request_number}</h2><StatusBadge status={status} /></div>}>
        <Head title={travel.request_number} />
             <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
             <ApprovalActions approval={approvalActions} />
             <div className="flex gap-2">{actions.can_edit === true && <Link href={route('travels.edit', travel.id)} className="rounded border px-4 py-2 text-sm">Ubah</Link>}{actions.can_submit === true && <button onClick={() => setConfirm('submit')} className="rounded bg-gray-900 px-4 py-2 text-sm text-white">Ajukan</button>}{actions.can_cancel === true && <button onClick={() => setConfirm('cancel')} className="rounded border border-rose-300 px-4 py-2 text-sm text-rose-700">Batalkan</button>}{actions.can_process_advance === true && <button onClick={() => router.post(route('travels.advance', travel.id), {}, { preserveScroll: true })} className="rounded bg-emerald-700 px-4 py-2 text-sm text-white">Proses Advance</button>}</div>
             <div className="grid grid-cols-1 gap-3 sm:grid-cols-3"><SummaryCard judul="Total Advance" nilai={formatRupiah(travel.total_advance)} /><SummaryCard judul="Tujuan" nilai={travel.destination} sub={`${formatTanggal(travel.start_date)} s.d. ${formatTanggal(travel.end_date)}`} /><SummaryCard judul="Perlu Settlement" nilai={requires_settlement ? 'Ya' : 'Tidak'} /></div>
             <TravelForm data={readOnlyData} setData={() => {}} errors={{}} processing={false} meta={{ employees: [], cost_categories: [] }} travelEmployee={travel?.employee} approvalTimeline={timeline} submitLabel="Form hanya-baca" batalHref={route('travels.show', travel.id)} onSubmit={() => {}} currentStatus={status} readOnly />
             <section className="rounded-lg bg-white p-5 shadow-sm"><h3 className="font-semibold">Proses Approval</h3><ol className="mt-3 space-y-3">{timeline.map((step) => <li key={step.id} className="rounded border p-3 text-sm"><p className="font-semibold">{step.step_order}. {approvalLabels[step.step_code] ?? step.step_code}</p><p className="text-xs text-gray-500">Status: {step.status} · Ditugaskan kepada: {step.approver?.name ?? 'Belum ditentukan'}</p>{step.acted_by && <p className="text-xs">Diproses oleh: <strong>{step.acted_by.name}</strong></p>}</li>)}</ol></section>
            {travel.employee_snapshot_json && <section className="rounded-lg bg-white p-5 shadow-sm"><h3 className="font-semibold">Snapshot Karyawan & Approval</h3><p className="mt-2 text-sm">{travel.employee_snapshot_json.name} ({travel.employee_snapshot_json.employee_number})</p><p className="text-sm text-gray-500">Supervisor: {travel.employee_snapshot_json.supervisor_name ?? '-'} - HOD: {travel.employee_snapshot_json.hod_name ?? '-'}</p></section>}
            {(travel.attachments?.length > 0 || canUpload) && <section className="rounded-lg bg-white p-5 shadow-sm"><h3 className="font-semibold">Lampiran</h3>{travel.attachments?.length > 0 ? <ul className="mt-2 space-y-1">{travel.attachments.map((file) => <li key={file.id}><a className="text-sm text-indigo-600 hover:underline" href={file.download_url}>{file.original_name}</a></li>)}</ul> : <p className="mt-2 text-sm text-gray-500">Belum ada lampiran.</p>}{canUpload && <form onSubmit={upload} className="mt-4 flex flex-wrap items-end gap-3 border-t pt-4"><div><label className="block text-xs font-medium text-gray-600">Tambah lampiran</label><input ref={fileInput} required type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" className="mt-1 block text-sm" /></div><button disabled={uploading} className="rounded bg-gray-900 px-3 py-2 text-sm text-white disabled:opacity-50">{uploading ? 'Mengunggah...' : 'Unggah'}</button></form>}</section>}
             <section className="rounded-lg bg-white p-5 shadow-sm"><h3 className="font-semibold">Audit Trail</h3>{(activities ?? []).map((activity) => <p key={activity.id} className="mt-2 text-sm">{activityLabels[activity.description] ?? activity.description} <span className="text-gray-500">· {activity.actor?.name ?? 'Sistem'} · {activity.created_at}</span></p>)}</section>
        </div>
        {confirm && <div className="fixed inset-0 flex items-center justify-center bg-black/30"><div className="rounded bg-white p-5"><p>Konfirmasi tindakan?</p><div className="mt-4 flex gap-2"><button onClick={() => run(confirm)} className="rounded bg-gray-900 px-3 py-2 text-white">Ya</button><button onClick={() => setConfirm(null)} className="rounded border px-3 py-2">Batal</button></div></div></div>}
    </AuthenticatedLayout>;
}
