const eventLabels = {
    'approval.assigned': 'Perlu Tindakan',
    'approval.withdrawn': 'Ditarik Kembali',
    'approval.delegated': 'Didelegasikan',
    'approval.escalation_decided': 'Keputusan Eskalasi',
    'workflow.approve': 'Disetujui',
    'workflow.return': 'Dikembalikan',
    'workflow.reject': 'Ditolak',
    'advance.processing': 'Advance Diproses',
    'advance.settlement_required': 'Advance Cair',
    'advance.completed': 'Selesai',
    'settlement.completed': 'Selesai',
    'source.completed': 'Sumber Selesai',
    'it.completed': 'Selesai',
    'erp.completed': 'Selesai',
    'user.request-link': 'Permintaan Tautan',
};

const stepLabels = {
    supervisor: 'Supervisor',
    hod: 'HOD',
    pm: 'PM',
    pm_gm: 'PM/GM',
    it: 'IT',
    erp_review: 'Reviewer ERP',
    coo_ceo: 'COO/CEO',
    coo: 'COO',
    ceo: 'CEO',
    hrga: 'HRGA',
    finance: 'Finance',
    document_validation: 'Validasi Dokumen',
};

export function eventLabel(event) {
    if (!event) return '';
    if (eventLabels[event]) return eventLabels[event];
    return String(event).replace(/[._-]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export function stepLabel(step) {
    if (!step) return '';
    return stepLabels[step] ?? step;
}

function badgeClass(event) {
    if (event === 'approval.assigned') return 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300';
    if (event === 'workflow.reject') return 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300';
    if (event === 'workflow.return' || event === 'approval.delegated') return 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300';
    if ((event ?? '').includes('completed') || event === 'workflow.approve' || event === 'advance.settlement_required') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
}

export function EventBadge({ event }) {
    if (!event) return null;
    return <span className={`ml-2 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${badgeClass(event)}`}>{eventLabel(event)}</span>;
}

export function MetaLine({ meta, className = 'mt-1 block text-[11px] text-slate-400' }) {
    const doc = [meta?.document_number, meta?.module].filter(Boolean).join(' · ');
    const context = [meta?.actor ? `Oleh ${meta.actor}` : '', meta?.step ? `Tahap ${stepLabel(meta.step)}` : ''].filter(Boolean).join(' · ');
    if (!doc && !context) return null;
    return <span className={className}>{[doc, context].filter(Boolean).join(' — ')}</span>;
}
