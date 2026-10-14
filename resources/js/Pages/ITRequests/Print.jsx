import { Head, Link } from '@inertiajs/react';
import ITRequestForm from './ITRequestForm';

const fmtDate = (value) => value ? new Date(value).toLocaleDateString('id-ID', { dateStyle: 'long' }) : '—';
const stepLabels = { hod: 'HOD', it: 'IT Service Desk', pm_gm: 'PM/GM Site', coo_ceo: 'COO/CEO' };
const approvalStatuses = { pending: 'Menunggu tindakan', approved: 'Disetujui', rejected: 'Ditolak', returned: 'Dikembalikan', skipped: 'Dilewati', cancelled: 'Dibatalkan' };
const formats = [['memo', 'Memo Penerusan'], ['items', 'Daftar Item'], ['form', 'Formulir Lengkap']];

const deviceLabel = (request, meta) => request.device_type ? (meta?.option_labels?.[request.device_type] ?? request.device_type) : '—';
const accessoryLabel = (meta, code) => meta?.option_labels?.[code] ?? code;
const recipientName = (request) => request.employee?.name ?? request.recipient_name ?? '—';

function itemRows(request, meta) {
    const rows = [];
    if (request.device_type) rows.push({ kategori: 'Perangkat utama', uraian: deviceLabel(request, meta), jumlah: 1, keterangan: request.request_type === 'replacement' ? 'Penggantian' : 'Barang baru' });
    (request.accessories ?? []).forEach((code) => rows.push({ kategori: 'Accessory', uraian: accessoryLabel(meta, code), jumlah: 1, keterangan: '' }));
    (request.software_optional ?? []).forEach((item) => rows.push({ kategori: 'Software', uraian: item.name, jumlah: 1, keterangan: item.license === 'existing' ? 'Lisensi tersedia' : 'Lisensi baru' }));
    return rows;
}

function Kop({ company }) {
    return <div className="border-b-4 border-double border-slate-900 pb-4 text-center">
        <p className="text-xl font-extrabold tracking-wide">PT. {(company?.name ?? 'BORNEO PRIMA').toUpperCase()}</p>
        <p className="mt-1 text-sm font-semibold text-slate-800">{[company?.city, company?.regency, company?.province].filter(Boolean).join(' · ')}</p>
    </div>;
}

function SignatureRow({ request, timeline }) {
    const steps = timeline ?? [];
    if (!steps.length) return null;
    const byCode = (code) => steps.find((item) => item.step_code === code);
    const hod = byCode('hod'), pm = byCode('pm_gm'), it = byCode('it'), coo = byCode('coo_ceo');
    const box = (title, name, date, cap) => <div className="w-1/3">
        <p className="font-bold">{title}:</p>
        <div className="h-16" />
        {cap && <p className="inline-block -rotate-3 rounded border-2 border-emerald-600 px-2 py-0.5 text-[11px] font-extrabold uppercase text-emerald-700">✓ {cap}</p>}
        <p className="mt-1 font-semibold">Name: <span className="font-bold">{name ?? '..............................'}</span></p>
        <p className="font-semibold">Date: <span className="font-bold">{date ?? '.... / .... / ........'}</span></p>
    </div>;
    const extra = [it && it.status === 'approved' ? box('Reviewed by IT', it.acted_by ?? it.actor ?? null, it.at ? fmtDate(it.at) : null, 'Approved via eForm') : null,
        coo && coo.status === 'approved' ? box('Approved by COO/CEO', coo.acted_by ?? coo.actor ?? null, coo.at ? fmtDate(coo.at) : null, 'Approved via eForm') : null].filter(Boolean);
    return <div className="mt-8 text-sm">
        <div className="flex justify-between gap-8">
            {box('Requested by', request.employee?.name ?? request.recipient_name ?? null, request.submitted_at ? fmtDate(request.submitted_at) : null, 'Requested via eForm')}
            {hod?.status === 'approved' && box('Acknowledged by HOD', hod.acted_by ?? hod.actor ?? null, hod.at ? fmtDate(hod.at) : null, 'Approved via eForm')}
            {pm?.status === 'approved' && box('Approved by PM/GM', pm.acted_by ?? pm.actor ?? null, pm.at ? fmtDate(pm.at) : null, 'Approved via eForm')}
        </div>
        {extra.length > 0 && <div className="mt-6 flex justify-between gap-8">{extra}{extra.length === 1 && <div className="w-1/3" />}{extra.length === 1 && <div className="w-1/3" />}</div>}
    </div>;
}

