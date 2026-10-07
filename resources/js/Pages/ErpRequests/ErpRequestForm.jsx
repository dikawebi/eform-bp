import InputError from "@/Components/InputError";
import EmployeeLookup from "@/Components/EmployeeLookup";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Modal from "@/Components/Modal";
import { Head, Link, useForm } from "@inertiajs/react";
import { Fragment, useState } from "react";
import WorkflowStepper from "@/Components/WorkflowStepper";

const input = "mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500";
const label = "block text-xs font-semibold text-slate-600";
const actionLabels = { new_account: "Pembuatan Akun Baru", modify_role: "Perubahan / Penambahan Role", reset_auth: "Reset Akses / Otorisasi Khusus" };
const moduleLabels = { finance_gl: "Finance & GL", accounts_payable: "Accounts Payable (AP)", accounts_receivable: "Accounts Receivable (AR)", supply_chain_procurement: "Supply Chain & Procurement", inventory_warehouse: "Inventory & Warehouse", fixed_assets: "Fixed Assets", project_management: "Project Management", budgeting: "Budgeting" };

function ErrorSummary({ errors }) {
    const messages = Object.entries(errors ?? {});
    if (!messages.length) return null;
    return <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        <p className="font-semibold">Periksa kembali isian berikut:</p>
        <ul className="mt-2 list-disc space-y-1 pl-5">{messages.slice(0, 12).map(([key, message]) => <li key={key}>{String(message)}</li>)}</ul>
    </div>;
}

