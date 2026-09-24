export function statusValue(status) {
    if (status && typeof status === 'object') return String(status.value ?? status.status ?? '');
    return String(status ?? '');
}

export function formatRupiah(value) {
    const number = Number(value);
    if (!Number.isFinite(number)) return '—';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(number);
}

export function formatTanggal(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function formatTanggalWaktu(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const labels = { land_transport: 'Transport Darat', flight: 'Tiket Pesawat', hotel: 'Penginapan', meal: 'Makan', other: 'Lainnya' };
export function labelKategori(value, options = []) {
    return options.find((option) => String(option.value) === String(value))?.label ?? labels[value] ?? String(value ?? '—').replace(/_/g, ' ');
}
export function labelStatus(value) {
    return { draft: 'Draf', submitted: 'Diajukan', in_review: 'Diperiksa', returned: 'Dikembalikan', rejected: 'Ditolak', approved: 'Disetujui', processing: 'Diproses', advance_paid: 'Advance Dibayar', settlement_required: 'Perlu Settlement', completed: 'Selesai', cancelled: 'Dibatalkan' }[statusValue(value)] ?? String(value ?? '—');
}
export const editableStatuses = ['draft', 'returned'];
export const submittableStatuses = ['draft', 'returned'];
export const cancellableStatuses = ['draft', 'submitted', 'returned'];
