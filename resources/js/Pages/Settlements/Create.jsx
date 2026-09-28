import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import InputError from "@/Components/InputError";
import { Head, Link, useForm } from "@inertiajs/react";
import WorkflowStepper from "@/Components/WorkflowStepper";

const empty = () => ({
    transaction_date: "",
    description: "",
    category: "other",
    amount: "",
    receipt_no: "",
});

const categoryLabels = {
    transport: "Transport",
    hotel: "Hotel",
    meal: "Makan",
    other: "Lainnya",
};
const sourceLabels = {
    leave_request: "Cuti / Izin",
    travel_request: "Perjalanan Dinas",
    new_join: "New Join",
    other: "Lainnya",
};
const editableInput =
    "settlement-sheet-input block w-full text-sm focus:border-blue-500 focus:ring-blue-500";
const rupiah = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;

function ErrorList({ errors }) {
    const entries = Object.entries(errors ?? {});
    if (!entries.length) return null;

    return (
        <div className="settlement-error-list" role="alert">
            <strong>Periksa kembali isian berikut:</strong>
            <ul>{entries.slice(0, 12).map(([key, value]) => <li key={key}>{String(value)}</li>)}</ul>
        </div>
    );
}

export default function Create({ sources = [], categories = [], settlement = null, initialSource = {}, readOnly = false, embedded = false, approvalTimeline = [] }) {
    const { data, setData, post, put, processing, errors } = useForm({
        source_type: settlement?.source_type || initialSource?.source_type || "",
        source_id: settlement?.source_id || initialSource?.source_id || "",
        source_reference: settlement?.source_reference || "",
        items: settlement?.items?.map((item) => ({
            ...item,
            transaction_date: item.transaction_date?.slice(0, 10),
        })) || [empty()],
    });

    const selectedSource = sources.find(
        (source) => source.source_type === data.source_type && String(source.source_id) === String(data.source_id),
    );
    const isManualSource = ["new_join", "other"].includes(data.source_type);
    const advance = Number(selectedSource?.advance ?? settlement?.advance_amount ?? 0);
    const totalActual = data.items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
    const totals = Object.fromEntries(
        Object.keys(categoryLabels).map((category) => [
            category,
            data.items.reduce((sum, item) => sum + (item.category === category ? Number(item.amount) || 0 : 0), 0),
        ]),
    );
    const difference = advance - totalActual;
    const differenceLabel = difference > 0 ? "OVERPAYMENT" : difference < 0 ? "UNDERPAYMENT" : "BALANCED";
    const sourceEmployee = selectedSource?.employee ?? settlement?.employee ?? selectedSource?.employee_name;
    const sourceEmployeeName = typeof sourceEmployee === "object" ? sourceEmployee.name : sourceEmployee;
    const sourceEmployeeNumber = typeof sourceEmployee === "object" ? sourceEmployee.employee_number : null;
    const sourceDepartment = typeof sourceEmployee === "object" ? sourceEmployee.department : selectedSource?.department;

    const add = () => setData("items", [...data.items, empty()]);
    const remove = (index) => setData("items", data.items.filter((_, row) => row !== index));
    const update = (index, key, value) => setData(
        "items",
        data.items.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row),
    );
    const selectSource = (value) => {
        const [source_type, source_id] = value.split(":");
        setData((current) => ({ ...current, source_type, source_id, source_reference: "" }));
    };
    const submit = (event) => {
        event.preventDefault();
        if (readOnly) return;
        settlement ? put(route("settlements.update", settlement.id)) : post(route("settlements.store"));
    };

    const form = (
        <>
            <Head title={settlement ? "Ubah Settlement" : "Buat Settlement"} />
            <form onSubmit={submit} className="settlement-sheet worksheet-form space-y-6">
                <fieldset disabled={readOnly} className="block min-w-0 space-y-6 border-0 p-0">
                <WorkflowStepper currentStatus={settlement?.status ?? "draft"} title="Alur Settlement" steps={[{ key: "draft", label: "Draf Settlement" }, { key: "submitted", label: "Diajukan" }, { key: "in_review", label: "Review HRGA", pic: approvalTimeline.find((item) => item.step_code === "hrga")?.approver?.name }, { key: "finance_review", label: "Review Finance", pic: approvalTimeline.find((item) => item.step_code === "finance")?.approver?.name }, { key: "approved", label: "Disetujui" }, { key: "payment_processing", label: "Proses Pembayaran" }, { key: "completed", label: "Selesai" }]} />
                <header className="settlement-sheet-header">
                    <div className="settlement-sheet-logo"><strong>BP</strong><small>BORNEO PRIMA</small><em>eForm Internal</em></div>
                    <div className="settlement-sheet-title">
                        <p>FORM DECLARATION / CLAIM</p>
                        <h1>SETTLEMENT {data.source_type ? `— ${sourceLabels[data.source_type] ?? data.source_type}` : "ADVANCE"}</h1>
                    </div>
                    <div className="settlement-sheet-number"><span>NO. SETTLEMENT</span><strong>{settlement?.settlement_number ?? "DRAF BARU"}</strong></div>
                </header>

                <ErrorList errors={errors} />

                <section className="settlement-sheet-section">
                    <div className="worksheet-section-heading">
                        <p className="worksheet-eyebrow">01 / Data sumber</p>
                        <h2>Sumber advance dan data karyawan</h2>
                    </div>
                    <div className="settlement-source-grid">
                        <div className="settlement-source-select">
                            <label htmlFor="settlement-source">Sumber advance <b>*</b></label>
                            <select
                                id="settlement-source"
                                disabled={!!settlement}
                                required
                                value={`${data.source_type}:${data.source_id}`}
                                onChange={(event) => selectSource(event.target.value)}
                                className={editableInput}
                            >
                                <option value=":">Pilih sumber pengajuan</option>
                                {sources.map((source) => (
                                    <option key={`${source.source_type}:${source.source_id}`} value={`${source.source_type}:${source.source_id}`}>
                                        {sourceLabels[source.source_type] ?? source.source_type} · {source.number} · {rupiah(source.advance)}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.source_id || errors.source_type} className="mt-1" />
                        </div>
                        <div className="settlement-master-cell"><span>Modul sumber</span><strong>{data.source_type ? sourceLabels[data.source_type] ?? data.source_type : "—"}</strong></div>
                        <div className="settlement-master-cell"><span>Nomor referensi</span><strong>{selectedSource?.number ?? settlement?.source_reference ?? "—"}</strong></div>
                        <div className="settlement-master-cell"><span>Advance</span><strong>{rupiah(advance)}</strong></div>
                        {(sourceEmployeeName || sourceEmployeeNumber || sourceDepartment) && <>
                            <div className="settlement-master-cell"><span>Nama karyawan</span><strong>{sourceEmployeeName ?? "—"}</strong></div>
                            <div className="settlement-master-cell"><span>NIK</span><strong>{sourceEmployeeNumber ?? "—"}</strong></div>
                            <div className="settlement-master-cell"><span>Departemen</span><strong>{sourceDepartment ?? "—"}</strong></div>
                            <div className="settlement-master-cell"><span>Roster</span><strong>{sourceEmployee?.roster ?? "—"}</strong></div>
                            <div className="settlement-master-cell"><span>Level</span><strong>{sourceEmployee?.level ?? "—"}</strong></div>
                            <div className="settlement-master-cell"><span>Jabatan</span><strong>{sourceEmployee?.job_title ?? "—"}</strong></div>
                            <div className="settlement-master-cell"><span>POH status</span><strong>{sourceEmployee?.poh_status === "local" ? "LOKAL" : sourceEmployee?.poh_status === "non_local" ? "NON LOKAL" : "—"}</strong></div>
                            <div className="settlement-master-cell"><span>POH</span><strong>{sourceEmployee?.poh_city ?? "—"}</strong></div>
                        </>}
                    </div>
                    {isManualSource && (
                        <div className="settlement-reference-row">
                            <div>
                                <label htmlFor="source-reference">Nomor referensi sumber <b>*</b></label>
                                <input id="source-reference" required maxLength={100} value={data.source_reference} onChange={(event) => setData("source_reference", event.target.value)} className={editableInput} placeholder="Masukkan nomor dokumen / referensi" />
                                <InputError message={errors.source_reference} className="mt-1" />
                            </div>
                            <p>Sumber manual mengikuti workbook; advance tercatat Rp0 dan nomor referensi wajib diisi.</p>
                        </div>
                    )}
                </section>

                <section className="settlement-sheet-section">
                    <div className="settlement-cost-heading">
                        <div><p className="worksheet-eyebrow">02 / Realisasi biaya</p><h2>Rincian actual cost</h2></div>
                        <button type="button" onClick={add} className="settlement-add-row">+ Tambah item</button>
                    </div>
                    <InputError message={errors.items} className="mt-3" />
                    <div className="settlement-matrix-scroll">
                        <table className="settlement-cost-table">
                            <thead><tr><th>No.</th><th>Tanggal</th><th>Item biaya / nomor nota</th><th>Transport</th><th>Hotel</th><th>Makan</th><th>Lainnya</th><th>Total</th><th>Aksi</th></tr></thead>
                            <tbody>
                                {data.items.map((item, index) => (
                                    <tr key={index}>
                                        <td className="settlement-row-number">{index + 1}</td>
                                        <td><input required type="date" value={item.transaction_date} onChange={(event) => update(index, "transaction_date", event.target.value)} className={editableInput} /><InputError message={errors[`items.${index}.transaction_date`]} /></td>
                                        <td>
                                            <input required maxLength={255} value={item.description} onChange={(event) => update(index, "description", event.target.value)} className={editableInput} placeholder="Uraian biaya" />
                                            <select value={item.category} onChange={(event) => update(index, "category", event.target.value)} className={`${editableInput} mt-2`} aria-label={`Kategori item ${index + 1}`}>
                                                {categories.map((category) => <option key={category} value={category}>{categoryLabels[category] ?? category}</option>)}
                                            </select>
                                            <input maxLength={100} value={item.receipt_no ?? ""} onChange={(event) => update(index, "receipt_no", event.target.value)} className={`${editableInput} mt-2`} placeholder="No. nota (opsional)" />
                                            <InputError message={errors[`items.${index}.description`] || errors[`items.${index}.category`] || errors[`items.${index}.receipt_no`]} />
                                        </td>
                                        {Object.keys(categoryLabels).map((category) => <td key={category} className="settlement-category-cell">
                                            {item.category === category ? <><input required min="0" step="0.01" type="number" value={item.amount} onChange={(event) => update(index, "amount", event.target.value)} className={editableInput} placeholder="0" /><InputError message={errors[`items.${index}.amount`]} /></> : <span>—</span>}
                                        </td>)}
                                        <td className="settlement-row-total">{rupiah(item.amount)}</td>
                                        <td>{data.items.length > 1 && <button type="button" onClick={() => remove(index)} className="settlement-remove-row">Hapus</button>}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot><tr><th colSpan="3">Subtotal per kategori</th>{Object.keys(categoryLabels).map((category) => <td key={category}>{rupiah(totals[category])}</td>)}<td>{rupiah(totalActual)}</td><td /></tr></tfoot>
                        </table>
                    </div>
                    <p className="settlement-sheet-instruction">Isi nominal pada satu kolom kategori yang dipilih untuk setiap item. Total akhir tetap dihitung dan divalidasi ulang oleh server.</p>
                </section>

                <section className="settlement-reconciliation">
                    <div><p>03 / Rekonsiliasi sementara</p><span>Nilai ini adalah estimasi sebelum validasi server.</span></div>
                    <dl><div><dt>Total advance</dt><dd>{rupiah(advance)}</dd></div><div><dt>Total aktual</dt><dd>{rupiah(totalActual)}</dd></div><div className={`settlement-difference settlement-difference-${differenceLabel.toLowerCase()}`}><dt>{differenceLabel}</dt><dd>{rupiah(Math.abs(difference))}</dd></div></dl>
                </section>

                {!readOnly && <div className="flex flex-wrap justify-end gap-3">
                    <Link href={route("settlements.index")} className="settlement-cancel-button">Batal</Link>
                    <button disabled={processing} className="ui-button-primary px-5 py-2.5 text-sm disabled:opacity-60">{processing ? "Menyimpan…" : "Simpan Draft"}</button>
                </div>}
                </fieldset>
            </form>
        </>
    );

    return embedded ? form : <AuthenticatedLayout title={settlement ? "Ubah Settlement" : "Buat Settlement"}>{form}</AuthenticatedLayout>;
}
