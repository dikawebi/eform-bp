import EmptyState from '@/Components/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import TravelForm, { normalizeMetadata } from './TravelForm';
import { editableStatuses, statusValue, labelStatus } from './helpers';

const date = (value) => value ? String(value).slice(0, 10) : '';
const itemData = (item) => { const category = item.category ?? 'land_transport'; return { category, transaction_date: date(item.transaction_date), origin: item.origin ?? '', destination: item.destination ?? '', description: item.description ?? '', quantity: item.quantity ?? 1, unit_price: item.unit_price ?? 0, metadata: normalizeMetadata(category, item.metadata_json ?? item.metadata ?? {}) }; };

export default function Edit({ travel, meta, canEdit, availableActions }) {
    const status = statusValue(travel?.status);
    const allowed = typeof canEdit === 'boolean' ? canEdit : Array.isArray(availableActions) ? availableActions.includes('edit') || availableActions.includes('update') : editableStatuses.includes(status);
    const employees = meta?.employees ?? [];
    const { data, setData, put, processing, errors } = useForm({ employee_id: travel?.employee_id ? String(travel.employee_id) : '', purpose: travel?.purpose ?? '', start_date: date(travel?.start_date), end_date: date(travel?.end_date), origin: travel?.origin ?? '', destination: travel?.destination ?? '', is_project_trip: Boolean(travel?.is_project_trip), items: (travel?.items ?? []).map(itemData) });
    const save = () => put(route('travels.update', travel.id), { onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }) });
    const shell = (children) => <AuthenticatedLayout title="Ubah Perjalanan Dinas" breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Perjalanan Dinas', href: '/travels' }, { label: travel?.request_number ?? 'Ubah' }]} header={<h2 className="text-xl font-semibold text-gray-800">Ubah {travel?.request_number}</h2>}><Head title="Ubah Perjalanan Dinas" /><div className="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">{children}</div></AuthenticatedLayout>;
    if (!allowed) return shell(<EmptyState title="Pengajuan tidak dapat diubah" description={`Status saat ini “${labelStatus(status)}”. Pengajuan yang telah disetujui tidak boleh diedit langsung. Otorisasi tetap diperiksa server.`}><Link href={route('travels.show', travel.id)} className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Kembali ke Detail</Link></EmptyState>);
    return shell(<><div className="mb-4 rounded-lg border border-orange-200 bg-orange-50 p-4 text-sm text-orange-800">Periksa kembali data sebelum menyimpan. Pengajuan berstatus {labelStatus(status)}.</div><TravelForm data={data} setData={setData} errors={errors} processing={processing} meta={meta} travelEmployee={travel?.employee} submitLabel="Simpan Perubahan" batalHref={route('travels.show', travel.id)} onSubmit={save} /></>);
}
