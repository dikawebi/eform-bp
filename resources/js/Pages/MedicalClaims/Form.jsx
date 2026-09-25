import InputError from "@/Components/InputError";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Modal from "@/Components/Modal";
import { Head, Link, useForm } from "@inertiajs/react";
import { Fragment, useMemo, useState } from "react";
import WorkflowStepper from "@/Components/WorkflowStepper";

const input = "mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500";
const benefitLabels = {
    obat_vitamin: "Pembelian Obat / Vitamin", rawat_jalan: "Rawat Jalan",
    rawat_inap: "Rawat Inap", lensa_kacamata: "Lensa (Kacamata)",
    frame_kacamata: "Frame (Kacamata)", medical_check_up: "Medical Check Up (MCU)",
    kacamata: "Kacamata", persalinan: "Persalinan", lainnya: "Lainnya",
};
const relationshipLabels = { self: "Karyawan", spouse: "Istri", child: "Anak" };
const blank = () => ({ patient_name: "", relationship: "self", treatment_date: "", facility_name: "", diagnosis_code: "", amount: "" });
const rupiah = (value) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(Number(value) || 0);

function ErrorSummary({ errors }) {
    const messages = Object.entries(errors ?? {});
    if (!messages.length) return null;
    return <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        <p className="font-semibold">Periksa kembali isian berikut:</p>
        <ul className="mt-2 list-disc space-y-1 pl-5">{messages.slice(0, 12).map(([key, message]) => <li key={key}>{String(message)}</li>)}</ul>
    </div>;
}

