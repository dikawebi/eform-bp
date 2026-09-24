import EmptyState from '@/Components/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import LeaveForm from './LeaveForm';
import { isStatusEditable, labelStatus, statusValue } from './helpers';

function tanggalInput(nilai) {
    if (!nilai) return '';
    return String(nilai).slice(0, 10);
}

/**
 * Edit hanya untuk status draft/returned. Backend (policy) tetap menolak
 * status lain dengan 403; halaman ini menampilkan info read-only bila
 * pengajuan sudah tidak editable.
 */
export default function Edit({ leave, meta, canEdit, availableActions }) {
    const status = statusValue(leave?.status);

    const bolehUbahProp =
        typeof canEdit === 'boolean'
            ? canEdit
            : Array.isArray(availableActions)
              ? availableActions.includes('edit') || availableActions.includes('update')
              : isStatusEditable(status);

    const periodeAwal = (leave?.periods ?? leave?.period ?? []).map((p) => ({
        category: p.category ?? 'annual_leave',
        start_date: tanggalInput(p.start_date),
        end_date: tanggalInput(p.end_date),
        notes: p.notes ?? '',
    }));

    const biayaAwal = (leave?.costItems ?? leave?.cost_items ?? []).map((it) => ({
        category: it.category ?? 'land_transport',
        description: it.description ?? '',
        quantity: it.quantity ?? 1,
        unit_price: it.unit_price ?? 0,
        origin: it.origin ?? '',
        destination: it.destination ?? '',
        flight_destination: it.flight_destination ?? '',
        service_date: tanggalInput(it.service_date),
        check_in_date: tanggalInput(it.check_in_date),
        check_out_date: tanggalInput(it.check_out_date),
        departure_time: it.departure_time ?? '',
    }));

    const { data, setData, put, processing, errors } = useForm({
        employee_id: leave?.employee_id ? String(leave.employee_id) : '',
        leave_type: leave?.leave_type ?? '',
        reason: leave?.reason ?? '',
        last_working_date: tanggalInput(leave?.last_working_date),
        onsite_date: tanggalInput(leave?.onsite_date),
        periods:
            periodeAwal.length > 0
                ? periodeAwal
                : [{ category: 'annual_leave', start_date: '', end_date: '', notes: '' }],
        cost_items: biayaAwal,
    });

    const simpan = () => {
        put(route('leaves.update', leave.id), {
            onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
        });
    };

    if (!bolehUbahProp) {
        return (
            <AuthenticatedLayout
                title="Ubah Cuti/Izin"
                breadcrumbs={[
                    { label: 'Dashboard', href: '/dashboard' },
                    { label: 'Cuti/Izin', href: '/leaves' },
                    { label: leave?.request_number ?? 'Detail', href: `/leaves/${leave?.id}` },
                    { label: 'Ubah' },
                ]}
                header={
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Ubah Pengajuan {leave?.request_number}
                    </h2>
                }
            >
                <Head title="Ubah Cuti/Izin" />
                <div className="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
                    <EmptyState
                        title="Pengajuan tidak dapat diubah"
                        description={`Status saat ini "${labelStatus(status)}". Pengajuan yang sudah disetujui tidak boleh diedit langsung — perubahan hanya melalui alur pengembalian (returned). Otorisasi final tetap diperiksa server.`}
                    >
                        <Link
                            href={route('leaves.show', leave.id)}
                            className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                        >
                            Kembali ke Detail
                        </Link>
                    </EmptyState>
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout
            title="Ubah Cuti/Izin"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Cuti/Izin', href: '/leaves' },
                { label: leave?.request_number ?? 'Detail', href: `/leaves/${leave?.id}` },
                { label: 'Ubah' },
            ]}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Ubah Pengajuan {leave?.request_number}
                </h2>
            }
        >
            <Head title={`Ubah ${leave?.request_number ?? 'Cuti'}`} />

            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
                {status === 'returned' && (
                    <div className="mb-4 rounded-lg border border-orange-200 bg-orange-50 p-4 text-sm text-orange-800">
                        Pengajuan ini <span className="font-semibold">dikembalikan</span> untuk
                        diperbaiki. Perbarui data yang diminta, simpan, lalu ajukan ulang dari
                        halaman detail. Alasan pengembalian dapat dilihat di halaman detail.
                    </div>
                )}

                <LeaveForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    meta={meta}
                    karyawanFallback={leave?.employee ?? null}
                    submitLabel="Simpan Perubahan"
                    batalHref={route('leaves.show', leave.id)}
                    onSubmit={simpan}
                />
            </div>
        </AuthenticatedLayout>
    );
}
