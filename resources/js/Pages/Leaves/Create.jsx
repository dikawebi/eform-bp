import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import LeaveForm from './LeaveForm';

export default function Create({ meta }) {
    const opsiPeriode = meta?.period_categories ?? [];
    const daftarKaryawan = meta?.employees ?? [];

    const { data, setData, post, transform, processing, errors } = useForm({
        employee_id: daftarKaryawan.length === 1 ? String(daftarKaryawan[0].id) : '',
        leave_type: 'annual_leave',
        reason: '',
        last_working_date: '',
        onsite_date: '',
        periods: opsiPeriode.map((option) => ({
            category: option.value,
            start_date: '',
            end_date: '',
            notes: '',
        })),
        cost_items: [],
    });

    const simpan = () => {
        transform((payload) => ({
            ...payload,
            periods: (payload.periods ?? []).filter((period) => period.start_date || period.end_date),
        }));
        post(route('leaves.store'), {
            onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
        });
    };

    return (
        <AuthenticatedLayout
            title="Buat Cuti/Izin"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Cuti/Izin', href: '/leaves' },
                { label: 'Buat' },
            ]}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Buat Pengajuan Cuti/Izin
                </h2>
            }
        >
            <Head title="Buat Cuti/Izin" />

            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
                <div className="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
                    Pengajuan tersimpan sebagai <span className="font-semibold">draf</span> dan
                    belum terkirim ke approval. Setelah tersimpan, gunakan tombol{' '}
                    <span className="font-semibold">Ajukan</span> di halaman detail untuk
                    mengirimnya. Rincian kategori dan tanggal cuti diisi pada
                    Section A.
                </div>

                <LeaveForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    meta={meta}
                    karyawanFallback={daftarKaryawan[0] ?? null}
                    submitLabel="Simpan Draft"
                    batalHref={route('leaves.index')}
                    onSubmit={simpan}
                    currentStatus="draft"
                />
            </div>
        </AuthenticatedLayout>
    );
}
