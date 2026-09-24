/**
 * Helper tampilan modul Cuti/Izin (Leaves).
 *
 * Semua label/fungsi di sini hanya untuk presentasi. Total hari, total
 * advance, status, dan otorisasi dihitung/diputuskan di server (Laravel);
 * angka di frontend selalu diberi label "Estimasi".
 */

/** Normalisasi status: backend mengirim enum sebagai string, tapi tetap tahan bila objek. */
export function statusValue(status) {
    if (status && typeof status === 'object') {
        return String(status.value ?? status.status ?? '');
    }
    return String(status ?? '');
}

export function formatRupiah(nilai) {
    const angka = Number(nilai);
    if (!Number.isFinite(angka)) return '—';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(angka);
}

export function formatTanggal(nilai) {
    if (!nilai) return '—';
    const tanggal = nilai instanceof Date ? nilai : new Date(nilai);
    if (Number.isNaN(tanggal.getTime())) return String(nilai);
    return tanggal.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function formatTanggalWaktu(nilai) {
    if (!nilai) return '—';
    const tanggal = nilai instanceof Date ? nilai : new Date(nilai);
    if (Number.isNaN(tanggal.getTime())) return String(nilai);
    return tanggal.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/** Jumlah hari inklusif (estimasi lokal; final dihitung server). */
export function estimasiHari(mulai, selesai) {
    if (!mulai || !selesai) return 0;
    const awal = new Date(mulai);
    const akhir = new Date(selesai);
    if (Number.isNaN(awal.getTime()) || Number.isNaN(akhir.getTime())) return 0;
    if (akhir < awal) return 0;
    return Math.round((akhir - awal) / 86400000) + 1;
}

export function labelDariOpsi(opsi, nilai, fallback) {
    const ketemu = (opsi ?? []).find((o) => String(o.value) === String(nilai));
    if (ketemu) return ketemu.label;
    return fallback ?? String(nilai ?? '—');
}

const PERIOD_LABEL_ID = {
    onsite: 'Onsite',
    travel_home: 'Perjalanan ke Rumah/Lokasi',
    roster_leave: 'Cuti Roster/OS',
    coff: 'C-Off',
    annual_leave: 'Cuti Tahunan',
    permission: 'Izin/Lainnya',
    travel_to_site: 'Perjalanan ke Site',
};

const COST_LABEL_ID = {
    land_transport: 'Transport Darat',
    hotel: 'Penginapan',
    meal: 'Makan',
    other: 'Lainnya',
};

export function labelPeriode(opsi, nilai) {
    return labelDariOpsi(opsi, nilai, PERIOD_LABEL_ID[String(nilai)] ?? String(nilai ?? '—'));
}

export function labelBiaya(opsi, nilai) {
    return labelDariOpsi(opsi, nilai, COST_LABEL_ID[String(nilai)] ?? String(nilai ?? '—'));
}

export function labelTipeCuti(nilai) {
    const peta = {
        annual_leave: 'Cuti Tahunan',
        roster_leave: 'Cuti Roster',
        coff: 'C-Off',
        permission: 'Izin',
        sick: 'Sakit',
        other: 'Lainnya',
    };
    return peta[String(nilai)] ?? String(nilai ?? '—').replace(/_/g, ' ');
}

export function labelStatus(nilai) {
    const peta = {
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
    return peta[String(nilai)] ?? String(nilai ?? '—');
}

/** Status yang masih boleh diubah pemilik (cerminan policy server; server tetap penentu). */
export function isStatusEditable(status) {
    const s = statusValue(status);
    return s === 'draft' || s === 'returned';
}

export function isStatusCancellable(status) {
    const s = statusValue(status);
    return s === 'draft' || s === 'submitted' || s === 'returned';
}

export function isStatusSubmittable(status) {
    const s = statusValue(status);
    return s === 'draft' || s === 'returned';
}