function ApprovalMatrix({ request, timeline }) {
    return <SignatureRow request={request} timeline={timeline} />;
}

function SignBlock({ leftTitle, leftName, rightTitle, rightName = '' }) {
    const cell = (title, name) => <div className="w-56 text-center text-sm"><p>{title}</p><div className="h-20" /><p className="font-bold underline">{name || '( ...................................... )'}</p><p className="mt-1">Tanggal: .... / .... / ........</p></div>;
    return <div className="mt-10 flex justify-between gap-4">{cell(leftTitle, leftName)}{cell(rightTitle, rightName)}</div>;
}

function MemoDoc({ request, meta, timeline, company }) {
    const rows = itemRows(request, meta);
    const itReviewer = (timeline ?? []).find((item) => item.step_code === 'it');
    return <div>
        <Kop company={company} />
        <h1 className="mt-4 text-center text-base font-extrabold uppercase underline">Memo Penerusan Permintaan Pengadaan</h1>
        <div className="mt-4 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
            <span>No.</span><span>: {request.request_number}</span>
            <span>Tanggal</span><span>: {fmtDate(request.completed_at)}</span>
            <span>Kepada</span><span>: Commercial Department</span>
            <span>Dari</span><span>: IT Department{(itReviewer?.acted_by ?? itReviewer?.actor) ? ` (${itReviewer.acted_by ?? itReviewer.actor})` : ''}</span>
            <span>Perihal</span><span>: Penerusan permintaan perangkat & software sebagai dasar PR</span>
        </div>
        <div className="mt-4 space-y-2 text-sm leading-7">
            <p>Menindaklanjuti request <strong>{request.request_number}</strong> yang telah selesai melalui seluruh tahap persetujuan, bersama ini diteruskan kepada Commercial untuk diproses sebagai Purchase Request (PR) dengan rincian penerima dan item sebagai berikut.</p>
        </div>
        <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
            <div className="rounded border border-slate-600 p-3"><p className="font-bold">Penerima</p><p className="mt-1 font-semibold">{recipientName(request)}</p><p className="mt-1">{[request.employee?.employee_number, request.employee?.department].filter(Boolean).join(' · ')}</p></div>
            <div className="rounded border border-slate-600 p-3"><p className="font-bold">Site / Cost code</p><p className="mt-1 font-semibold">{[request.recipient_site, request.recipient_cost_code].filter(Boolean).join(' · ') || '—'}</p><p className="mt-1">Dibutuhkan: {request.needed_date ? fmtDate(request.needed_date) : '—'}</p></div>
        </div>
        <table className="mt-4 w-full border-collapse text-xs">
            <thead><tr className="bg-slate-200"><th className="border border-slate-600 px-2 py-1.5 text-left">No</th><th className="border border-slate-600 px-2 py-1.5 text-left">Kategori</th><th className="border border-slate-600 px-2 py-1.5 text-left">Uraian</th><th className="border border-slate-600 px-2 py-1.5 text-center">Jml</th><th className="border border-slate-600 px-2 py-1.5 text-left">Keterangan</th></tr></thead>
            <tbody>{rows.length ? rows.map((row, index) => <tr key={index}><td className="border border-slate-600 px-2 py-1.5 text-center">{index + 1}</td><td className="border border-slate-600 px-2 py-1.5">{row.kategori}</td><td className="border border-slate-600 px-2 py-1.5">{row.uraian}</td><td className="border border-slate-600 px-2 py-1.5 text-center">{row.jumlah}</td><td className="border border-slate-600 px-2 py-1.5">{row.keterangan || '—'}</td></tr>) : <tr><td colSpan="5" className="border border-slate-600 px-2 py-3 text-center">Tidak ada item perangkat.</td></tr>}</tbody>
        </table>
        <p className="mt-3 text-sm leading-7"><strong>Catatan:</strong> Spesifikasi teknis ditentukan internal oleh IT. Persetujuan ini bukan persetujuan pembelian.</p>
        {request.description && <p className="mt-1 text-sm leading-7"><strong>Deskripsi:</strong> {request.description}</p>}
        {request.purpose && <p className="mt-1 text-sm leading-7"><strong>Tujuan:</strong> {request.purpose}</p>}
        <SignatureRow request={request} timeline={timeline} />
        <SignBlock leftTitle="Diserahkan oleh, IT" leftName={itReviewer?.acted_by ?? itReviewer?.actor ?? ''} rightTitle="Diterima oleh, Commercial" />
    </div>;
}

