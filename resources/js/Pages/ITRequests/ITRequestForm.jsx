import InputError from "@/Components/InputError";
import EmployeeLookup from "@/Components/EmployeeLookup";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Modal from "@/Components/Modal";
import { Head, Link, useForm } from "@inertiajs/react";
import { Fragment, useState } from "react";
import WorkflowStepper from "@/Components/WorkflowStepper";

const input = "mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500";
const label = "block text-xs font-semibold text-slate-600";
const deviceLabels = { laptop: "Laptop", desktop: "Desktop", workstation_cad: "Workstation / CAD", upgrade: "Upgrade perangkat" };
const reasonLabels = { not_suitable: "Tidak sesuai kebutuhan", damaged: "Rusak", other: "Lainnya" };
const priorityLabels = { normal: "Normal", high: "High", critical: "Critical" };
const accessoryLabels = { monitor_20: "Monitor 20 inci", external_hdd: "External hard drive", digital_camera: "Digital camera", printer_bw_laser: "Printer B/W laser", printer_colour_laser: "Printer colour laser", printer_a4_inkjet: "Printer A4 inkjet", printer_plotter: "Printer plotter", gps_unit: "GPS unit", rig_mobile_radio: "Rig/mobile radio", handheld_radio: "Handheld radio", ups_stabilizer: "UPS/stabilizer", wireless_keyboard_mouse: "Wireless keyboard/mouse", notebook_battery: "Notebook battery", notebook_power_adapter: "Notebook power adapter", other: "Lainnya" };

function ErrorSummary({ errors }) {
    const messages = Object.entries(errors ?? {});
    if (!messages.length) return null;
    return <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        <p className="font-semibold">Periksa kembali isian berikut:</p>
        <ul className="mt-2 list-disc space-y-1 pl-5">{messages.slice(0, 12).map(([key, message]) => <li key={key}>{String(message)}</li>)}</ul>
    </div>;
}

