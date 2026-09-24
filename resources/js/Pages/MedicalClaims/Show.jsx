import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';

const money = (v) => v == null ? 'Terbatas' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);
const benefitLabel = { obat_vitamin: 'Obat / Vitamin', rawat_jalan: 'Rawat Jalan', rawat_inap: 'Rawat Inap', lensa_kacamata: 'Lensa Kacamata', frame_kacamata: 'Frame Kacamata', medical_check_up: 'Medical Check Up', kacamata: 'Kacamata', persalinan: 'Persalinan', lainnya: 'Lainnya' };
const activityLabels = { created: 'Dibuat', submitted: 'Diajukan', updated: 'Diperbarui', attachment_uploaded: 'Lampiran diunggah', cancelled: 'Dibatalkan', completed: 'Diselesaikan', payment_processing: 'Diproses untuk pembayaran' };
const activityLabel = (value = '') => activityLabels[String(value).split('.').pop()] ?? value;

export default function Show({ claim, timeline = [], activities = [], availableActions = {} }) {
    const input = useRef();
    const [documentType, setDocumentType] = useState('receipt');
    const [uploading, setUploading] = useState(false);
    const [paymentReference, setPaymentReference] = useState('');
    const [paymentDate, setPaymentDate] = useState('');
    const action = (name) => {
        if (name === 'payment' && !window.confirm('Masukkan klaim ke proses pembayaran?')) return;
        const data = name === 'complete' ? { payment_reference: paymentReference, payment_date: paymentDate } : {};
        router.post(route(`medical-claims.${name}`, claim.id), data, { preserveScroll: true });
    };
    const upload = (event) => {
        event.preventDefault();
        if (!input.current?.files?.[0]) return;
        const data = new FormData();
        data.append('file', input.current.files[0]);
        data.append('document_type', documentType);
        setUploading(true);
        router.post(route('medical-claims.attachments.store', claim.id), data, { forceFormData: true, onFinish: () => setUploading(false) });
    };
    return <AuthenticatedLayout title={claim.claim_number} header={<div className="flex items-center justify-between"><h2 className="font-mono text-xl font-semibold">{claim.claim_number}</h2><StatusBadge status={claim.status} /></div>}>
        <Head title={claim.claim_number} />
        <div className="mx-auto max-w-5xl space-y-4 px-4 py-6">
            <div className="flex flex-wrap gap-2">
                {availableActions.can_edit && <Link href={route('medical-claims.edit', claim.id)} className="rounded border px-3 py-2 text-sm">Ubah</Link>}
                {availableActions.can_submit && <button onClick={() => action('submit')} className="rounded bg-gray-900 px-3 py-2 text-sm text-white">Ajukan</button>}
                 {availableActions.can_payment_process && <button onClick={() => action('payment')} className="rounded bg-blue-700 px-3 py-2 text-sm text-white">Proses Pembayaran</button>}
                 {availableActions.can_payment_complete && <div className="flex flex-wrap gap-2"><input value={paymentReference} onChange={(e) => setPaymentReference(e.target.value)} placeholder="Referensi pembayaran" className="rounded border px-2 py-2 text-sm" /><input type="date" value={paymentDate} onChange={(e) => setPaymentDate(e.target.value)} className="rounded border px-2 py-2 text-sm" /><button onClick={() => action('complete')} className="rounded bg-emerald-700 px-3 py-2 text-sm text-white">Tandai Selesai</button></div>}
                {availableActions.can_cancel && <button onClick={() => action('cancel')} className="rounded border border-rose-300 px-3 py-2 text-sm text-rose-700">Batalkan</button>}
            </div>
            <section className="grid grid-cols-1 gap-3 sm:grid-cols-3"><div className="ui-card p-4 sm:col-span-2"><p className="text-xs text-gray-500">Manfaat terpilih</p><div className="mt-2 flex flex-wrap gap-1.5">{(claim.benefit_types?.length ? claim.benefit_types : [claim.benefit_type]).map((benefit) => <span key={benefit} className="ui-badge bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300">{benefitLabel[benefit] ?? benefit}</span>)}</div></div><div className="ui-card p-4"><p className="text-xs text-gray-500">Total klaim</p><p className="mt-1 text-lg font-bold">{money(claim.total_amount)}</p></div></section>
            {claim.items?.length > 0 && <section className="rounded bg-white p-5 shadow-sm"><h3 className="font-semibold">Rincian klaim</h3><div className="mt-3 space-y-2">{claim.items.map((item) => <div key={item.id} className="rounded border p-3 text-sm"><span className="font-medium">{item.patient_name}</span> <span className="text-gray-500">({item.relationship})</span><span className="float-right">{money(item.amount)}</span><p className="text-gray-500">{item.facility_name} · {item.treatment_date}</p></div>)}</div></section>}
             <section className="rounded bg-white p-5 shadow-sm"><h3 className="font-semibold">Lampiran</h3>{claim.attachments?.length ? <ul className="mt-3 space-y-2 text-sm">{claim.attachments.map((file) => <li key={file.id} className="flex flex-wrap items-center justify-between gap-2"><span>{file.original_name} <span className="text-gray-500">({file.document_type})</span></span>{file.download_url && <a className="text-indigo-600 hover:underline" href={file.download_url}>Unduh</a>}</li>)}</ul> : <p className="mt-3 text-sm text-gray-500">Belum ada lampiran.</p>}{availableActions.can_upload && <form onSubmit={upload} className="mt-4 flex flex-wrap gap-2 border-t pt-4"><select aria-label="Jenis dokumen" value={documentType} onChange={(e) => setDocumentType(e.target.value)} className="rounded border text-sm"><option value="receipt">Nota / kuitansi</option><option value="prescription">Resep</option><option value="doctor_letter">Surat dokter</option></select><input aria-label="File lampiran" ref={input} type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" /><button disabled={uploading} className="rounded bg-gray-900 px-3 py-2 text-sm text-white">Unggah</button></form>}</section>
             <section className="rounded bg-white p-5 shadow-sm"><h3 className="font-semibold">Timeline</h3>{timeline.map((event, index) => <div key={index} className="mt-3 border-l-2 pl-3 text-sm"><p>{event.step_code} · {event.status}</p><p className="text-gray-500">{event.actor ?? 'Belum diproses'} {event.comments ? `: ${event.comments}` : ''}</p></div>)}</section>
             <section className="rounded bg-white p-5 shadow-sm"><h3 className="font-semibold">Riwayat Aktivitas</h3>{activities.length ? activities.map((item) => <div key={item.id ?? `${item.at}-${item.event}`} className="mt-3 border-l-2 pl-3 text-sm"><p className="font-medium">{activityLabel(item.action ?? item.event)}</p><p className="text-gray-500">{item.actor ?? 'Sistem'}{item.at ? ` · ${item.at}` : ''}</p></div>) : <p className="mt-3 text-sm text-gray-500">Belum ada aktivitas.</p>}</section>
        </div>
    </AuthenticatedLayout>;
}