function ItemsDoc({ request, meta, timeline }) {
    const rows = itemRows(request, meta);
    return <div>
        <h1 className="text-center text-base font-extrabold uppercase">Lampiran Daftar Item — Dasar PR</h1>
        <p className="mt-1 text-center text-sm font-semibold">Ref: {request.request_number} · Penerima: {recipientName(request)} · {[request.recipient_site, request.recipient_cost_code].filter(Boolean).join(' · ') || '—'}</p>
        <table className="mt-4 w-full border-collapse text-xs">
            <thead><tr className="bg-slate-200"><th className="border border-slate-600 px-2 py-1.5 text-left">No</th><th className="border border-slate-600 px-2 py-1.5 text-left">Kategori</th><th className="border border-slate-600 px-2 py-1.5 text-left">Uraian</th><th className="border border-slate-600 px-2 py-1.5 text-center">Jml</th><th className="border border-slate-600 px-2 py-1.5 text-left">Keterangan</th></tr></thead>
            <tbody>{rows.length ? rows.map((row, index) => <tr key={index}><td className="border border-slate-600 px-2 py-1.5 text-center">{index + 1}</td><td className="border border-slate-600 px-2 py-1.5">{row.kategori}</td><td className="border border-slate-600 px-2 py-1.5">{row.uraian}</td><td className="border border-slate-600 px-2 py-1.5 text-center">{row.jumlah}</td><td className="border border-slate-600 px-2 py-1.5">{row.keterangan || '—'}</td></tr>) : <tr><td colSpan="5" className="border border-slate-600 px-2 py-3 text-center">Tidak ada item perangkat.</td></tr>}</tbody>
        </table>
        {(request.software_standard ?? []).length > 0 && <p className="mt-3 text-sm">Software standar terpasang: {(request.software_standard ?? []).join(', ')}.</p>}
        {request.accessory_other_note && <p className="mt-1 text-sm">Keterangan accessories: {request.accessory_other_note}</p>}
        <SignatureRow request={request} timeline={timeline} />
        <SignBlock leftTitle="Disiapkan oleh" leftName={request.employee?.name ?? ''} rightTitle="Diterima Commercial" />
    </div>;
}

export default function Print({ request, meta = {}, timeline = [], format = 'memo', company = {} }) {
    return <div className="min-h-screen bg-slate-200 print:bg-white">
        <Head title={`Cetak ${request.request_number}`} />
        <div className="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 px-4 py-4 print:hidden">
            <div className="flex gap-2" role="tablist" aria-label="Format cetak">
                {formats.map(([value, text]) => <Link key={value} href={route('it-requests.print', { it_request: request.id, format: value })} className={`rounded-lg px-4 py-2 text-sm font-bold ${format === value ? 'bg-blue-600 text-white' : 'border border-slate-300 bg-white text-slate-600'}`}>{text}</Link>)}
            </div>
            <div className="flex gap-2">
                <Link href={route('it-requests.show', request.id)} className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Kembali</Link>
                <button type="button" onClick={() => window.print()} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-bold text-white">Cetak</button>
            </div>
        </div>
        <div className="mx-auto mb-10 max-w-4xl bg-white p-8 shadow print:m-0 print:max-w-none print:p-0 print:shadow-none sm:p-10">
            {format === 'items' ? <ItemsDoc request={request} meta={meta} timeline={timeline} /> : format === 'form' ? <><ITRequestForm request={request} meta={meta} readOnly embedded hideStepper approvalTimeline={[]} /><ApprovalMatrix request={request} timeline={timeline} /></> : <MemoDoc request={request} meta={meta} timeline={timeline} company={company} />}
        </div>
    </div>;
}