export default function Form({ claim, meta, edit = false, readOnly = false, approvalTimeline = [], embedded = false }) {
    const employee = claim?.employee ?? meta?.employee ?? null;
    const { data, setData, post, put, processing, errors } = useForm({
        benefit_types: claim?.benefit_types ?? (claim?.benefit_type ? [claim.benefit_type] : [meta?.benefit_types?.[0]].filter(Boolean)),
        items: claim?.items?.map((item) => ({ ...item, treatment_date: item.treatment_date?.slice(0, 10) })) ?? [blank()],
        receipt: null,
    });
    const upload = useForm({ file: null, document_type: "receipt" });
    const [confirm, setConfirm] = useState(false);
    const total = useMemo(() => data.items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0), [data.items]);
    const requirements = meta?.required_documents ?? ["receipt"];
    const update = (index, key, value) => setData("items", data.items.map((item, row) => row === index ? { ...item, [key]: value } : item));
    const changeRelationship = (index, relationship) => setData("items", data.items.map((item, row) => row === index ? { ...item, relationship, patient_name: relationship === "self" ? "" : item.patient_name } : item));
    const toggleBenefit = (type) => setData("benefit_types", data.benefit_types.includes(type) ? data.benefit_types.filter((item) => item !== type) : [...data.benefit_types, type]);
    const save = () => edit ? put(route("medical-claims.update", claim.id)) : post(route("medical-claims.store"), { forceFormData: true });
    const requestSave = (event) => { event.preventDefault(); setConfirm(true); };
    const uploadReceipt = (event) => { event.preventDefault(); upload.post(route("medical-claims.attachments.store", claim.id), { forceFormData: true, preserveScroll: true, onSuccess: () => upload.reset("file") }); };

    const Layout = embedded ? Fragment : AuthenticatedLayout;
    return <Layout {...(embedded ? {} : { title: edit ? "Ubah Medical Claim" : "Buat Medical Claim" })}>
        <Head title={edit ? "Ubah Medical Claim" : "Buat Medical Claim"} />
        <form onSubmit={readOnly ? (event) => event.preventDefault() : requestSave} className="worksheet-form medical-sheet space-y-6">
            <fieldset disabled={readOnly} className="block min-w-0 space-y-6 border-0 p-0">
            <WorkflowStepper currentStatus={claim?.status ?? "draft"} title="Alur Medical Claim" steps={[{ key: "draft", label: "Draf" }, { key: "submitted", label: "Diajukan" }, { key: "in_review", label: "Review HRGA", pic: approvalTimeline.find((item) => item.step_code === "hrga")?.approver?.name }, { key: "document_validation", label: "Validasi Dokumen", pic: approvalTimeline.find((item) => item.step_code === "document_validation")?.approver?.name }, { key: "approved", label: "Disetujui" }, { key: "payment_processing", label: "Proses Pembayaran" }, { key: "completed", label: "Selesai" }]} />
            <header className="medical-sheet-header">
                <div className="medical-sheet-logo"><strong>BP</strong><small>PT. BORNEO PRIMA</small><em>COAL MINING &amp; TRADING</em></div>
                <div className="medical-sheet-title"><p>PT. BORNEO PRIMA</p><h1>FORMULIR MEDICAL CLAIM</h1></div>
                <div className="medical-sheet-number"><span>Nomor dokumen:</span><strong>{claim?.claim_number ?? "Dibuat saat draf disimpan"}</strong></div>
            </header>
            <ErrorSummary errors={errors} />

            <section className="medical-sheet-section">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Bagian 01 · Data Karyawan</p><h2>Identitas karyawan</h2><p>Identitas dan kepesertaan ditetapkan dari master karyawan oleh sistem.</p></div>
                <div className="medical-identity-grid">
                    <div className="medical-master-note"><strong>Profil karyawan dari master</strong><span>Data NIK, nama, departemen, dan jabatan tidak dapat diubah pada formulir klaim.</span></div>
                    <div><span>NIK</span><strong>{employee?.employee_number ?? "—"}</strong></div><div><span>NAMA</span><strong>{employee?.name ?? "—"}</strong></div><div><span>DEPARTEMEN</span><strong>{employee?.department ?? "—"}</strong></div><div><span>JABATAN</span><strong>{employee?.job_title ?? "—"}</strong></div>
                </div>
                <p className="medical-sheet-instruction">Silakan isi pada kolom berwarna biru saja. Sistem memverifikasi kepemilikan karyawan dan tanggungan saat disimpan.</p>
            </section>

            <section className="medical-sheet-section overflow-hidden">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Bagian 02 · Manfaat</p><h2>Pilih jenis manfaat</h2><p>Centang satu atau beberapa manfaat sesuai bukti pengeluaran.</p></div>
                <fieldset className="medical-benefit-grid"><legend className="sr-only">Jenis manfaat kesehatan</legend>
                    {(meta?.benefit_types ?? []).map((type, index) => <label key={type} className="medical-benefit-option"><span>{String(index + 1).padStart(2, "0")}</span><input type="checkbox" checked={data.benefit_types.includes(type)} onChange={() => toggleBenefit(type)} /><strong>{benefitLabels[type] ?? type}</strong></label>)}
                </fieldset>
                <InputError message={errors.benefit_types || errors.benefit_type || errors["benefit_types.0"]} className="mt-2" />
            </section>

            <section className="medical-sheet-section">
                <div className="worksheet-section-heading mb-4 flex flex-wrap items-center justify-between gap-2"><div><p className="worksheet-eyebrow">Bagian 03 · Rincian Pengobatan</p><h2>Pasien dan rincian biaya</h2><p>Jumlah total berikut merupakan estimasi; total akhir dihitung ulang oleh server.</p></div><span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Estimasi {rupiah(total)}</span></div>
                <InputError message={errors.items} className="mb-2" />
                <div className="medical-claim-rows">{data.items.map((item, index) => {
                    const patientName = item.relationship === "self" ? employee?.name ?? "Karyawan (dari master)" : item.patient_name ?? "";
                    return <article key={item.id ?? index} className="medical-claim-row">
                        <header><span className="worksheet-row-index">{index + 1}</span><strong>Rincian pasien</strong><span>Estimasi {rupiah(item.amount)}</span>{data.items.length > 1 && <button type="button" onClick={() => setData("items", data.items.filter((_, row) => row !== index))}>Hapus</button>}</header>
                        <div className="medical-claim-table" role="group" aria-label={`Rincian pasien ${index + 1}`}>
                            <div className="medical-patient"><label>Nama pasien <b>{item.relationship !== "self" ? "*" : ""}</b></label>{item.relationship === "self" ? <strong>{patientName}</strong> : <input required className={input} value={patientName} onChange={(event) => update(index, "patient_name", event.target.value)} placeholder="Nama istri atau anak" maxLength="150" />}<InputError message={errors[`items.${index}.patient_name`]} /></div>
                            <div><label>Hubungan dengan karyawan <b>*</b></label><select required className={input} value={item.relationship} onChange={(event) => changeRelationship(index, event.target.value)}>{Object.entries(relationshipLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><InputError message={errors[`items.${index}.relationship`]} /></div>
                            <div><label>Tanggal berobat <b>*</b></label><input required type="date" className={input} value={item.treatment_date ?? ""} onChange={(event) => update(index, "treatment_date", event.target.value)} /><InputError message={errors[`items.${index}.treatment_date`]} /></div>
                            <div><label>Nama / lokasi fasilitas kesehatan <b>*</b></label><input required className={input} value={item.facility_name ?? ""} onChange={(event) => update(index, "facility_name", event.target.value)} placeholder="Klinik, rumah sakit, atau apotek" maxLength="255" /><InputError message={errors[`items.${index}.facility_name`]} /></div>
                            <div><label>Jumlah biaya (Rp) <b>*</b></label><input required type="number" min="0" step="0.01" className={input} value={item.amount ?? ""} onChange={(event) => update(index, "amount", event.target.value)} placeholder="0" /><InputError message={errors[`items.${index}.amount`]} /></div>
                        </div>
                    </article>;
                })}</div>
                <button type="button" onClick={() => setData("items", [...data.items, blank()])} className="medical-add-row">+ Tambah baris pasien</button>
                <div className="medical-total-row"><span>Total estimasi klaim</span><strong>{rupiah(total)}</strong></div>
            </section>

            <section className="medical-sheet-section">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Bagian 04 · Lampiran</p><h2>Bukti pengobatan</h2><p>Lampiran disimpan private dan dapat diperiksa HRGA sesuai hak akses.</p></div>
                <div className="medical-attachment-grid"><div><h3>Persyaratan dokumen</h3><ul>{requirements.map((document) => <li key={document}>{document === "receipt" ? "Nota / kuitansi pembayaran" : document === "prescription" ? "Resep dokter" : document === "doctor_letter" ? "Surat dokter" : document}</li>)}</ul><p>PDF, JPG, JPEG, PNG, atau WebP; maksimal 5 MB per berkas.</p></div>
                    {!edit ? <div><label htmlFor="medical-receipt">Nota / kuitansi pembayaran</label><input id="medical-receipt" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(event) => setData("receipt", event.target.files?.[0] ?? null)} /><InputError message={errors.receipt} /></div> : <div><h3>Unggah bukti tambahan</h3><p>Tambahkan dokumen melalui panel lampiran di bawah formulir.</p></div>}
                </div>
            </section>
            {!readOnly && <div className="flex flex-wrap gap-3"><button disabled={processing} className="rounded-md bg-gray-900 px-5 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-60">{processing ? "Menyimpan…" : "Simpan Draft"}</button><Link href="/medical-claims" className="rounded-md border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</Link></div>}
            </fieldset>
        </form>
        {edit && <section className="medical-sheet medical-attachment-panel"><h2>Lampiran bukti pengobatan</h2>{claim?.attachments?.length ? <ul>{claim.attachments.map((attachment) => <li key={attachment.id}><a href={attachment.download_url}>{attachment.original_name}</a><span>{attachment.document_type}</span></li>)}</ul> : <p>Belum ada lampiran.</p>}<form onSubmit={uploadReceipt}><input type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(event) => upload.setData("file", event.target.files?.[0] ?? null)} /><InputError message={upload.errors.file} /><button disabled={upload.processing || !upload.data.file} className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60">{upload.processing ? "Mengunggah…" : "Unggah bukti"}</button></form></section>}
        <Modal show={confirm} onClose={() => setConfirm(false)} maxWidth="md"><div className="p-6"><h2 className="text-lg font-semibold text-gray-900">Konfirmasi penyimpanan</h2><p className="mt-2 text-sm text-gray-600">Draf klaim akan disimpan dengan estimasi total <strong>{rupiah(total)}</strong>.</p><div className="mt-3 rounded-md bg-amber-50 p-3 text-xs text-amber-800">Total dan validitas tanggungan diverifikasi ulang oleh server. Pengajuan belum dikirim ke approval sampai Anda menekan Ajukan dari halaman detail.</div><div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setConfirm(false)} className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Periksa lagi</button><button type="button" disabled={processing} onClick={() => { setConfirm(false); save(); }} className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60">Ya, simpan</button></div></div></Modal>
        </Layout>;
}
