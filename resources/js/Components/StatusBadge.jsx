export default function StatusBadge({ status, className = '' }) {
    const key = String(status ?? '')
        .toLowerCase()
        .trim()
        .replace(/[\s-]+/g, '_');

    const styles = {
        draft: 'bg-slate-100 text-slate-700 ring-slate-600/20',
        submitted: 'bg-sky-100 text-sky-800 ring-sky-600/20',
        in_review: 'bg-amber-100 text-amber-800 ring-amber-600/20',
        returned: 'bg-orange-100 text-orange-800 ring-orange-600/20',
        rejected: 'bg-rose-100 text-rose-800 ring-rose-600/20',
        approved: 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        processing: 'bg-indigo-100 text-indigo-800 ring-indigo-600/20',
        advance_paid: 'bg-violet-100 text-violet-800 ring-violet-600/20',
        settlement_required:
            'bg-fuchsia-100 text-fuchsia-800 ring-fuchsia-600/20',
        payment_processing: 'bg-cyan-100 text-cyan-800 ring-cyan-600/20',
        completed: 'bg-teal-100 text-teal-800 ring-teal-600/20',
        cancelled: 'bg-zinc-100 text-zinc-700 ring-zinc-500/20',
    };

    const labels = {
        draft: 'Draf',
        submitted: 'Diajukan',
        in_review: 'Diperiksa',
        returned: 'Dikembalikan',
        rejected: 'Ditolak',
        approved: 'Disetujui',
        processing: 'Diproses',
        advance_paid: 'Advance Dibayar',
        settlement_required: 'Perlu Settlement',
        payment_processing: 'Pembayaran Diproses',
        completed: 'Selesai',
        cancelled: 'Dibatalkan',
    };

    const style = styles[key] ?? styles.draft;
    const label = labels[key] ?? String(status ?? '—');

    return (
        <span
            className={`ui-badge ${style} ${className}`}
        >
            {label}
        </span>
    );
}