export default function ErpRequestForm({ request = null, meta = {}, edit = false, readOnly = false, approvalTimeline = [], embedded = false }) {
    const employees = meta?.employees ?? [];
    const defaultEmployee = request?.employee ?? employees[0] ?? null;
    const { data, setData, post, put, processing, errors } = useForm({
        employee_id: request?.employee_id ? String(request.employee_id) : (employees.length === 1 ? String(employees[0].id) : ""),
        recipient_name: request?.recipient_name ?? "",
        recipient_site: request?.recipient_site ?? "",
        recipient_cost_code: request?.recipient_cost_code ?? "",
        recipient_position: request?.recipient_position ?? "",
        action_type: request?.action_type ?? "new_account",
        existing_erp_username: request?.existing_erp_username ?? "",
        business_purpose: request?.business_purpose ?? "",
        modules: request?.modules ?? [],
    });
    const [confirm, setConfirm] = useState(false);

    const recipient = employees.find((item) => String(item.id) === String(data.employee_id)) ?? (defaultEmployee && String(defaultEmployee.id) === String(data.employee_id) ? defaultEmployee : null);
    const approvalPic = (stepCode) => {
        const step = approvalTimeline.find((item) => item.step_code === stepCode);
        return step?.approver?.name ?? step?.actor ?? null;
    };

    const toggleModule = (value) => setData("modules", data.modules.includes(value) ? data.modules.filter((item) => item !== value) : [...data.modules, value]);
    const save = () => edit ? put(route("erp-requests.update", request.id)) : post(route("erp-requests.store"));
    const requestSave = (event) => { event.preventDefault(); setConfirm(true); };

    const Layout = embedded ? Fragment : AuthenticatedLayout;
    return <Layout {...(embedded ? {} : { title: edit ? "Ubah ERP Request" : "Buat ERP Request" })}>
        <Head title={edit ? "Ubah ERP Request" : "Buat ERP Request"} />
        <form onSubmit={readOnly ? (event) => event.preventDefault() : requestSave} className="worksheet-form medical-sheet space-y-6">
            <fieldset disabled={readOnly} className="block min-w-0 space-y-6 border-0 p-0">
            <WorkflowStepper currentStatus={request?.status ?? "draft"} title="Alur ERP Request" steps={[{ key: "draft", label: "Draf" }, { key: "submitted", label: "Diajukan" }, { key: "in_review", label: "Diketahui HOD", pic: approvalPic("hod") }, { key: "erp_review", label: "Reviewer ERP", pic: approvalPic("erp_review") }, { key: "processing", label: "Tinjauan IT", pic: approvalPic("it") }, { key: "approved", label: "Disetujui PM/GM", pic: approvalPic("pm_gm") }, { key: "completed", label: "Selesai" }]} />
            <header className="medical-sheet-header">
                <div className="medical-sheet-logo"><strong>BP</strong><small>PT. BORNEO PRIMA</small><em>COAL MINING &amp; TRADING</em></div>
                <div className="medical-sheet-title"><p>PT. BORNEO PRIMA</p><h1>ERP (D365 F&amp;O) ACCESS &amp; AUTHORIZATION REQUEST</h1></div>
                <div className="medical-sheet-number"><span>Nomor request:</span><strong>{request?.request_number ?? "Terbit saat draf disimpan"}</strong></div>
            </header>
            <ErrorSummary errors={errors} />

            <section className="medical-sheet-section">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Bagian 01 · Identitas</p><h2>Data pemohon dan pengguna sistem</h2><p>Pengaju adalah akun karyawan yang login. Akun ERP dapat ditujukan untuk diri sendiri atau rekan kerja.</p></div>
                {employees.length > 1 ? <div className="mb-3"><EmployeeLookup employees={employees} value={data.employee_id} onChange={(id) => setData("employee_id", id)} /><InputError message={errors.employee_id} className="mt-1" /></div> : <div className="medical-identity-grid"><div className="medical-master-note"><strong>Profil pengguna dari master</strong><span>{recipient ? `${recipient.employee_number} · ${recipient.name}` : "Pilih pengguna dari daftar karyawan."}</span></div><div><span>NIK</span><strong>{recipient?.employee_number ?? "—"}</strong></div><div><span>NAMA</span><strong>{recipient?.name ?? "—"}</strong></div><div><span>DEPARTEMEN</span><strong>{recipient?.department ?? "—"}</strong></div><div><span>JABATAN</span><strong>{recipient?.job_title ?? "—"}</strong></div></div>}
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    <div><label className={label}>Nama penerima (jika berbeda)</label><input className={input} value={data.recipient_name} onChange={(event) => setData("recipient_name", event.target.value)} placeholder="Kosongkan jika untuk diri sendiri" maxLength="150" /><InputError message={errors.recipient_name} /></div>
                    <div><label className={label}>Jabatan penerima</label><input className={input} value={data.recipient_position} onChange={(event) => setData("recipient_position", event.target.value)} maxLength="100" /><InputError message={errors.recipient_position} /></div>
                    <div><label className={label}>Site penerima</label><input className={input} value={data.recipient_site} onChange={(event) => setData("recipient_site", event.target.value)} maxLength="100" /><InputError message={errors.recipient_site} /></div>
                    <div><label className={label}>Cost code penerima</label><input className={input} value={data.recipient_cost_code} onChange={(event) => setData("recipient_cost_code", event.target.value)} maxLength="50" /><InputError message={errors.recipient_cost_code} /></div>
                </div>
            </section>

            <section className="medical-sheet-section">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Bagian 02 · Tipe Permohonan</p><h2>Jenis kebutuhan D365 F&amp;O</h2><p>Tentukan apakah permohonan berupa pembuatan akun baru, penyesuaian role, atau reset otorisasi.</p></div>
                <div className="grid gap-2 sm:grid-cols-3">{Object.entries(actionLabels).map(([value, text]) => <label key={value} className={`cursor-pointer rounded-lg border p-4 text-sm font-semibold ${data.action_type === value ? "border-blue-500 bg-blue-50 text-blue-900" : "border-slate-200 text-slate-700"}`}><input type="radio" name="action_type" checked={data.action_type === value} onChange={() => setData("action_type", value)} /> {text}</label>)}</div>
                <InputError message={errors.action_type} className="mt-1" />
                {data.action_type !== "new_account" && <div className="mt-3"><label className={label}>Username / ID akun ERP eksisting <b className="text-rose-600">*</b></label><input className={input} value={data.existing_erp_username} onChange={(event) => setData("existing_erp_username", event.target.value)} placeholder="Contoh: nama.user@perusahaan.com" maxLength="150" /><InputError message={errors.existing_erp_username} /></div>}
            </section>

            <section className="medical-sheet-section">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Bagian 03 · Deskripsi Kebutuhan</p><h2>Deskripsi kebutuhan dan pilihan modul</h2><p>Jelaskan kebutuhan pekerjaan. Pilihan modul opsional jika belum mengetahui struktur modul D365 F&amp;O.</p></div>
                <div><label className={label}>Deskripsi kebutuhan tugas / otorisasi pekerjaan <b className="text-rose-600">*</b></label><textarea required rows="4" className={input} value={data.business_purpose} onChange={(event) => setData("business_purpose", event.target.value)} placeholder="Contoh: Perlu menginput Purchase Order pembelian solar site dan melihat laporan utang supplier." /><InputError message={errors.business_purpose} /></div>
                <div className="mt-4"><p className={label}>Pilih modul D365 F&amp;O (opsional)</p><p className="mt-0.5 text-xs text-slate-500">Boleh dikosongkan dan akan ditinjau oleh Reviewer ERP.</p><div className="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">{(meta?.modules ?? Object.keys(moduleLabels)).map((value) => <label key={value} className="flex items-center gap-2 rounded-md border border-slate-200 p-3 text-xs font-semibold text-slate-700"><input type="checkbox" checked={data.modules.includes(value)} onChange={() => toggleModule(value)} /> {moduleLabels[value] ?? value}</label>)}</div></div>
                <InputError message={errors.modules} className="mt-1" />
            </section>

            {!readOnly && <div className="flex flex-wrap gap-3"><button disabled={processing} className="rounded-md bg-gray-900 px-5 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-60">{processing ? "Menyimpan…" : "Simpan Draft"}</button><Link href="/erp-requests" className="rounded-md border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</Link></div>}
            </fieldset>
        </form>
        <Modal show={confirm} onClose={() => setConfirm(false)} maxWidth="md"><div className="p-6"><h2 className="text-lg font-semibold text-gray-900">Konfirmasi penyimpanan</h2><p className="mt-2 text-sm text-gray-600">Draf ERP request akan disimpan. Pengajuan belum dikirim ke approval sampai Anda menekan Ajukan dari halaman detail.</p><div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setConfirm(false)} className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Periksa lagi</button><button type="button" disabled={processing} onClick={() => { setConfirm(false); save(); }} className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60">Ya, simpan</button></div></div></Modal>
        </Layout>;
}
