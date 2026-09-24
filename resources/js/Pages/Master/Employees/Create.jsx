import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const inputCls = 'mt-1 block w-full rounded-md border-gray-300 text-sm';

export default function Create({ users, supervisors }) {
    const isAdmin = usePage().props.auth?.user?.roles?.includes('admin') ?? false;
    const { data, setData, post, processing, errors } = useForm({
        employee_number: '',
        name: '',
        department: '',
        level: '',
        job_title: '',
        roster: '',
        employment_status: 'permanent',
        poh_status: 'non_local',
        poh_city: '',
        poh_province: '',
        user_id: '',
        supervisor_id: '',
        hod_id: '',
        is_project_based: false,
        active: true,
        joined_at: '',
        ended_at: '',
    });

    const sameDepartment = supervisors.filter((person) => String(person.department ?? '').trim().toLowerCase() === String(data.department ?? '').trim().toLowerCase() && String(data.department ?? '').trim() !== '');
    const setDepartment = (department) => {
        const sameDeptIds = new Set(supervisors.filter((person) => String(person.department ?? '').trim().toLowerCase() === String(department ?? '').trim().toLowerCase() && String(department ?? '').trim() !== '').map((person) => String(person.id)));
        setData({ ...data, department, supervisor_id: sameDeptIds.has(String(data.supervisor_id)) ? data.supervisor_id : '', hod_id: sameDeptIds.has(String(data.hod_id)) ? data.hod_id : '' });
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('master.employees.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Tambah Karyawan</h2>}>
            <Head title="Tambah Karyawan" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl rounded-lg bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label className="text-xs font-medium text-gray-600">NIK</label>
                            <input value={data.employee_number} onChange={(e) => setData('employee_number', e.target.value)} className={inputCls} />
                            {errors.employee_number && <p className="text-xs text-red-600">{errors.employee_number}</p>}
                        </div>
                        <div>
                            <label className="text-xs font-medium text-gray-600">Nama</label>
                            <input value={data.name} onChange={(e) => setData('name', e.target.value)} className={inputCls} />
                            {errors.name && <p className="text-xs text-red-600">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="text-xs font-medium text-gray-600">Departemen</label>
                            <input value={data.department} onChange={(e) => setDepartment(e.target.value)} className={inputCls} />
                            {errors.department && <p className="text-xs text-red-600">{errors.department}</p>}
                        </div>
                        <div>
                            <label className="text-xs font-medium text-gray-600">Level</label>
                            <input value={data.level} onChange={(e) => setData('level', e.target.value)} className={inputCls} />
                            {errors.level && <p className="text-xs text-red-600">{errors.level}</p>}
                        </div>
                        <div>
                            <label className="text-xs font-medium text-gray-600">Jabatan</label>
                            <input value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} className={inputCls} />
                            {errors.job_title && <p className="text-xs text-red-600">{errors.job_title}</p>}
                        </div>
                        <div>
                            <label className="text-xs font-medium text-gray-600">Roster</label>
                            <input value={data.roster} onChange={(e) => setData('roster', e.target.value)} className={inputCls} />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-gray-600">Status Kepegawaian</label>
                            <input value={data.employment_status} onChange={(e) => setData('employment_status', e.target.value)} className={inputCls} />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-gray-600">Status POH</label>
                            <select value={data.poh_status} onChange={(e) => setData('poh_status', e.target.value)} className={inputCls}>
                                <option value="non_local">Non Lokal</option>
                                <option value="local">Lokal</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs font-medium text-gray-600">Kota POH</label>
                            <input value={data.poh_city} onChange={(e) => setData('poh_city', e.target.value)} className={inputCls} />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-gray-600">Provinsi POH</label>
                            <input value={data.poh_province} onChange={(e) => setData('poh_province', e.target.value)} className={inputCls} />
                        </div>
                        {isAdmin && <div>
                            <label className="text-xs font-medium text-gray-600">Tautkan akun login ke NIK (opsional)</label>
                            <p className="mt-1 text-xs text-gray-500">Pilih akun karyawan ini agar dapat mengajukan atas nama sendiri.</p>
                            <select value={data.user_id} onChange={(e) => setData('user_id', e.target.value)} className={inputCls}>
                                <option value="">— Tanpa user —</option>
                                {users.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name} ({u.email})
                                    </option>
                                ))}
                            </select>
                            {errors.user_id && <p className="text-xs text-red-600">{errors.user_id}</p>}
                        </div>}
                        <div>
                            <label className="text-xs font-medium text-gray-600">Supervisor · departemen sama</label>
                            <select value={data.supervisor_id} onChange={(e) => setData('supervisor_id', e.target.value)} className={inputCls}>
                                <option value="">{data.department ? '— Pilih supervisor —' : '— Pilih departemen dulu —'}</option>
                                {sameDepartment.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name} ({s.employee_number})
                                    </option>
                                ))}
                            </select>
                            {errors.supervisor_id && <p className="text-xs text-red-600">{errors.supervisor_id}</p>}
                        </div>
                        <div>
                            <label className="text-xs font-medium text-gray-600">HOD · departemen sama</label>
                            <select value={data.hod_id} onChange={(e) => setData('hod_id', e.target.value)} className={inputCls}>
                                <option value="">{data.department ? '— Pilih HOD —' : '— Pilih departemen dulu —'}</option>
                                {sameDepartment.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name} ({s.employee_number})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs font-medium text-gray-600">Tanggal Bergabung</label>
                            <input type="date" value={data.joined_at} onChange={(e) => setData('joined_at', e.target.value)} className={inputCls} />
                        </div>

                        <div className="flex items-center gap-4 md:col-span-2">
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={data.is_project_based} onChange={(e) => setData('is_project_based', e.target.checked)} />
                                Berbasis proyek
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={data.active} onChange={(e) => setData('active', e.target.checked)} />
                                Aktif
                            </label>
                        </div>

                        <div className="flex gap-3 md:col-span-2">
                            <button disabled={processing} className="rounded-md bg-gray-900 px-4 py-2 text-sm text-white">
                                Simpan
                            </button>
                            <Link href={route('master.employees.index')} className="rounded-md border px-4 py-2 text-sm">
                                Batal
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
