import { Head, Link } from '@inertiajs/react';

const fmtDate = (value) => value ? new Date(value).toLocaleDateString('id-ID', { dateStyle: 'medium' }) : '—';
const clip = (value, max = 200) => {
    const text = String(value ?? '').trim();
    return text.length > max ? `${text.slice(0, max).trimEnd()}…` : text;
};
const stepLabels = { hod: 'HOD', it: 'IT', pm_gm: 'PM/GM Site', coo_ceo: 'COO/CEO' };
const requestStatuses = { draft: 'Draft', submitted: 'Diajukan', in_review: 'Dalam Review', returned: 'Dikembalikan', rejected: 'Ditolak', approved: 'Disetujui', processing: 'Diproses', completed: 'Selesai', cancelled: 'Dibatalkan' };
const replacementLabels = { not_suitable: 'Tidak sesuai kebutuhan', damaged: 'Rusak', other: 'Lainnya' };
const typeLabels = { new_item: 'Barang baru', replacement: 'Penggantian' };

const deviceLabel = (request, meta) => request.device_type ? (meta?.option_labels?.[request.device_type] ?? request.device_type) : '—';
const accessoryLabel = (meta, code) => meta?.option_labels?.[code] ?? code;
const recipientName = (request) => request.employee?.name ?? request.recipient_name ?? '—';

function Chip({ label, children }) {
    return <div className="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5">
        <b className="block text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-400">{label}</b>
        <span className="text-[12px] font-semibold text-slate-900">{children}</span>
    </div>;
}

function SectionTitle({ children }) {
    return <h3 className="mb-1.5 mt-4 border-l-[3px] border-blue-700 pl-2 text-[9px] font-extrabold uppercase tracking-[0.14em] text-slate-600">{children}</h3>;
}

function Stamp({ requested = false }) {
    return <span
        className={`inline-block -rotate-[9deg] rounded-md border-[2.5px] px-2.5 py-1 text-[9px] font-extrabold uppercase leading-tight tracking-[0.18em] [-webkit-print-color-adjust:exact] [print-color-adjust:exact] ${requested ? 'border-slate-500/60 text-slate-600/90' : 'border-blue-700/70 text-blue-800/90'}`}
        style={{ boxShadow: requested ? 'inset 0 0 0 1px rgb(100 116 139 / 0.35)' : 'inset 0 0 0 1px rgb(37 99 235 / 0.4)' }}
    >✓ {requested ? 'Requested via eForm' : 'Approved via eForm'}</span>;
}

function SignatureRow({ request, timeline }) {
    const approved = new Map();
    for (const item of (timeline ?? [])) {
        if (item.status === 'approved') approved.set(item.step_code, { label: stepLabels[item.step_code] ?? item.step_code, person: item.acted_by ?? item.actor ?? null, at: item.at });
    }
    const boxes = [
        { title: 'Diminta oleh', name: recipientName(request), date: request.submitted_at, requested: true },
        ...[...approved.values()].map((item) => ({ title: item.label, name: item.person, date: item.at, requested: false })),
    ];

    return <div className="mt-4">
        <div className="grid grid-cols-4 gap-3">
            {boxes.map((box, index) => <div key={`${box.title}-${index}`} className="min-w-0 rounded-lg border border-slate-200 p-2.5">
                <p className="text-[10px] font-extrabold text-blue-900">{box.title}</p>
                <div className="relative flex h-14 items-center justify-center overflow-hidden py-1">
                    <Stamp requested={box.requested} />
                </div>
                <p className="truncate text-[11px] font-extrabold text-slate-900">{box.name ?? '........................'}</p>
                <p className="text-[9.5px] text-slate-500">{box.date ? fmtDate(box.date) : '.... / .... / ....'}</p>
            </div>)}
        </div>
    </div>;
}

