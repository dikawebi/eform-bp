import InputError from "@/Components/InputError";
import EmployeeLookup from "@/Components/EmployeeLookup";
import Modal from "@/Components/Modal";
import WorkflowStepper from "@/Components/WorkflowStepper";
import { Link } from "@inertiajs/react";
import { useMemo, useState } from "react";
import {
    estimasiHari,
    formatRupiah,
    labelBiaya,
    labelPeriode,
} from "./helpers";

const inputCls =
    "mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500";
const labelCls = "block text-xs font-medium text-gray-600";
const estimasiMalam = (checkIn, checkOut) => {
    if (!checkIn || !checkOut) return null;
    const utcDate = (value) =>
        Date.UTC(
            ...value
                .split("-")
                .map((part, index) => Number(part) - (index === 1 ? 1 : 0)),
        );
    return Math.max(0, (utcDate(checkOut) - utcDate(checkIn)) / 86400000);
};

function PeriodeRow({
    index,
    baris,
    opsiPeriode,
    errors,
    bisaHapus,
    onUbah,
    onHapus,
}) {
    const hari = estimasiHari(baris.start_date, baris.end_date);
    return (
        <div className="worksheet-row p-4">
            <div className="worksheet-row-header -mx-4 -mt-4 mb-4 flex items-center justify-between gap-2 rounded-t-lg px-4 py-2.5">
                <p className="text-sm font-semibold text-gray-800">
                    Periode {index + 1}
                    <span className="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                        Estimasi {hari} hari
                    </span>
                </p>
                {bisaHapus && (
                    <button
                        type="button"
                        onClick={onHapus}
                        className="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"
                    >
                        Hapus
                    </button>
                )}
            </div>
            <div className="worksheet-fields worksheet-fields-period">
                <div className="worksheet-cell worksheet-col-span-2">
                    <label className={labelCls}>Kategori periode</label>
                    <select
                        value={baris.category}
                        onChange={(e) => onUbah("category", e.target.value)}
                        className={inputCls}
                    >
                        {(opsiPeriode ?? []).map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                    <InputError
                        message={errors[`periods.${index}.category`]}
                        className="mt-1"
                    />
                </div>
                <div>
                    <label className={labelCls}>Tanggal mulai</label>
                    <input
                        type="date"
                        value={baris.start_date}
                        onChange={(e) => onUbah("start_date", e.target.value)}
                        className={inputCls}
                    />
                    <InputError
                        message={errors[`periods.${index}.start_date`]}
                        className="mt-1"
                    />
                </div>
                <div>
                    <label className={labelCls}>Tanggal selesai</label>
                    <input
                        type="date"
                        value={baris.end_date}
                        onChange={(e) => onUbah("end_date", e.target.value)}
                        className={inputCls}
                    />
                    <InputError
                        message={errors[`periods.${index}.end_date`]}
                        className="mt-1"
                    />
                </div>
                <div className="worksheet-cell worksheet-col-span-2">
                    <label className={labelCls}>
                        Catatan periode (opsional)
                    </label>
                    <input
                        value={baris.notes ?? ""}
                        onChange={(e) => onUbah("notes", e.target.value)}
                        className={inputCls}
                        placeholder="cth. Perjalanan pulang ke POH"
                        maxLength={1000}
                    />
                    <InputError
                        message={errors[`periods.${index}.notes`]}
                        className="mt-1"
                    />
                </div>
            </div>
        </div>
    );
}

function CutiPeriodRow({ index, baris, opsiPeriode, errors, onUbah, onUbahTanggalOnsite }) {
    const isOnsiteAwal = baris.category === "onsite";
    const hari = estimasiHari(baris.start_date, baris.end_date);
    return (
        <div className="leave-period-grid-row">
            <div className="leave-period-category">
                <span className="leave-period-label">{isOnsiteAwal ? "ONSITE (setelah cuti sebelumnya)" : labelPeriode(opsiPeriode, baris.category)}</span>
                <input type="hidden" value={baris.category} readOnly />
                <InputError message={errors[`periods.${index}.category`]} />
            </div>
            <div>
                <label>{isOnsiteAwal ? "Tgl" : "Tgl awal"}</label>
                <input type="date" value={baris.start_date} onChange={(event) => isOnsiteAwal ? onUbahTanggalOnsite(event.target.value) : onUbah("start_date", event.target.value)} className={inputCls} />
                <InputError message={errors[`periods.${index}.start_date`]} />
            </div>
            <div>
                {isOnsiteAwal ? <span className="text-xs text-gray-500">Tanggal hanya sebagai penanda dan tidak masuk total hari cuti.</span> : <><label>Tgl akhir</label><input type="date" value={baris.end_date} onChange={(event) => onUbah("end_date", event.target.value)} className={inputCls} /><InputError message={errors[`periods.${index}.end_date`]} /></>}
            </div>
            <div className="leave-period-total">
                <span>Jumlah hari</span>
                <strong>{isOnsiteAwal ? "Tidak dihitung" : hari > 0 ? `${hari} Hari` : "—"}</strong>
            </div>
        </div>
    );
}

function CutiCostRow({ item, index, category, errors, eligible, onChange, onRemove }) {
    const checkIn = item.check_in_date ?? "";
    const checkOut = item.check_out_date ?? "";
    const nights = checkIn && checkOut ? estimasiMalam(checkIn, checkOut) : Number(item.quantity) || 0;
    const quantity = category === "hotel" ? nights : Number(item.quantity) || 0;
    const amount = eligible ? quantity * (Number(item.unit_price) || 0) : 0;
    const label = { land_transport: "Travel", hotel: "Penginapan", meal: "Makan", other: "Lainnya" }[category] ?? category;
    const description = item.description || label;
    const set = (field, value) => onChange({ ...item, description, [field]: value });
    const setDate = (field, value) => {
        const next = { ...item, description, [field]: value };
        if (category === "hotel") next.quantity = estimasiMalam(next.check_in_date, next.check_out_date) ?? next.quantity;
        onChange(next);
    };
    const text = (title, field, placeholder = "") => <div className="worksheet-cell"><label>{title}</label><input value={item[field] ?? ""} onChange={(event) => set(field, event.target.value)} className={inputCls} placeholder={placeholder} /></div>;
    const money = (title, field = "unit_price") => <div className="worksheet-cell"><label>{title} <b>*</b></label><input required type="number" min="0" step="0.01" value={item[field] ?? 0} onChange={(event) => set(field, event.target.value)} className={inputCls} /></div>;
    return (
        <article className="leave-cost-row">
            <header><span className="worksheet-row-index">{index + 1}</span><strong>{label}</strong><span>Estimasi {formatRupiah(amount)}</span><button type="button" onClick={onRemove}>Hapus</button></header>
            <div className={`worksheet-fields leave-cost-fields leave-cost-fields-${category}`}>
                {category === "land_transport" && <><div className="worksheet-cell"><label>Tanggal <b>*</b></label><input required type="date" value={item.service_date ?? ""} onChange={(event) => set("service_date", event.target.value)} className={inputCls} /></div>{text("Lokasi awal", "origin", "Rumah / kota asal")}{text("Tujuan", "destination", "Bandara / site")}{money("Biaya (Rp)")}</>}
                {category === "hotel" && <>{text("Lokasi", "description", "Hotel / mess")}{<div className="worksheet-cell"><label>Check-in <b>*</b></label><input required type="date" value={checkIn} onChange={(event) => setDate("check_in_date", event.target.value)} className={inputCls} /></div>}{<div className="worksheet-cell"><label>Check-out <b>*</b></label><input required type="date" value={checkOut} onChange={(event) => setDate("check_out_date", event.target.value)} className={inputCls} /></div>}{money("Biaya (Rp)")}</>}
                {(category === "meal" || category === "other") && <>{text(category === "meal" ? "Item" : "Keperluan", "description", category === "meal" ? "Makan siang / malam" : "Biaya lainnya")}{money("Jumlah", "quantity")}{money("Harga (Rp)")}</>}
            </div>
        </article>
    );
}

function BiayaRow({
    index,
    baris,
    opsiBiaya,
    errors,
    eligible,
    onUbah,
    onHapus,
}) {
    const malam =
        baris.category === "hotel"
            ? estimasiMalam(baris.check_in_date, baris.check_out_date)
            : null;
    const qty = malam ?? (Number(baris.quantity) || 0);
    const harga = Number(baris.unit_price) || 0;
    const jumlah = eligible ? qty * harga : 0;
    return (
        <div className="worksheet-row p-4">
            <div className="worksheet-row-header -mx-4 -mt-4 mb-4 flex items-center justify-between gap-2 rounded-t-lg px-4 py-2.5">
                <p className="text-sm font-semibold text-gray-800">
                    Biaya {index + 1}
                    <span className="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                        Estimasi {formatRupiah(jumlah)}
                    </span>
                </p>
                <button
                    type="button"
                    onClick={onHapus}
                    className="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"
                >
                    Hapus
                </button>
            </div>
            <div className="worksheet-fields worksheet-fields-cost">
                <div className="worksheet-cell">
                    <label className={labelCls}>Kategori biaya</label>
                    <select
                        value={baris.category}
                        onChange={(e) => onUbah("category", e.target.value)}
                        className={inputCls}
                    >
                        {(opsiBiaya ?? []).map((o) => (
                            <option key={o.value} value={o.value}>
                                {labelBiaya(null, o.value) === o.value
                                    ? o.value
                                    : o.label}
                            </option>
                        ))}
                    </select>
                    <InputError
                        message={errors[`cost_items.${index}.category`]}
                        className="mt-1"
                    />
                </div>
                {baris.category === "land_transport" && (
                    <>
                        <div>
                            <label className={labelCls}>
                                Lokasi awal (opsional)
                            </label>
                            <input
                                value={baris.origin ?? ""}
                                onChange={(e) =>
                                    onUbah("origin", e.target.value)
                                }
                                className={inputCls}
                                placeholder="Rumah / kota asal"
                            />
                            <InputError
                                message={errors[`cost_items.${index}.origin`]}
                            />
                        </div>
                        <div>
                            <label className={labelCls}>
                                Tujuan (opsional)
                            </label>
                            <input
                                value={baris.destination ?? ""}
                                onChange={(e) =>
                                    onUbah("destination", e.target.value)
                                }
                                className={inputCls}
                                placeholder="Bandara / site"
                            />
                            <InputError
                                message={
                                    errors[`cost_items.${index}.destination`]
                                }
                            />
                        </div>
                        <div>
                            <label className={labelCls}>
                                Tujuan flight (opsional)
                            </label>
                            <input
                                value={baris.flight_destination ?? ""}
                                onChange={(e) =>
                                    onUbah("flight_destination", e.target.value)
                                }
                                className={inputCls}
                                placeholder="Kota / bandara"
                            />
                            <InputError
                                message={
                                    errors[
                                        `cost_items.${index}.flight_destination`
                                    ]
                                }
                            />
                        </div>
                        <div>
                            <label className={labelCls}>
                                Jam berangkat (opsional)
                            </label>
                            <input
                                type="time"
                                value={baris.departure_time ?? ""}
                                onChange={(e) =>
                                    onUbah("departure_time", e.target.value)
                                }
                                className={inputCls}
                            />
                            <InputError
                                message={
                                    errors[`cost_items.${index}.departure_time`]
                                }
                            />
                        </div>
                        <div>
                            <label className={labelCls}>
                                Tanggal perjalanan (opsional)
                            </label>
                            <input
                                type="date"
                                value={baris.service_date ?? ""}
                                onChange={(e) =>
                                    onUbah("service_date", e.target.value)
                                }
                                className={inputCls}
                            />
                            <InputError
                                message={
                                    errors[`cost_items.${index}.service_date`]
                                }
                            />
                        </div>
                    </>
                )}
                {baris.category === "hotel" && (
                    <>
                        <div>
                            <label className={labelCls}>
                                Check-in (opsional)
                            </label>
                            <input
                                type="date"
                                value={baris.check_in_date ?? ""}
                                onChange={(e) =>
                                    onUbah("check_in_date", e.target.value)
                                }
                                className={inputCls}
                            />
                            <InputError
                                message={
                                    errors[`cost_items.${index}.check_in_date`]
                                }
                            />
                        </div>
                        <div>
                            <label className={labelCls}>
                                Check-out (opsional)
                            </label>
                            <input
                                type="date"
                                value={baris.check_out_date ?? ""}
                                onChange={(e) =>
                                    onUbah("check_out_date", e.target.value)
                                }
                                className={inputCls}
                            />
                            <InputError
                                message={
                                    errors[`cost_items.${index}.check_out_date`]
                                }
                            />
                        </div>
                    </>
                )}
                <div>
                    <label className={labelCls}>Keterangan</label>
                    <input
                        value={baris.description ?? ""}
                        onChange={(e) => onUbah("description", e.target.value)}
                        className={inputCls}
                        placeholder="cth. Bus site – kota"
                        maxLength={255}
                    />
                    <InputError
                        message={errors[`cost_items.${index}.description`]}
                        className="mt-1"
                    />
                </div>
                <div>
                    <label className={labelCls}>
                        {malam === null
                            ? "Kuantitas"
                            : "Jumlah malam (dihitung dari tanggal)"}
                    </label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={malam ?? baris.quantity}
                        readOnly={malam !== null}
                        onChange={(e) => onUbah("quantity", e.target.value)}
                        className={inputCls}
                    />
                    <InputError
                        message={errors[`cost_items.${index}.quantity`]}
                        className="mt-1"
                    />
                </div>
                <div>
                    <label className={labelCls}>Harga satuan (Rp)</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={baris.unit_price}
                        onChange={(e) => onUbah("unit_price", e.target.value)}
                        className={inputCls}
                    />
                    <InputError
                        message={errors[`cost_items.${index}.unit_price`]}
                        className="mt-1"
                    />
                </div>
            </div>
        </div>
    );
}

/**
 * Form cuti/izin bersama untuk Create & Edit.
 * Angka yang tampil = estimasi lokal; total final dihitung server.
 */
export default function LeaveForm({
    data,
    setData,
    errors,
    processing,
    meta,
    karyawanFallback = null,
    submitLabel = "Simpan",
    batalHref = "/leaves",
    onSubmit,
    currentStatus = "draft",
    readOnly = false,
    approvalTimeline = [],
}) {
    const [konfirmasi, setKonfirmasi] = useState(false);

    const daftarKaryawan = meta?.employees ?? [];
    const karyawanTerpilih =
        daftarKaryawan.find((k) => String(k.id) === String(data.employee_id)) ??
        (karyawanFallback && String(karyawanFallback.id) === String(data.employee_id)
            ? karyawanFallback : null);
    const adalahLokal = karyawanTerpilih?.poh_status === "local";
    const biayaEligible = !adalahLokal;

    const opsiPeriode = meta?.period_categories ?? [];
    const opsiBiaya = meta?.cost_categories ?? [];

    const totalHari = useMemo(
        () =>
            (data.periods ?? []).reduce(
                (acc, p) => acc + (p.category === "onsite" ? 0 : estimasiHari(p.start_date, p.end_date)),
                0,
            ),
        [data.periods],
    );
    const perluPeriksaTanggal = (data.periods ?? []).some((periode) =>
        (periode.start_date && !periode.end_date) ||
        (!periode.start_date && periode.end_date) ||
        (periode.start_date && periode.end_date && estimasiHari(periode.start_date, periode.end_date) === 0),
    );

    const totalAdvance = useMemo(() => {
        if (!biayaEligible) return 0;
        return (data.cost_items ?? []).reduce(
            (acc, it) =>
                acc + (Number(it.quantity) || 0) * (Number(it.unit_price) || 0),
            0,
        );
    }, [data.cost_items, biayaEligible]);

    const ubahPeriode = (index, field, value) => {
        const next = [...(data.periods ?? [])];
        next[index] = { ...next[index], [field]: value };
        setData("periods", next);
    };

    const ubahBiaya = (index, field, value) => {
        const next = [...(data.cost_items ?? [])];
        next[index] = { ...next[index], [field]: value };
        if (
            next[index].category === "hotel" &&
            (field === "check_in_date" || field === "check_out_date")
        ) {
            const nights = estimasiMalam(
                next[index].check_in_date,
                next[index].check_out_date,
            );
            if (nights !== null) next[index].quantity = nights;
        }
        setData("cost_items", next);
    };

    const tambahBiaya = (category = opsiBiaya[0]?.value ?? "land_transport") => {
        setData("cost_items", [
            ...(data.cost_items ?? []),
            {
                category,
                description: { land_transport: "Travel", hotel: "Penginapan", meal: "Makan", other: "Lainnya" }[category] ?? "Biaya",
                quantity: 1,
                unit_price: 0,
            },
        ]);
    };

    const hapusBiaya = (index) => {
        setData(
            "cost_items",
            (data.cost_items ?? []).filter((_, i) => i !== index),
        );
    };

    const daftarError = Object.entries(errors ?? {});
    const kirim = (e) => {
        e.preventDefault();
        setKonfirmasi(true);
    };

    return (
        <form onSubmit={readOnly ? (event) => event.preventDefault() : kirim} className="worksheet-form leave-sheet space-y-6">
            <fieldset disabled={readOnly} className="block min-w-0 space-y-6 border-0 p-0">
            <WorkflowStepper
                currentStatus={currentStatus}
                title="Alur Cuti / Izin"
                steps={[{ key: "draft", label: "Draf" }, { key: "submitted", label: "Diajukan" }, { key: "in_review", label: "Dalam Review", pic: approvalTimeline.find((item) => item.step_code === "supervisor")?.approver?.name }, { key: "approved", label: "Disetujui", pic: approvalTimeline.find((item) => item.step_code === "hod")?.approver?.name }, { key: "processing", label: "Diproses HRGA", pic: approvalTimeline.find((item) => item.step_code === "hrga")?.approver?.name }, { key: "advance_paid", label: "Advance Dibayar" }, { key: "settlement_required", label: "Perlu Settlement" }, { key: "completed", label: "Selesai" }]}
            />
            <header className="leave-sheet-header">
                <div className="leave-sheet-logo"><strong>BP</strong><small>PT. BORNEO PRIMA</small><em>COAL MINING &amp; TRADING</em></div>
                <div className="leave-sheet-title"><p>PT. BORNEO PRIMA</p><h2>FORMULIR PENGAJUAN CUTI / IZIN</h2></div>
                <div className="leave-sheet-number"><span>Formulir Nomor:</span><strong>002/BP/HRD/BUA/FORM/IX/2025</strong></div>
            </header>
            {daftarError.length > 0 && (
                <div className="rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p className="text-sm font-semibold text-rose-800">
                        Periksa kembali isian berikut:
                    </p>
                    <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-rose-700">
                        {daftarError.slice(0, 12).map(([key, msg]) => (
                            <li key={key}>{String(msg)}</li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Informasi pengajuan */}
            <section className="leave-sheet-section employee-lookup-section">
                <div className="worksheet-section-heading mb-4">
                    <p className="worksheet-eyebrow">
                        Sheet Cuti / Izin · Bagian 01
                    </p>
                    <h3 className="text-sm font-semibold text-gray-900">
                        Data Karyawan & Pengajuan
                    </h3>
                    <p className="mt-0.5 text-xs text-gray-500">
                        Profil ditarik dari master karyawan; lengkapi informasi
                        pengajuan seperti pada formulir sumber.
                    </p>
                </div>
                <div className="leave-sheet-profile-grid">
                    {daftarKaryawan.length > 1 ? (
                        <div className="employee-lookup-cell">
                            <EmployeeLookup
                                employees={daftarKaryawan}
                                value={data.employee_id}
                                onChange={(id) => setData("employee_id", id)}
                            />
                            <InputError
                                message={errors.employee_id}
                                className="mt-1"
                            />
                        </div>
                    ) : (
                        <div className="leave-sheet-master-note">
                            <strong>{karyawanTerpilih?.employee_number ?? "—"} · {karyawanTerpilih?.name ?? "Karyawan"}</strong>
                            <span>Data identitas terisi otomatis dari master karyawan.</span>
                        </div>
                    )}
                    <div className="leave-sheet-value-cell"><span>NIK</span><strong>{karyawanTerpilih?.employee_number ?? "—"}</strong></div>
                    <div className="leave-sheet-value-cell"><span>ROSTER</span><strong>{karyawanTerpilih?.roster ?? "—"}</strong></div>
                    <div className="leave-sheet-value-cell"><span>NAMA</span><strong>{karyawanTerpilih?.name ?? "—"}</strong></div>
                    <div className="leave-sheet-value-cell"><span>LEVEL</span><strong>{karyawanTerpilih?.level ?? "—"}</strong></div>
                    <div className="leave-sheet-value-cell"><span>DEPT / JABATAN</span><strong>{karyawanTerpilih?.department ?? "—"}</strong></div>
                    <div className="leave-sheet-value-cell"><span>POH STATUS</span><strong>{karyawanTerpilih ? (karyawanTerpilih.poh_status === "local" ? "LOKAL" : "NON LOKAL") : "—"}</strong></div>
                    <div className="leave-sheet-value-cell"><span>JABATAN</span><strong>{karyawanTerpilih?.job_title ?? "—"}</strong></div>
                    <div className="leave-sheet-value-cell"><span>POH</span><strong>{karyawanTerpilih?.poh_city ?? "—"}{karyawanTerpilih?.poh_province ? ` - ${karyawanTerpilih.poh_province}` : ""}</strong></div>
                </div>
            </section>

            {/* Section A: rincian hari cuti */}
            <section className="leave-sheet-section">
                <div className="worksheet-section-heading mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p className="worksheet-eyebrow">
                            A. RINCIAN HARI CUTI
                        </p>
                        <h3 className="text-sm font-semibold text-gray-900">
                            Periode Cuti / Izin
                        </h3>
                        <p className="mt-0.5 text-xs text-gray-500">
                            Isi tanggal pada kategori yang digunakan. Estimasi hari
                            dihitung inklusif — final dari server.
                        </p>
                    </div>
                    <span className="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                        Estimasi total {totalHari} hari
                    </span>
                </div>
                <InputError message={errors.periods} className="mt-2" />
                {perluPeriksaTanggal && (
                    <p className="leave-date-review-note">PERIKSA TANGGAL PENGAJUAN</p>
                )}
                <div className="mt-4 space-y-3">
                    {(data.periods ?? []).filter((baris) => baris.category === "onsite").map((baris) => {
                        const i = (data.periods ?? []).indexOf(baris);
                        return (
                        <CutiPeriodRow
                            key={i}
                            index={i}
                            baris={baris}
                            opsiPeriode={opsiPeriode}
                            errors={errors}
                            onUbah={(f, v) => ubahPeriode(i, f, v)}
                            onUbahTanggalOnsite={(value) => {
                                const next = [...(data.periods ?? [])];
                                next[i] = { ...next[i], start_date: value, end_date: value };
                                setData("periods", next);
                            }}
                        />
                        );
                    })}
                    <div className="leave-period-grid-row mt-3">
                    <div className="leave-period-category"><span className="leave-period-label">HARI TERAKHIR KERJA</span></div>
                    <div><label>Tgl</label><input type="date" value={data.last_working_date ?? ""} onChange={(event) => setData("last_working_date", event.target.value)} className={inputCls} /><InputError message={errors.last_working_date} /></div>
                    <div className="text-xs text-gray-500">Penanda hari kerja terakhir sebelum cuti.</div>
                    <div className="leave-period-total"><span>Jumlah hari</span><strong>Tidak dihitung</strong></div>
                    </div>
                    {(data.periods ?? []).filter((baris) => baris.category !== "onsite").map((baris) => {
                        const i = (data.periods ?? []).indexOf(baris);
                        return (
                            <CutiPeriodRow
                                key={i}
                                index={i}
                                baris={baris}
                                opsiPeriode={opsiPeriode}
                                errors={errors}
                                onUbah={(f, v) => ubahPeriode(i, f, v)}
                                onUbahTanggalOnsite={() => {}}
                            />
                        );
                    })}
                </div>
                <div className="leave-period-grid-row mt-3">
                    <div className="leave-period-category"><span className="leave-period-label">ONSITE (setelah cuti)</span></div>
                    <div><label>Tgl</label><input type="date" value={data.onsite_date ?? ""} onChange={(event) => setData("onsite_date", event.target.value)} className={inputCls} /><InputError message={errors.onsite_date} /></div>
                    <div className="text-xs text-gray-500">Penanda kembali onsite setelah cuti.</div>
                    <div className="leave-period-total"><span>Jumlah hari</span><strong>Tidak dihitung</strong></div>
                </div>
                {adalahLokal && (
                    <p className="leave-local-policy-note">
                        Karyawan lokal tidak mendapat hari perjalanan dan uang transport.
                    </p>
                )}
                <div className="leave-sheet-reason-row">
                    <label className={labelCls}>Alasan izin / catatan</label>
                    <textarea
                        value={data.reason ?? ""}
                        onChange={(e) => setData("reason", e.target.value)}
                        rows={2}
                        className={inputCls}
                        placeholder="Contoh: Cuti istri melahirkan 2 hari (15–16 Oktober 2026)"
                    />
                    <InputError message={errors.reason} className="mt-1" />
                </div>
            </section>

            {/* Biaya */}
            <section className="leave-sheet-section">
                <div className="worksheet-section-heading mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p className="worksheet-eyebrow">
                            B. RINCIAN BIAYA CUTI
                        </p>
                        <h3 className="text-sm font-semibold text-gray-900">
                            Biaya Perjalanan &amp; Akomodasi (opsional)
                        </h3>
                        <p className="mt-0.5 text-xs text-gray-500">
                            Jumlah per baris = kuantitas × harga satuan.
                            Estimasi — final dari server.
                        </p>
                    </div>
                    <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        Estimasi {formatRupiah(totalAdvance)}
                    </span>
                </div>

                {adalahLokal && (
                    <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <p className="font-semibold">
                            Karyawan berstatus lokal
                        </p>
                        <p className="mt-0.5">
                            Sesuai kebijakan, biaya perjalanan karyawan lokal
                            tidak eligible sehingga advance bernilai Rp0. Baris
                            biaya tetap tersimpan sebagai arsip, tetapi total
                            final ditentukan server.
                        </p>
                    </div>
                )}
                {!adalahLokal && (
                    <div className="mt-3 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
                        Karyawan berstatus non-lokal: biaya dihitung dari
                        kuantitas × harga satuan untuk setiap baris.
                    </div>
                )}

                <InputError message={errors.cost_items} className="mt-2" />
                <div className="leave-cost-sections">
                    {[
                        { category: "land_transport", code: "B", title: "TRAVEL (JALUR DARAT)" },
                        { category: "flight", code: "C", title: "TIKET PESAWAT" },
                        { category: "hotel", code: "D", title: "PENGINAPAN (HOTEL / MESS PURCA)" },
                        { category: "meal", code: "E", title: "MAKAN" },
                    ].map((section) => {
                        const rows = (data.cost_items ?? []).map((item, index) => ({ item, index })).filter(({ item }) => item.category === section.category);
                        return (
                            <section key={section.category} className="leave-cost-section">
                                <header><h4>{section.code}. {section.title}</h4>{section.category !== "flight" && <button type="button" onClick={() => tambahBiaya(section.category)}>+ Tambah baris</button>}</header>
                                {section.category === "flight" ? <p className="leave-cost-empty">Tiket pesawat tidak berlaku untuk pengajuan cuti/izin.</p> : rows.length ? rows.map(({ item, index }) => <CutiCostRow key={index} index={index} item={item} category={section.category} errors={errors} eligible={biayaEligible} onChange={(next) => setData("cost_items", (data.cost_items ?? []).map((row, rowIndex) => rowIndex === index ? next : row))} onRemove={() => hapusBiaya(index)} />) : <p className="leave-cost-empty">Belum ada rincian biaya pada bagian ini.</p>}
                            </section>
                        );
                    })}
                </div>
            </section>

            <section className="leave-sheet-section" aria-labelledby="leave-terms-title">
                <h3 id="leave-terms-title" className="text-sm font-semibold text-gray-900">
                    Dengan ini karyawan menyetujui syarat &amp; ketentuan:
                </h3>
                <ul className="mt-3 list-disc space-y-1 pl-5 text-sm leading-relaxed text-gray-700">
                    <li>HR &amp; Finance dapat melakukan penyesuaian biaya yang diajukan.</li>
                    <li>Transfer dana dilakukan Finance ke rekening payroll karyawan.</li>
                    <li>Transfer dana dilakukan 1 minggu sekali hanya pada hari Rabu.</li>
                    <li>Form cuti ini wajib diserahkan ke HR <strong>2 minggu sebelumnya</strong> agar transfer dana bisa dilakukan sebelum keberangkatan cuti karyawan.</li>
                    <li>Total biaya dalam form ini akan dipotong dari gaji jika tidak dilakukan settlement dalam waktu <strong>30 hari sejak tiba di site</strong>.</li>
                </ul>
            </section>

            {!readOnly && <div className="flex flex-wrap gap-3">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-gray-900 px-5 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-60"
                >
                    {processing ? "Menyimpan…" : submitLabel}
                </button>
                <Link
                    href={batalHref}
                    className="rounded-md border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Batal
                </Link>
            </div>}

            <Modal
                show={konfirmasi}
                onClose={() => setKonfirmasi(false)}
                maxWidth="md"
            >
                <div className="p-6">
                    <h3 className="text-base font-semibold text-gray-900">
                        Konfirmasi penyimpanan
                    </h3>
                    <p className="mt-2 text-sm text-gray-600">
                        Data akan disimpan sebagai draf dengan estimasi{" "}
                        <span className="font-semibold">{totalHari} hari</span>{" "}
                        dan advance{" "}
                        <span className="font-semibold">
                            {formatRupiah(totalAdvance)}
                        </span>
                        .
                    </p>
                    <div className="mt-3 rounded-md bg-amber-50 p-3 text-xs text-amber-800">
                        Angka di atas hanya estimasi lokal. Total hari dan total
                        advance final dihitung ulang oleh server saat data
                        disimpan — pengajuan belum terkirim ke approval sebelum
                        Anda menekan tombol Ajukan di halaman detail.
                    </div>
                    <div className="mt-5 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setKonfirmasi(false)}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Periksa Lagi
                        </button>
                        <button
                            type="button"
                            disabled={processing}
                            onClick={() => {
                                setKonfirmasi(false);
                                onSubmit();
                            }}
                            className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-60"
                        >
                            Ya, Simpan
                        </button>
                    </div>
                </div>
            </Modal>
            </fieldset>
        </form>
    );
}

export { labelBiaya, labelPeriode };
