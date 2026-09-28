import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import TravelForm, { emptyItem } from './TravelForm';

export default function Create({ meta }) {
    const employees = meta?.employees ?? [];
    const { data, setData, post, transform, processing, errors } = useForm({ employee_id: employees.length === 1 ? String(employees[0].id) : '', purpose: '', start_date: '', end_date: '', origin: '', destination: '', is_project_trip: false, items: [emptyItem(meta?.cost_categories?.[0]?.value ?? 'land_transport')] });
    const save = () => {
        transform((payload) => {
            const routeItem = payload.items.find((item) => item.category === 'land_transport');
            return { ...payload, origin: payload.origin || routeItem?.origin || 'Belum ditentukan', destination: payload.destination || routeItem?.destination || 'Belum ditentukan' };
        });
        post(route('travels.store'), { onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }) });
    };
    return <AuthenticatedLayout title="Buat Perjalanan Dinas" breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Perjalanan Dinas', href: '/travels' }, { label: 'Buat' }]} header={<h2 className="text-xl font-semibold text-gray-800">Buat Pengajuan Perjalanan Dinas</h2>}><Head title="Buat Perjalanan Dinas" /><div className="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8"><div className="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">Pengajuan akan disimpan sebagai <b>draf</b>. Total advance final dihitung oleh server.</div><TravelForm data={data} setData={setData} errors={errors} processing={processing} meta={meta} batalHref={route('travels.index')} onSubmit={save} currentStatus="draft" /></div></AuthenticatedLayout>;
}
