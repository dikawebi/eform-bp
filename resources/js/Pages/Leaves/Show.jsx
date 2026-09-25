import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import LeaveForm from './LeaveForm';
import { formatRupiah, formatTanggalWaktu, isStatusCancellable, isStatusEditable, isStatusSubmittable, statusValue } from './helpers';

const approvalLabels = { supervisor: 'Pemeriksaan Supervisor', hod: 'Persetujuan HOD', pm: 'Persetujuan Project Manager', hrga: 'Proses HRGA' };
const activityLabel = (value) => ({ 'leave.created': 'Draf pengajuan dibuat', 'leave.updated': 'Pengajuan diperbarui', 'leave.submitted': 'Pengajuan dikirim', 'leave.cancelled': 'Pengajuan dibatalkan' }[value] ?? value ?? 'Aktivitas');

export default function Show({ leave, requires_settlement, activities = [], canEdit, availableActions, timeline = [] }) {
    const [confirm, setConfirm] = useState(null);
    const [busy, setBusy] = useState(false);
    const fileInput = useRef(null);
    const status = statusValue(leave?.status);
    const actions = Array.isArray(availableActions) ? Object.fromEntries(availableActions.map((item) => [item, true])) : (availableActions ?? {});
    const editable = typeof canEdit === 'boolean' ? canEdit : actions.can_edit === true || (isStatusEditable(status) && actions.edit !== false);
    const submitAllowed = actions.can_submit === true || (actions.can_submit === undefined && isStatusSubmittable(status));
    const cancelAllowed = actions.can_cancel === true || (actions.can_cancel === undefined && isStatusCancellable(status));
    const data = { employee_id: leave?.employee?.id ?? leave?.employee_id ?? '', leave_type: leave?.leave_type ?? '', reason: leave?.reason ?? '', last_working_date: leave?.last_working_date ?? '', onsite_date: leave?.onsite_date ?? '', periods: leave?.periods ?? leave?.period ?? [], cost_items: leave?.costItems ?? leave?.cost_items ?? [] };
    const postAction = (name) => { setBusy(true); router.post(route(`leaves.${name}`, leave.id), {}, { preserveScroll: true, onFinish: () => { setBusy(false); setConfirm(null); } }); };
    const upload = (event) => { event.preventDefault(); const file = fileInput.current?.files?.[0]; if (!file) return; const form = new FormData(); form.append('file', file); form.append('document_type', 'supporting_document'); router.post(route('leaves.attachments.store', leave.id), form, { forceFormData: true, preserveScroll: true, onSuccess: () => { fileInput.current.value = ''; } }); };
    return <AuthenticatedLayout title={`Cuti ${leave?.request_number ?? ''}`} breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Cuti/Izin', href: '/leaves' }, { label: leave?.request_number ?? 'Detail' }]} header={<div className="flex flex-wrap items-center justify-between gap-3"><h2 className="text-xl font-semibold">{leave?.request_number}</h2><div className="flex items-center gap-2"><StatusBadge status={status} />{editable && <Link href={route('leaves.edit', leave.id)} className="rounded-md border px-4 py-2 text-sm">Ubah</Link>}{submitAllowed && <button type="button" onClick={() => setConfirm('submit')} className="rounded-md bg-gray-900 px-4 py-2 text-sm text-white">Ajukan</button>}{cancelAllowed && <button type="button" onClick={() => setConfirm('cancel')} className="rounded-md border border-rose-300 px-4 py-2 text-sm text-rose-700">Batalkan</button>}</div></div>}>
        <Head title={leave?.request_number ?? 'Detail Cuti'} />
         <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
            {(status === 'returned' || status === 'rejected') && <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Pengajuan {status === 'returned' ? 'dikembalikan untuk diperbaiki.' : 'ditolak.'} Alasan tercatat pada timeline approval dan audit trail.</div>}
            <LeaveForm data={data} setData={() => {}} errors={{}} processing={false} meta={{ employees: leave?.employee ? [leave.employee] : [], period_categories: [], cost_categories: [] }} karyawanFallback={leave?.employee} currentStatus={status} approvalTimeline={timeline} readOnly submitLabel="Form hanya-baca" batalHref={route('leaves.show', leave.id)} onSubmit={() => {}} />
            {(leave?.attachments?.length > 0 || actions.can_upload) && <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100"><h3 className="font-semibold">Lampiran</h3><ul className="mt-3 space-y-1 text-sm">{(leave.attachments ?? []).map((file) => <li key={file.id}><a className="text-indigo-600 hover:underline" href={file.download_url}>{file.original_name}</a></li>)}</ul>{actions.can_upload && <form onSubmit={upload} className="mt-4 flex flex-wrap items-end gap-3 border-t pt-4"><input ref={fileInput} required type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" /><button className="rounded bg-gray-900 px-3 py-2 text-sm text-white">Unggah</button></form>}</section>}
            <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100"><h3 className="font-semibold">Proses Approval</h3>{timeline.length ? <ol className="mt-3 space-y-3">{timeline.map((step) => <li key={step.id} className="rounded border p-3 text-sm"><p className="font-semibold">{step.step_order}. {approvalLabels[step.step_code] ?? step.step_code}</p><p className="text-xs text-gray-500">Status: {step.status} · PIC: {step.approver?.name ?? 'Belum ditentukan'}</p>{step.comments && <p className="mt-1 text-xs">Catatan: {step.comments}</p>}</li>)}</ol> : <EmptyState title="Belum ada approval" description="Approval akan muncul setelah pengajuan dikirim." />}</section>
            <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100"><h3 className="font-semibold">Riwayat &amp; Audit Trail</h3>{activities.length ? <ol className="mt-3 space-y-3 border-l-2 border-gray-200 pl-4">{activities.map((item) => <li key={item.id} className="text-sm"><p className="font-medium">{activityLabel(item.description ?? item.action)}</p><p className="text-xs text-gray-500">{item.causer?.name ?? item.actor?.name ?? 'Sistem'} · {formatTanggalWaktu(item.created_at ?? item.at)}</p></li>)}</ol> : <EmptyState title="Belum ada riwayat" description="Perubahan status dicatat di sini." />}</section>
            <Link href={route('leaves.index')} className="inline-flex rounded-md border px-4 py-2 text-sm">Kembali ke Daftar</Link>
        </div>
        <Modal show={Boolean(confirm)} onClose={() => setConfirm(null)} maxWidth="md"><div className="p-6"><h3 className="font-semibold">{confirm === 'submit' ? 'Ajukan pengajuan?' : 'Batalkan pengajuan?'}</h3><p className="mt-2 text-sm text-gray-600">Keputusan akhir diperiksa dan dijalankan server sesuai status serta hak akses Anda.</p><div className="mt-5 flex justify-end gap-2"><button type="button" onClick={() => setConfirm(null)} className="rounded border px-4 py-2 text-sm">Kembali</button><button type="button" disabled={busy} onClick={() => postAction(confirm)} className="rounded bg-gray-900 px-4 py-2 text-sm text-white">Konfirmasi</button></div></div></Modal>
    </AuthenticatedLayout>;
}
