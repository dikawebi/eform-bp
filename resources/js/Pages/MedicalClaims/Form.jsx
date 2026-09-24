import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const input = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100';
const blank = () => ({ relationship: 'self', dependent_id: '', treatment_date: '', facility_name: '', diagnosis_code: '', amount: '' });
const benefitLabels = {
    obat_vitamin: 'Pembelian Obat / Vitamin',
    rawat_jalan: 'Rawat Jalan',
    rawat_inap: 'Rawat Inap',
    lensa_kacamata: 'Lensa (Kacamata)',
    frame_kacamata: 'Frame (Kacamata)',
    medical_check_up: 'Medical Check Up (MCU)',
    kacamata: 'Kacamata (kategori lama)',
    persalinan: 'Persalinan (kategori lama)',
    lainnya: 'Lainnya (kategori lama)',
};

export default function Form({ claim, meta, edit = false }) {
    const { data, setData, post, put, processing, errors } = useForm({
        benefit_types: claim?.benefit_types ?? (claim?.benefit_type ? [claim.benefit_type] : [meta?.benefit_types?.[0]].filter(Boolean)),
        items: claim?.items?.map((item) => ({ ...item, treatment_date: item.treatment_date?.slice(0, 10) })) ?? [blank()],
    });

    const update = (index, key, value) => setData('items', data.items.map((item, row) => row === index ? { ...item, [key]: value } : item));
    const toggleBenefit = (type) => setData('benefit_types', data.benefit_types.includes(type) ? data.benefit_types.filter((item) => item !== type) : [...data.benefit_types, type]);
    const submit = (event) => {
        event.preventDefault();
        edit ? put(route('medical-claims.update', claim.id)) : post(route('medical-claims.store'));
    };

    return (
        <AuthenticatedLayout title={edit ? 'Ubah Medical Claim' : 'Buat Medical Claim'}>
            <Head title={edit ? 'Ubah Medical Claim' : 'Buat Medical Claim'} />
            <form onSubmit={submit} className="worksheet-form mx-auto max-w-5xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
                <section className="overflow-hidden rounded-2xl bg-gradient-to-br from-[#0066FF] to-[#0F172A] p-6 text-white shadow-md">
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-blue-200">Medical Claim</p>
                    <h1 className="mt-1 text-2xl font-extrabold">{edit ? 'Perbarui klaim kesehatan' : 'Ajukan klaim kesehatan'}</h1>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-blue-100">Pilih manfaat, tambahkan pasien dan rincian biaya. Informasi pasien mengikuti master tanggungan karyawan.</p>
                </section>

                <section className="worksheet-section overflow-hidden">
                    <header className="worksheet-section-header"><div><p className="worksheet-eyebrow">Sheet Medical Claim · Bagian 01</p><h2 className="text-base font-bold">Pilih jenis manfaat</h2><p className="text-xs text-slate-500">Centang satu atau beberapa manfaat sesuai bukti pengeluaran.</p></div></header>
                    <fieldset className="grid grid-cols-1 gap-px bg-slate-200 p-px sm:grid-cols-2 lg:grid-cols-3 dark:bg-slate-700">
                        <legend className="sr-only">Jenis manfaat kesehatan</legend>
                        {(meta?.benefit_types ?? []).map((type, index) => <label key={type} className="worksheet-cell flex cursor-pointer items-center gap-3"><span className="flex h-6 w-6 shrink-0 items-center justify-center rounded border border-slate-300 bg-white text-[10px] font-bold text-blue-700 dark:border-slate-600 dark:bg-slate-900">{String(index + 1).padStart(2, '0')}</span><input type="checkbox" checked={data.benefit_types.includes(type)} onChange={() => toggleBenefit(type)} className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" /><span className="text-sm font-medium">{benefitLabels[type] ?? type}</span></label>)}
                    </fieldset>
                    <div className="px-3 pb-3"><InputError message={errors.benefit_types || errors.benefit_type} className="mt-1" /><InputError message={errors['benefit_types.0']} /></div>
                </section>

                <section className="ui-card space-y-4 p-5 sm:p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div><p className="text-xs font-bold uppercase tracking-[0.13em] text-blue-600">02 / Pasien & rincian</p><h2 className="mt-1 text-base font-bold">Pasien dan biaya</h2></div>
                        <button type="button" onClick={() => setData('items', [...data.items, blank()])} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">+ Tambah pasien</button>
                    </div>
                    <InputError message={errors.items} />
                    {data.items.map((item, index) => (
                        <article key={item.id ?? index} className="worksheet-row overflow-hidden">
                            <header className="worksheet-row-header flex items-center justify-between px-3 py-2.5"><div className="flex items-center gap-2"><span className="worksheet-row-index">{index + 1}</span><h3 className="text-sm font-bold">Rincian pasien</h3></div>{data.items.length > 1 && <button type="button" onClick={() => setData('items', data.items.filter((_, row) => row !== index))} className="rounded-md px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40">Hapus baris</button>}</header>
                            <div className="grid grid-cols-1 gap-px bg-slate-200 p-px dark:bg-slate-700 md:grid-cols-2">
                                <div className="worksheet-cell"><label className="text-xs font-semibold">Hubungan pasien</label><select className={`${input} mt-1`} value={item.relationship} onChange={(event) => update(index, 'relationship', event.target.value)}><option value="self">Karyawan</option><option value="spouse">Istri</option><option value="child">Anak</option></select><InputError message={errors[`items.${index}.relationship`]} className="mt-1" /></div>
                                {item.relationship !== 'self' && <div className="worksheet-cell"><label className="text-xs font-semibold">Tanggungan terdaftar</label><select required className={`${input} mt-1`} value={item.dependent_id ?? ''} onChange={(event) => update(index, 'dependent_id', event.target.value)}><option value="">Pilih tanggungan</option>{(meta?.dependents ?? []).filter((dependent) => dependent.relationship === item.relationship).map((dependent) => <option key={dependent.id} value={dependent.id}>{dependent.name}</option>)}</select><InputError message={errors[`items.${index}.dependent_id`]} className="mt-1" /></div>}
                                <div className="worksheet-cell"><label className="text-xs font-semibold">Tanggal berobat</label><input required type="date" className={`${input} mt-1`} value={item.treatment_date ?? ''} onChange={(event) => update(index, 'treatment_date', event.target.value)} /><InputError message={errors[`items.${index}.treatment_date`]} className="mt-1" /></div>
                                <div className="worksheet-cell"><label className="text-xs font-semibold">Nama / lokasi fasilitas kesehatan</label><input required className={`${input} mt-1`} value={item.facility_name ?? ''} onChange={(event) => update(index, 'facility_name', event.target.value)} placeholder="Nama klinik, rumah sakit, atau apotek" /><InputError message={errors[`items.${index}.facility_name`]} className="mt-1" /></div>
                                <div className="worksheet-cell"><label className="text-xs font-semibold">Jumlah biaya (Rp)</label><input required type="number" min="0" step="0.01" className={`${input} mt-1`} value={item.amount ?? ''} onChange={(event) => update(index, 'amount', event.target.value)} placeholder="0" /><InputError message={errors[`items.${index}.amount`]} className="mt-1" /></div>
                            </div>
                        </article>
                    ))}
                </section>

                <div className="flex flex-wrap justify-end gap-3">
                    <Link href="/medical-claims" className="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Batal</Link>
                    <button disabled={processing} className="ui-button-primary rounded-lg px-5 py-2.5 text-sm disabled:opacity-60">{processing ? 'Menyimpan…' : 'Simpan Draft'}</button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
