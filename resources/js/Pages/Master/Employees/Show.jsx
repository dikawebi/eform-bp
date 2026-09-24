import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ employee }) {
    const { flash } = usePage().props;

    const deactivate = () => {
        if (confirm(`Nonaktifkan ${employee.name} (${employee.employee_number})?`)) {
            router.delete(route('master.employees.destroy', employee.id));
        }
    };

    const rows = [
        ['NIK', employee.employee_number],
        ['Nama', employee.name],
        ['Departemen', employee.department],
        ['Level', employee.level],
        ['Jabatan', employee.job_title],
        ['Roster', employee.roster ?? '-'],
        ['Status Kepegawaian', employee.employment_status],
        ['Status POH', employee.poh_status],
        ['Kota POH', employee.poh_city ?? '-'],
        ['Provinsi POH', employee.poh_province ?? '-'],
        ['Supervisor', employee.supervisor ? `${employee.supervisor.name} (${employee.supervisor.employee_number})` : '-'],
        ['HOD', employee.hod ? `${employee.hod.name} (${employee.hod.employee_number})` : '-'],
        ['Berbasis Proyek', employee.is_project_based ? 'Ya' : 'Tidak'],
        ['Status', employee.active ? 'Aktif' : 'Nonaktif'],
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Detail Karyawan</h2>}>
            <Head title="Detail Karyawan" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl space-y-4">
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">{flash.success}</div>
                    )}

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <dl className="divide-y text-sm">
                            {rows.map(([label, value]) => (
                                <div key={label} className="grid grid-cols-3 gap-4 py-2">
                                    <dt className="text-gray-500">{label}</dt>
                                    <dd className="col-span-2 font-medium text-gray-900">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </div>

                    <div className="flex gap-3">
                        <Link href={route('master.employees.edit', employee.id)} className="rounded-md bg-gray-900 px-4 py-2 text-sm text-white">
                            Ubah
                        </Link>
                        {employee.active && (
                            <button onClick={deactivate} className="rounded-md border border-red-300 px-4 py-2 text-sm text-red-700">
                                Nonaktifkan
                            </button>
                        )}
                        <Link href={route('master.employees.index')} className="rounded-md border px-4 py-2 text-sm">
                            Kembali
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