export default function Print({ request, specification = null, meta = {}, timeline = [], company = {} }) {
    const accessories = (request.accessories ?? []).map((code) => accessoryLabel(meta, code));
    const softwareOptional = request.software_optional ?? [];
    const address = [company?.city, company?.regency, company?.province].filter(Boolean).join(' · ');

    return <div className="min-h-screen bg-slate-200 dark:bg-slate-950 print:bg-white">
        <Head title={`Save to PDF ${request.request_number}`} />
        <style>{'@page { size: A4 portrait; margin: 10mm; }'}</style>

        <div className="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 px-4 py-4 print:hidden">
            <Link href={route('it-requests.show', request.id)} className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Kembali</Link>
            <button type="button" onClick={() => window.print()} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400">Save to PDF</button>
        </div>

        <div className="mx-auto mb-10 max-w-4xl bg-white pb-8 text-slate-900 shadow print:m-0 print:max-w-none print:pb-0 print:shadow-none dark:bg-white dark:text-slate-900 dark:shadow-black/40 [-webkit-print-color-adjust:exact] [print-color-adjust:exact]">
            <div className="flex items-center justify-between bg-gradient-to-r from-slate-900 to-blue-900 px-12 py-4 text-white print:px-6">
                <div className="flex items-center gap-3">
                    <img src="/images/logo-bp.png" alt="Logo perusahaan" width="40" height="40" className="h-10 w-10 rounded-lg bg-white object-contain" />
                    <div>
                        <p className="text-[16px] font-extrabold tracking-tight">{company?.name ?? 'PT Borneo Prima'}</p>
                        <p className="mt-0.5 text-[9.5px] text-slate-300">{address}</p>
                    </div>
                </div>
                <div className="text-right">
                    <p className="text-[9px] uppercase tracking-[0.2em] text-slate-300">Nomor request</p>
                    <p className="font-mono text-[13px] font-bold">{request.request_number}</p>
                </div>
            </div>

            <div className="px-12 print:px-6">
                <h2 className="mt-3.5 text-[11.5px] font-extrabold uppercase tracking-[0.16em] text-blue-700 after:mt-1.5 after:block after:h-[3px] after:w-16 after:rounded-sm after:bg-blue-700">IT &amp; Telecommunication Acquisition Request</h2>

                <div>
                    <SectionTitle>Penerima</SectionTitle>
                    <div className="grid grid-cols-2 gap-x-5 gap-y-1.5">
                        <Chip label="Nama">{recipientName(request)}</Chip>
                        <Chip label="NIK">{request.employee?.employee_number ?? '—'}</Chip>
                        <Chip label="Departemen">{request.employee?.department ?? '—'}</Chip>
                        <Chip label="Jabatan">{request.employee?.job_title ?? '—'}</Chip>
                        <Chip label="Jenis permintaan">{typeLabels[request.request_type] ?? request.request_type ?? '—'}</Chip>
                        <Chip label="Site / Cost code">{[request.recipient_site, request.recipient_cost_code].filter(Boolean).join(' · ') || '—'}</Chip>
                    </div>

                    <SectionTitle>Persetujuan</SectionTitle>
                    <div className="grid grid-cols-2 gap-x-5 gap-y-1.5">
                        <Chip label="Status">{requestStatuses[request.status] ?? request.status}</Chip>
                        <Chip label="Selesai">{fmtDate(request.completed_at)}</Chip>
                        <Chip label="Kebutuhan khusus">{request.special_specification ? 'Ya' : 'Tidak'}</Chip>
                    </div>

                    <SectionTitle>Perangkat &amp; Software</SectionTitle>
                    <table className="w-full border-collapse text-[12px]">
                        <thead>
                            <tr className="bg-indigo-50 text-left text-[9.5px] font-extrabold uppercase tracking-[0.1em] text-blue-900">
                                <th className="px-2.5 py-1">Komponen</th>
                                <th className="px-2.5 py-1">Rincian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td className="border-b border-slate-300 px-2.5 py-1 text-[10.5px] font-bold text-blue-900">Perangkat utama</td><td className="border-b border-slate-300 px-2.5 py-1 font-semibold">{deviceLabel(request, meta)}</td></tr>
                            <tr><td className="border-b border-slate-300 px-2.5 py-1 text-[10.5px] font-bold text-blue-900">Accessories</td><td className="border-b border-slate-300 px-2.5 py-1">{accessories.length ? accessories.join(', ') : '—'}{request.accessory_other_note ? ` (${request.accessory_other_note})` : ''}</td></tr>
                            <tr><td className="border-b border-slate-300 px-2.5 py-1 text-[10.5px] font-bold text-blue-900">Software standar</td><td className="border-b border-slate-300 px-2.5 py-1">{(request.software_standard ?? []).join(', ') || '—'}</td></tr>
                            <tr><td className="border-b border-slate-300 px-2.5 py-1 text-[10.5px] font-bold text-blue-900">Software tambahan</td><td className="border-b border-slate-300 px-2.5 py-1">{softwareOptional.length ? softwareOptional.map((item) => `${item.name}${item.license === 'existing' ? ' (lisensi tersedia)' : ' (lisensi baru)'}`).join(', ') : '—'}</td></tr>
                        </tbody>
                    </table>

                    <SectionTitle>Spesifikasi Teknis Ditetapkan IT</SectionTitle>
                    {specification ? <>
                        <table className="w-full border-collapse text-[12px]">
                            <thead>
                                <tr className="bg-indigo-50 text-left text-[9.5px] font-extrabold uppercase tracking-[0.1em] text-blue-900">
                                    <th className="px-2.5 py-1">Komponen</th>
                                    <th className="px-2.5 py-1">Spesifikasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(specification.specs ?? []).map((row, index) => <tr key={`${row.label}-${index}`}>
                                    <td className="border-b border-slate-300 px-2.5 py-1 text-[10.5px] font-bold text-blue-900">{row.label}</td>
                                    <td className="border-b border-slate-300 px-2.5 py-1">{row.value}</td>
                                </tr>)}
                                {specification.notes && <tr><td className="border-b border-slate-300 px-2.5 py-1 text-[10.5px] font-bold text-blue-900">Catatan IT</td><td className="whitespace-pre-line border-b border-slate-300 px-2.5 py-1">{specification.notes}</td></tr>}
                            </tbody>
                        </table>
                        <p className="mt-1.5 text-[10px] text-slate-600">Ditetapkan oleh {specification.locked_by ?? specification.specified_by ?? '—'} · {fmtDate(specification.locked_at ?? specification.specified_at)}{specification.template_name ? ` · Template: ${specification.template_name}` : ''}</p>
                    </> : <p className="rounded-md bg-slate-50 px-2.5 py-1.5 text-[11px] text-slate-600">Spesifikasi IT belum tersedia untuk request ini.</p>}

                    <SectionTitle>Deskripsi &amp; Tujuan</SectionTitle>
                    <div className="grid grid-cols-2 gap-x-5 gap-y-1.5">
                        <Chip label="Deskripsi">{clip(request.description) || '—'}</Chip>
                        <Chip label="Tujuan">{clip(request.purpose) || '—'}</Chip>
                    </div>
                    {request.request_type === 'replacement' && request.replacement_reason && <p className="mt-1.5 text-[11px] text-slate-600"><span className="font-bold text-blue-900">Alasan penggantian:</span> {replacementLabels[request.replacement_reason] ?? request.replacement_reason}{request.replacement_note ? ` — ${clip(request.replacement_note, 120)}` : ''}</p>}
                </div>

                <SignatureRow request={request} timeline={timeline} />

                <p className="mt-3 rounded-md bg-indigo-50 px-2.5 py-1.5 text-[8.5px] font-medium text-blue-900">Persetujuan ini bukan persetujuan pembelian. Spesifikasi teknis ditentukan internal oleh IT.</p>
            </div>
        </div>
    </div>;
}