export default function ITRequestForm({ request = null, meta = {}, edit = false, readOnly = false, approvalTimeline = [], embedded = false }) {
    const employees = meta?.employees ?? [];
    const defaultEmployee = request?.employee ?? employees[0] ?? null;
    const { data, setData, post, put, processing, errors } = useForm({
        employee_id: request?.employee_id ? String(request.employee_id) : (employees.length === 1 ? String(employees[0].id) : ""),
        is_new_employee: Boolean(request?.is_new_employee),
        recipient_name: request?.recipient_name ?? "",
        recipient_id_number: request?.recipient_id_number ?? "",
        recipient_site: request?.recipient_site ?? "",
        recipient_cost_code: request?.recipient_cost_code ?? "",
        recipient_position: request?.recipient_position ?? "",
        recipient_effective_date: request?.recipient_effective_date ?? "",
        request_type: request?.request_type ?? "new_item",
        replacement_reason: request?.replacement_reason ?? "",
        replacement_note: request?.replacement_note ?? "",
        device_type: request?.device_type ?? "",
        special_specification: Boolean(request?.special_specification),
        description: request?.description ?? "",
        purpose: request?.purpose ?? "",
        software_standard: request?.software_standard ?? ["MS Office", "AntiVirus", "WinZip", "PDF Reader", "AnyDesk"],
        software_optional: request?.software_optional ?? [],
        software_name: "",
        accessories: request?.accessories ?? [],
        accessory_other_note: request?.accessory_other_note ?? "",
        needed_date: request?.needed_date ?? "",
        priority: request?.priority ?? "normal",
    });
    const [confirm, setConfirm] = useState(false);
    const [softwareLicense, setSoftwareLicense] = useState("new");

    const recipient = employees.find((item) => String(item.id) === String(data.employee_id)) ?? (defaultEmployee && String(defaultEmployee.id) === String(data.employee_id) ? defaultEmployee : null);
    const approvalPic = (stepCode) => {
        const step = approvalTimeline.find((item) => item.step_code === stepCode);
        return step?.approver?.name ?? step?.actor ?? null;
    };
    const steps = [
        { key: "draft", label: "Draf" }, { key: "submitted", label: "Diajukan" },
        { key: "in_review", label: "Diketahui HOD", pic: approvalPic("hod") },
        { key: "processing", label: "Tinjauan IT", pic: approvalPic("it") },
        { key: "approved", label: "Disetujui PM/GM", pic: approvalPic("pm_gm") },
        ...(data.special_specification ? [{ key: "high_spec_approval", label: "Disetujui COO/CEO", pic: approvalPic("coo_ceo") }] : []),
        { key: "completed", label: "Selesai" },
    ];

    const toggleAccessory = (code) => setData("accessories", data.accessories.includes(code) ? data.accessories.filter((item) => item !== code) : [...data.accessories, code]);
    const addSoftware = () => {
        const name = String(data.software_name ?? "").trim();
        if (!name || data.software_optional.some((item) => item.name === name)) { setData("software_name", ""); return; }
        setData("software_optional", [...data.software_optional, { name, license: softwareLicense }]);
        setData("software_name", "");
    };
    const removeSoftware = (name) => setData("software_optional", data.software_optional.filter((item) => item.name !== name));
    const save = () => {
        const payload = { ...data };
        delete payload.software_name;
        if (edit) {
            put(route("it-requests.update", request.id), { ...payload, _method: "put" });
        } else {
            post(route("it-requests.store"), payload);
        }
    };
    const requestSave = (event) => { event.preventDefault(); setConfirm(true); };

    const Layout = embedded ? Fragment : AuthenticatedLayout;
    return <Layout {...(embedded ? {} : { title: edit ? "Ubah IT Request" : "Buat IT Request" })}>
        <Head title={edit ? "Ubah IT Request" : "Buat IT Request"} />
        <form onSubmit={readOnly ? (event) => event.preventDefault() : requestSave} className="worksheet-form medical-sheet space-y-6">
            <fieldset disabled={readOnly} className="block min-w-0 space-y-6 border-0 p-0">
            <WorkflowStepper currentStatus={request?.status ?? "draft"} title="Alur IT Request" steps={steps} />
            <header className="medical-sheet-header">
                <div className="medical-sheet-logo"><strong>BP</strong><small>PT. BORNEO PRIMA</small><em>COAL MINING &amp; TRADING</em></div>
                <div className="medical-sheet-title"><p>PT. BORNEO PRIMA</p><h1>IT &amp; TELECOMMUNICATION ACQUISITION REQUEST</h1></div>
                <div className="medical-sheet-number"><span>Nomor request:</span><strong>{request?.request_number ?? "Terbit saat draf disimpan"}</strong></div>
            </header>
            <ErrorSummary errors={errors} />

            <section className="medical-sheet-section">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Bagian 01 · Identitas</p><h2>Data pengaju dan penerima</h2><p>Pengaju adalah akun karyawan yang login. Penerima dapat dari master karyawan atau karyawan baru yang belum terdaftar.</p></div>
                {employees.length > 1 ? <div className="mb-3"><EmployeeLookup employees={employees} value={data.employee_id} onChange={(id) => setData("employee_id", id)} /><InputError message={errors.employee_id} className="mt-1" /></div> : <div className="medical-identity-grid"><div className="medical-master-note"><strong>Profil penerima dari master</strong><span>{recipient ? `${recipient.employee_number} · ${recipient.name}` : "Pilih penerima dari daftar karyawan."}</span></div></div>}
                <label className="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" checked={Boolean(data.is_new_employee)} onChange={(event) => setData("is_new_employee", event.target.checked)} /> Penerima adalah karyawan baru / belum terdaftar</label>
                {Boolean(data.is_new_employee) && <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    <div><label className={label}>Nama penerima <b className="text-rose-600">*</b></label><input className={input} value={data.recipient_name} onChange={(event) => setData("recipient_name", event.target.value)} maxLength="150" /><InputError message={errors.recipient_name} /></div>
                    <div><label className={label}>NIK / ID (opsional)</label><input className={input} value={data.recipient_id_number} onChange={(event) => setData("recipient_id_number", event.target.value)} maxLength="50" /><InputError message={errors.recipient_id_number} /></div>
                    <div><label className={label}>Site <b className="text-rose-600">*</b></label><input className={input} value={data.recipient_site} onChange={(event) => setData("recipient_site", event.target.value)} maxLength="100" /><InputError message={errors.recipient_site} /></div>
                    <div><label className={label}>Cost code <b className="text-rose-600">*</b></label><input className={input} value={data.recipient_cost_code} onChange={(event) => setData("recipient_cost_code", event.target.value)} maxLength="50" /><InputError message={errors.recipient_cost_code} /></div>
                    <div><label className={label}>Jabatan yang direncanakan</label><input className={input} value={data.recipient_position} onChange={(event) => setData("recipient_position", event.target.value)} maxLength="100" /><InputError message={errors.recipient_position} /></div>
                    <div><label className={label}>Tanggal efektif masuk</label><input type="date" className={input} value={data.recipient_effective_date} onChange={(event) => setData("recipient_effective_date", event.target.value)} /><InputError message={errors.recipient_effective_date} /></div>
                </div>}
            </section>

            <section className="medical-sheet-section">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Bagian 02 · Jenis Permintaan</p><h2>Barang baru atau penggantian</h2><p>Pilih penggantian jika perangkat sebelumnya tidak lagi memenuhi kebutuhan kerja.</p></div>
                <div className="flex flex-wrap gap-3">
                    {[["new_item", "Barang baru"], ["replacement", "Penggantian"]].map(([value, text]) => <label key={value} className="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-900"><input type="radio" checked={data.request_type === value} onChange={() => setData("request_type", value)} /> {text}</label>)}
                </div>
                {data.request_type === "replacement" && <div className="mt-3 grid gap-3 sm:grid-cols-3">
                    {Object.entries(reasonLabels).map(([value, text]) => <label key={value} className="text-sm"><input type="radio" name="replacement_reason" checked={data.replacement_reason === value} onChange={() => setData("replacement_reason", value)} /> {text}</label>)}
                </div>}
                <InputError message={errors.replacement_reason} className="mt-1" />
                {data.request_type === "replacement" && <div className="mt-3"><label className={label}>Keterangan penggantian {data.replacement_reason === "other" ? <b className="text-rose-600">*</b> : ""}</label><textarea rows="2" className={input} value={data.replacement_note} onChange={(event) => setData("replacement_note", event.target.value)} placeholder="Jelaskan kondisi perangkat lama" /><InputError message={errors.replacement_note} /></div>}
            </section>

            <section className="medical-sheet-section">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Bagian 03 · Software</p><h2>Kebutuhan software</h2><p>Software standar terpasang sesuai kebijakan dan tidak dapat diubah. Tambahkan software opsional sesuai kebutuhan.</p></div>
                <div className="rounded-lg border border-slate-200 bg-slate-50 p-4"><p className="text-xs font-bold text-slate-600">SOFTWARE STANDAR (TERPASANG SESUAI KEBIJAKAN)</p><p className="mt-2 text-sm text-slate-700">{(meta?.software_standard ?? data.software_standard).join(", ")}.</p></div>
                <div className="mt-4"><label className={label}>Tambah software opsional</label><div className="mt-1 flex flex-col gap-2 sm:flex-row"><input value={data.software_name} onChange={(event) => setData("software_name", event.target.value)} className={input} placeholder="Nama software" maxLength="100" /><select value={softwareLicense} onChange={(event) => setSoftwareLicense(event.target.value)} className={input}><option value="existing">Lisensi tersedia</option><option value="new">Lisensi baru</option></select><button type="button" onClick={addSoftware} className="rounded-md border border-blue-300 px-4 py-2 text-sm font-semibold text-blue-700">+ Tambah</button></div></div>
                {data.software_optional.length > 0 && <ul className="mt-3 space-y-2">{data.software_optional.map((item) => <li key={item.name} className="flex items-center justify-between gap-2 rounded-lg border border-slate-200 p-3 text-sm"><span><strong>{item.name}</strong> <span className="text-slate-500">({item.license === "existing" ? "Lisensi tersedia" : "Lisensi baru"})</span></span><button type="button" onClick={() => removeSoftware(item.name)} className="text-xs font-semibold text-rose-600">Hapus</button></li>)}</ul>}
                <InputError message={errors.software_optional} className="mt-1" />
            </section>

            <section className="medical-sheet-section">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Bagian 04 · Perangkat</p><h2>Perangkat dan accessories</h2><p>Pilih satu perangkat utama. Spesifikasi teknis ditentukan internal oleh IT di luar sistem ini.</p></div>
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">{(meta?.devices ?? Object.keys(deviceLabels)).map((value) => <label key={value} className={`cursor-pointer rounded-lg border p-3 text-sm ${data.device_type === value ? "border-blue-500 bg-blue-50 font-semibold text-blue-900" : "border-slate-200 text-slate-700"}`}><input type="radio" name="device_type" checked={data.device_type === value} onChange={() => setData("device_type", value)} /> {deviceLabels[value] ?? value}</label>)}</div>
                <InputError message={errors.device_type} className="mt-1" />
                <div className="mt-4"><p className={label}>Accessories &amp; perangkat tambahan</p><div className="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">{(meta?.accessories ?? Object.keys(accessoryLabels)).map((value) => <label key={value} className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={data.accessories.includes(value)} onChange={() => toggleAccessory(value)} /> {accessoryLabels[value] ?? value}</label>)}</div></div>
                <InputError message={errors.accessories} className="mt-1" />
                {data.accessories.includes("other") && <div className="mt-3"><label className={label}>Jelaskan perangkat tambahan lainnya <b className="text-rose-600">*</b></label><input className={input} value={data.accessory_other_note} onChange={(event) => setData("accessory_other_note", event.target.value)} placeholder="Contoh: docking station USB-C" /><InputError message={errors.accessory_other_note} /></div>}
                <div className="mt-4 rounded-lg border border-slate-200 p-4"><label className="flex cursor-pointer items-center gap-2 text-sm font-semibold"><input type="checkbox" checked={Boolean(data.special_specification)} onChange={(event) => setData("special_specification", event.target.checked)} /> Ada kebutuhan khusus untuk spesifikasi perangkat</label><p className="mt-1 text-xs text-slate-500">Jika dipilih, pengajuan otomatis memerlukan persetujuan COO/CEO setelah PM/GM.</p></div>
                {Boolean(data.special_specification) && <div className="mt-3 grid gap-4 lg:grid-cols-2"><div><label className={label}>Deskripsi perangkat <b className="text-rose-600">*</b></label><textarea required rows="4" className={input} value={data.description} onChange={(event) => setData("description", event.target.value)} placeholder="Contoh: Laptop untuk pemodelan CAD." /><InputError message={errors.description} /></div><div><label className={label}>Tujuan utama penggunaan <b className="text-rose-600">*</b></label><textarea required rows="4" className={input} value={data.purpose} onChange={(event) => setData("purpose", event.target.value)} placeholder="Jelaskan aktivitas kerja yang memerlukan spesifikasi khusus." /><InputError message={errors.purpose} /></div></div>}
            </section>

            <section className="medical-sheet-section">
                <div className="worksheet-section-heading mb-4"><p className="worksheet-eyebrow">Bagian 05 · Jadwal</p><h2>Tanggal dibutuhkan dan prioritas</h2><p>Prioritas membantu IT merencanakan pemenuhan, bukan menggantikan approval.</p></div>
                <div className="grid gap-4 sm:grid-cols-2"><div><label className={label}>Tanggal dibutuhkan <b className="text-rose-600">*</b></label><input required type="date" className={input} value={data.needed_date} onChange={(event) => setData("needed_date", event.target.value)} /><InputError message={errors.needed_date} /></div><div><label className={label}>Prioritas <b className="text-rose-600">*</b></label><select required className={input} value={data.priority} onChange={(event) => setData("priority", event.target.value)}>{(meta?.priorities ?? Object.keys(priorityLabels)).map((value) => <option key={value} value={value}>{priorityLabels[value] ?? value}</option>)}</select><InputError message={errors.priority} /></div></div>
            </section>

            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><p className="font-semibold">Catatan</p><p className="mt-1">Persetujuan ini bukan persetujuan pembelian. Setelah mendapat persetujuan akhir, request diteruskan kepada IT untuk proses lanjutan.</p></div>

            {!readOnly && <div className="flex flex-wrap gap-3"><button disabled={processing} className="rounded-md bg-gray-900 px-5 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-60">{processing ? "Menyimpan…" : "Simpan Draft"}</button><Link href="/it-requests" className="rounded-md border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</Link></div>}
            </fieldset>
        </form>
        <Modal show={confirm} onClose={() => setConfirm(false)} maxWidth="md"><div className="p-6"><h2 className="text-lg font-semibold text-gray-900">Konfirmasi penyimpanan</h2><p className="mt-2 text-sm text-gray-600">Draf IT request akan disimpan. Pengajuan belum dikirim ke approval sampai Anda menekan Ajukan dari halaman detail.</p><div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setConfirm(false)} className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Periksa lagi</button><button type="button" disabled={processing} onClick={() => { setConfirm(false); save(); }} className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60">Ya, simpan</button></div></div></Modal>
        </Layout>;
}
