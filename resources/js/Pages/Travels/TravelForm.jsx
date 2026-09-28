import InputError from "@/Components/InputError";
import EmployeeLookup from "@/Components/EmployeeLookup";
import Modal from "@/Components/Modal";
import WorkflowStepper from "@/Components/WorkflowStepper";
import { Link } from "@inertiajs/react";
import { useMemo, useRef, useState } from "react";
import { formatRupiah } from "./helpers";

const input =
    "block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100";
const label = "block text-xs font-semibold text-slate-600 dark:text-slate-300";
const metadataKeys = {
    flight: [
        "flight_destination",
        "departure_time",
        "airline",
        "ticket_number",
    ],
    hotel: ["check_in_date", "check_out_date", "nights"],
    land_transport: ["note"],
    meal: ["note"],
    other: ["note"],
};
const costSections = [
    { category: "land_transport", code: "C.1", title: "Travel (Jalur Darat)" },
    { category: "flight", code: "C.2", title: "Tiket Pesawat" },
    { category: "hotel", code: "C.3", title: "Penginapan (Hotel / mess BUA / mess PORT / mess Purca)" },
    { category: "meal", code: "C.4", title: "Makan" },
    { category: "other", code: "C.5", title: "Lainnya" },
];
const sectionNames = Object.fromEntries(
    costSections.map((section) => [section.category, section.title]),
);

export const normalizeMetadata = (category, metadata = {}) =>
    Object.fromEntries(
        (metadataKeys[category] ?? [])
            .filter(
                (key) =>
                    metadata?.[key] !== undefined &&
                    metadata?.[key] !== null &&
                    metadata[key] !== "",
            )
            .map((key) => [key, metadata[key]]),
    );

const emptyItem = (category = "land_transport") => ({
    category,
    transaction_date: "",
    origin: "",
    destination: "",
    description: sectionNames[category] ?? "Biaya perjalanan",
    quantity: 1,
    unit_price: 0,
    metadata: {},
});

function ErrorList({ errors }) {
    const list = Object.entries(errors ?? {});
    return list.length ? (
        <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
            <p className="font-semibold">Periksa kembali isian berikut:</p>
            <ul className="mt-2 list-disc space-y-1 pl-5">
                {list.slice(0, 12).map(([key, value]) => (
                    <li key={key}>{String(value)}</li>
                ))}
            </ul>
        </div>
    ) : null;
}

function FieldLabel({ children, required = false, ...props }) {
    return (
        <label {...props}>
            {children}
            {required && <span className="travel-required-mark"> *</span>}
        </label>
    );
}

function MetadataFields({ item, setMeta, index, errors }) {
    const field = (key, title, type = "text", placeholder = "") => (
        <div className="worksheet-cell">
            <label className={label} htmlFor={`travel-${index}-${key}`}>
                {title}
            </label>
            <input
                id={`travel-${index}-${key}`}
                type={type}
                min={type === "number" ? "0" : undefined}
                value={item.metadata?.[key] ?? ""}
                onChange={(event) => setMeta(key, event.target.value)}
                className={`${input} mt-1`}
                placeholder={placeholder}
            />
            <InputError
                message={errors[`items.${index}.metadata.${key}`]}
                className="mt-1"
            />
        </div>
    );

    if (item.category === "flight")
        return (
            <>
                {field(
                    "flight_destination",
                    "Tujuan flight",
                    "text",
                    "Kota / bandara tujuan",
                )}
                {field("departure_time", "Jam keberangkatan", "time")}
                {field(
                    "airline",
                    "Maskapai",
                    "text",
                    "Contoh: Garuda Indonesia",
                )}
                {field("ticket_number", "Nomor tiket / booking")}
            </>
        );
    if (item.category === "hotel")
        return (
            <>
                {field("check_in_date", "Tanggal check-in", "date")}
                {field("check_out_date", "Tanggal check-out", "date")}
                <div className="worksheet-hint md:col-span-2">
                    Jumlah malam dihitung otomatis dari tanggal menginap, lalu
                    diverifikasi ulang oleh server.
                </div>
            </>
        );
    if (["land_transport", "meal", "other"].includes(item.category))
        return (
            <div className="md:col-span-2">
                {field(
                    "note",
                    "Catatan tambahan (opsional)",
                    "text",
                    "Contoh: rincian lokasi atau kebutuhan biaya",
                )}
            </div>
        );
    return null;
}

function ItemRow({ item, index, options, errors, onChange, onRemove }) {
    const checkIn = item.metadata?.check_in_date;
    const checkOut = item.metadata?.check_out_date;
    const derivedNights =
        item.category === "hotel" && checkIn && checkOut
            ? Math.max(
                  0,
                  (Date.parse(`${checkOut}T00:00:00Z`) -
                      Date.parse(`${checkIn}T00:00:00Z`)) /
                      86400000,
              )
            : null;
    const quantity = derivedNights ?? (Number(item.quantity) || 0);
    const amount = quantity * (Number(item.unit_price) || 0);
    const set = (field, value) => onChange({ ...item, [field]: value });
    const setMeta = (field, value) => {
        const metadata = normalizeMetadata(item.category, {
            ...(item.metadata ?? {}),
            [field]: value,
        });
        const nights =
            metadata.check_in_date && metadata.check_out_date
                ? Math.max(
                      0,
                      (Date.parse(`${metadata.check_out_date}T00:00:00Z`) -
                          Date.parse(`${metadata.check_in_date}T00:00:00Z`)) /
                          86400000,
                  )
                : item.quantity;
        onChange({ ...item, quantity: nights, metadata });
    };
    const changeCategory = (category) =>
        onChange({ ...item, category, metadata: {} });

    return (
        <article className="worksheet-row overflow-hidden">
            <header className="worksheet-row-header flex flex-wrap items-center justify-between gap-2 px-3 py-2.5">
                <div className="flex items-center gap-2">
                    <span className="worksheet-row-index">{index + 1}</span>
                    <h4 className="text-sm font-bold">Item biaya perjalanan</h4>
                    <span className="rounded-md bg-white/80 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-slate-900 dark:text-emerald-300">
                        Estimasi {formatRupiah(amount)}
                    </span>
                </div>
                <button
                    type="button"
                    onClick={onRemove}
                    className="rounded-md px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                >
                    Hapus baris
                </button>
            </header>
            <div className="worksheet-fields worksheet-fields-travel">
                <div className="worksheet-cell">
                    <FieldLabel className={label} required>Kategori biaya</FieldLabel>
                    <select
                        required
                        value={item.category}
                        onChange={(event) => changeCategory(event.target.value)}
                        className={`${input} mt-1`}
                    >
                        {options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <InputError
                        message={errors[`items.${index}.category`]}
                        className="mt-1"
                    />
                </div>
                <div className="worksheet-cell">
                    <label className={label}>Tanggal transaksi</label>
                    <input
                        type="date"
                        value={item.transaction_date ?? ""}
                        onChange={(event) =>
                            set("transaction_date", event.target.value)
                        }
                        className={`${input} mt-1`}
                    />
                    <InputError
                        message={errors[`items.${index}.transaction_date`]}
                        className="mt-1"
                    />
                </div>
                <div className="worksheet-cell">
                    <label className={label}>Lokasi awal</label>
                    <input
                        value={item.origin ?? ""}
                        onChange={(event) => set("origin", event.target.value)}
                        className={`${input} mt-1`}
                        placeholder="Kota / lokasi asal"
                    />
                    <InputError
                        message={errors[`items.${index}.origin`]}
                        className="mt-1"
                    />
                </div>
                <div className="worksheet-cell">
                    <label className={label}>Tujuan</label>
                    <input
                        value={item.destination ?? ""}
                        onChange={(event) =>
                            set("destination", event.target.value)
                        }
                        className={`${input} mt-1`}
                        placeholder="Kota / lokasi tujuan"
                    />
                    <InputError
                        message={errors[`items.${index}.destination`]}
                        className="mt-1"
                    />
                </div>
                <div className="worksheet-cell md:col-span-2">
                    <FieldLabel className={label} required>Keterangan / uraian biaya</FieldLabel>
                    <input
                        required
                        value={item.description ?? ""}
                        onChange={(event) =>
                            set("description", event.target.value)
                        }
                        className={`${input} mt-1`}
                        placeholder="Contoh: Tiket perjalanan, hotel, atau transport lokal"
                    />
                    <InputError
                        message={errors[`items.${index}.description`]}
                        className="mt-1"
                    />
                </div>
                {item.category !== "hotel" && (
                    <div className="worksheet-cell">
                        <FieldLabel className={label} required>Kuantitas</FieldLabel>
                        <input
                            required
                            type="number"
                            min="0"
                            step="0.01"
                            value={item.quantity}
                            onChange={(event) =>
                                set("quantity", event.target.value)
                            }
                            className={`${input} mt-1`}
                        />
                        <InputError
                            message={errors[`items.${index}.quantity`]}
                            className="mt-1"
                        />
                    </div>
                )}
                {item.category === "hotel" && (
                    <div className="worksheet-cell">
                        <FieldLabel className={label} required>
                            Jumlah malam (hasil tanggal)
                        </FieldLabel>
                        <input
                            readOnly
                            type="number"
                            value={derivedNights ?? item.quantity}
                            className={`${input} mt-1 bg-slate-50 dark:bg-slate-800`}
                        />
                        <InputError
                            message={errors[`items.${index}.quantity`]}
                            className="mt-1"
                        />
                    </div>
                )}
                <div className="worksheet-cell">
                    <FieldLabel className={label} required>Harga satuan (Rp)</FieldLabel>
                    <input
                        required
                        type="number"
                        min="0"
                        step="0.01"
                        value={item.unit_price}
                        onChange={(event) =>
                            set("unit_price", event.target.value)
                        }
                        className={`${input} mt-1`}
                    />
                    <InputError
                        message={errors[`items.${index}.unit_price`]}
                        className="mt-1"
                    />
                </div>
                <MetadataFields
                    item={item}
                    setMeta={setMeta}
                    index={index}
                    errors={errors}
                />
            </div>
        </article>
    );
}

function TravelItemRow({ item, index, options, errors, onChange, onRemove }) {
    const categoryLabel = options.find((option) => option.value === item.category)?.label ?? sectionNames[item.category];
    const description = item.description || sectionNames[item.category] || "Biaya perjalanan";
    const checkIn = item.metadata?.check_in_date ?? "";
    const checkOut = item.metadata?.check_out_date ?? "";
    const nights = checkIn && checkOut
        ? Math.max(0, (Date.parse(`${checkOut}T00:00:00Z`) - Date.parse(`${checkIn}T00:00:00Z`)) / 86400000)
        : Number(item.quantity) || 0;
    const amount = item.category === "flight" ? 0 : nights * (Number(item.unit_price) || 0);
    const set = (field, value) => onChange({ ...item, description, [field]: value });
    const setMeta = (field, value) => onChange({
        ...item,
        description,
        quantity: field === "check_in_date" || field === "check_out_date"
            ? ((field === "check_in_date" ? value : checkIn) && (field === "check_out_date" ? value : checkOut)
                ? Math.max(0, (Date.parse(`${field === "check_out_date" ? value : checkOut}T00:00:00Z`) - Date.parse(`${field === "check_in_date" ? value : checkIn}T00:00:00Z`)) / 86400000)
                : item.quantity)
            : item.quantity,
        metadata: normalizeMetadata(item.category, { ...(item.metadata ?? {}), [field]: value }),
    });
    const moneyField = (title, field = "unit_price") => (
        <div className="worksheet-cell">
            <FieldLabel className={label} required>{title}</FieldLabel>
            <input required type="number" min="0" step="0.01" value={item[field] ?? 0} onChange={(event) => set(field, event.target.value)} className={`${input} mt-1`} />
            <InputError message={errors[`items.${index}.${field}`]} className="mt-1" />
        </div>
    );
    const textField = (title, field, placeholder, required = false) => (
        <div className="worksheet-cell">
            <FieldLabel className={label} required={required}>{title}</FieldLabel>
            <input required={required} value={item[field] ?? ""} onChange={(event) => set(field, event.target.value)} className={`${input} mt-1`} placeholder={placeholder} />
            <InputError message={errors[`items.${index}.${field}`]} className="mt-1" />
        </div>
    );
    return (
        <article className="worksheet-row travel-cost-row overflow-hidden">
            <header className="worksheet-row-header flex flex-wrap items-center gap-2">
                <span className="worksheet-row-index">{index + 1}</span>
                <h4>{categoryLabel}</h4>
                <span className="travel-cost-estimate">Estimasi {formatRupiah(amount)}</span>
                <button type="button" onClick={onRemove}>Hapus</button>
            </header>
            <div className={`worksheet-fields worksheet-fields-travel travel-fields-${item.category}`}>
                <div className="worksheet-cell travel-locked-category"><FieldLabel className={label}>Kategori biaya</FieldLabel><div className="travel-sheet-value">{categoryLabel}</div></div>
                {item.category === "land_transport" && <>
                    <div className="worksheet-cell"><FieldLabel className={label} required>Tanggal</FieldLabel><input required type="date" value={item.transaction_date ?? ""} onChange={(event) => set("transaction_date", event.target.value)} className={`${input} mt-1`} /></div>
                    {textField("Lokasi awal", "origin", "Kota / lokasi asal", true)}{textField("Tujuan", "destination", "Kota / lokasi tujuan", true)}{moneyField("Biaya (Rp)")}
                </>}
                {item.category === "flight" && <>
                    {textField("Lokasi awal", "origin", "Kota / bandara asal")}{textField("Tujuan", "destination", "Kota / bandara tujuan")}
                    <div className="worksheet-cell"><FieldLabel className={label} required>Tanggal flight</FieldLabel><input required type="date" value={item.transaction_date ?? ""} onChange={(event) => set("transaction_date", event.target.value)} className={`${input} mt-1`} /></div>
                    <div className="worksheet-cell"><FieldLabel className={label} required>Jam</FieldLabel><input required type="time" value={item.metadata?.departure_time ?? ""} onChange={(event) => setMeta("departure_time", event.target.value)} className={`${input} mt-1`} /></div>
                    <div className="worksheet-hint md:col-span-2">Tiket dicatat sebagai informasi perjalanan dan tidak masuk estimasi uang muka.</div>
                </>}
                {item.category === "hotel" && <>
                    {textField("Lokasi", "origin", "Nama hotel / mess")}
                    <div className="worksheet-cell"><FieldLabel className={label} required>Check-in</FieldLabel><input required type="date" value={checkIn} onChange={(event) => setMeta("check_in_date", event.target.value)} className={`${input} mt-1`} /></div>
                    <div className="worksheet-cell"><FieldLabel className={label} required>Check-out</FieldLabel><input required type="date" value={checkOut} onChange={(event) => setMeta("check_out_date", event.target.value)} className={`${input} mt-1`} /></div>
                    <div className="worksheet-cell"><FieldLabel className={label}>Jumlah malam</FieldLabel><input readOnly type="number" value={nights} className={`${input} mt-1`} /></div>
                    {moneyField("Biaya (Rp)")}
                </>}
                {item.category === "meal" && <>{textField("Keterangan", "description", "Contoh: makan siang", true)}{moneyField("Kuantitas", "quantity")}{moneyField("Harga satuan (Rp)")}</>}
                {item.category === "other" && <>{textField("Keperluan", "description", "Contoh: biaya administrasi", true)}{moneyField("Kuantitas", "quantity")}{moneyField("Harga satuan (Rp)")}</>}
                <input type="hidden" name={`items[${index}][description]`} value={description} readOnly />
            </div>
        </article>
    );
}

export default function TravelForm({
    data,
    setData,
    errors,
    processing,
    meta,
    travelEmployee = null,
    submitLabel = "Simpan Draft",
    batalHref,
    onSubmit,
    currentStatus = "draft",
    readOnly = false,
    approvalTimeline = [],
    advancePic = null,
}) {
    const [confirm, setConfirm] = useState(false);
    const formRef = useRef(null);
    const options = meta?.cost_categories ?? [
        { value: "land_transport", label: "Transport Darat" },
        { value: "flight", label: "Tiket Pesawat" },
        { value: "hotel", label: "Penginapan" },
        { value: "meal", label: "Makan" },
        { value: "other", label: "Lainnya" },
    ];
    const employees = meta?.employees ?? [];
    const employee =
        employees.find(
            (item) => String(item.id) === String(data.employee_id),
        ) ??
        (travelEmployee && String(travelEmployee.id) === String(data.employee_id)
            ? travelEmployee : null) ??
        (employees.length === 1 ? employees[0] : null);
    const total = useMemo(
        () =>
            (data.items ?? []).reduce((sum, item) => {
                const nights =
                    item.category === "hotel" &&
                    item.metadata?.check_in_date &&
                    item.metadata?.check_out_date
                        ? Math.max(
                              0,
                              (Date.parse(
                                  `${item.metadata.check_out_date}T00:00:00Z`,
                              ) -
                                  Date.parse(
                                      `${item.metadata.check_in_date}T00:00:00Z`,
                                  )) /
                                  86400000,
                          )
                        : Number(item.quantity) || 0;
                return sum + (item.category === "flight" ? 0 : nights * (Number(item.unit_price) || 0));
            }, 0),
        [data.items],
    );
    const travelDays = useMemo(() => {
        if (!data.start_date || !data.end_date) return 0;
        return Math.max(
            0,
            (Date.parse(`${data.end_date}T00:00:00Z`) -
                Date.parse(`${data.start_date}T00:00:00Z`)) /
                86400000 +
                1,
        );
    }, [data.start_date, data.end_date]);
    const updateItem = (index, item) => {
        const items = [...(data.items ?? [])];
        items[index] = { ...item, metadata: item.metadata ?? {} };
        setData("items", items);
    };
    const add = (category = options[0]?.value ?? "land_transport") =>
        setData("items", [
            ...(data.items ?? []),
            emptyItem(category),
        ]);
    const remove = (index) =>
        setData(
            "items",
            (data.items ?? []).filter((_, itemIndex) => itemIndex !== index),
        );
    const openConfirmation = () => {
        if (formRef.current && !formRef.current.checkValidity()) {
            formRef.current.reportValidity();
            return;
        }
        setConfirm(true);
    };
    const submit = (event) => {
        event.preventDefault();
        openConfirmation();
    };

    return (
        <form ref={formRef} onSubmit={readOnly ? (event) => event.preventDefault() : submit} className="worksheet-form travel-sheet space-y-6">
            <fieldset disabled={readOnly} className="block min-w-0 space-y-6 border-0 p-0">
            <WorkflowStepper
                currentStatus={currentStatus}
                title="Alur Perjalanan Dinas"
                steps={[{ key: "draft", label: "Draf" }, { key: "submitted", label: "Diajukan" }, { key: "in_review", label: "Dalam Review", pic: approvalTimeline.find((item) => item.step_code === "supervisor")?.approver?.name }, { key: "approved", label: "Disetujui", pic: approvalTimeline.find((item) => item.step_code === "hod")?.approver?.name }, { key: "processing", label: "Diproses HRGA", pic: approvalTimeline.find((item) => item.step_code === "hrga")?.approver?.name }, { key: "advance_paid", label: "Advance Dibayar", pic: advancePic }, { key: "settlement_required", label: "Perlu Settlement", pic: advancePic }, { key: "completed", label: "Selesai" }]}
            />
            <header className="travel-sheet-header">
                <div className="travel-sheet-logo" aria-label="Borneo Prima">
                    <span className="travel-sheet-logo-mark">BP</span>
                    <span className="travel-sheet-logo-name">PT. BORNEO PRIMA</span>
                    <span className="travel-sheet-logo-sub">COAL MINING &amp; TRADING</span>
                </div>
                <div className="travel-sheet-title">
                    <p>PT. BORNEO PRIMA</p>
                    <h2>SURAT TUGAS / FORMULIR PERJALANAN DINAS</h2>
                </div>
                <div className="travel-sheet-number">
                    <span>Formulir Nomor:</span>
                    <strong>{data.request_number ?? "004/BP/HRD/BUA/FORM/IX/2025"}</strong>
                </div>
            </header>
            <ErrorList errors={errors} />
            <section className="travel-sheet-section employee-lookup-section">
                <div className="travel-sheet-profile-grid">
                    {employees.length > 1 && (
                        <div className="travel-sheet-field employee-lookup-cell">
                            <EmployeeLookup
                                employees={employees}
                                value={data.employee_id}
                                onChange={(id) => setData("employee_id", id)}
                            />
                            <InputError
                                message={errors.employee_id}
                                className="mt-1"
                            />
                        </div>
                    )}
                    {employee && (
                        <div className="travel-sheet-field travel-sheet-field-wide travel-sheet-master-note">
                            <p className={label}>Profil karyawan dari master</p>
                            <p className="mt-1 text-sm font-bold">{employee.employee_number} · {employee.name}</p>
                            <p className="mt-0.5 text-xs text-slate-500">Data identitas terisi otomatis dari master karyawan.</p>
                        </div>
                    )}
                    <div className="travel-sheet-field travel-sheet-field-wide">
                        <label className={label}>NIK</label>
                        <div className="travel-sheet-value">{employee?.employee_number ?? "-"}</div>
                    </div>
                    <div className="travel-sheet-field">
                        <label className={label}>Roster</label>
                        <div className="travel-sheet-value">{employee?.roster ?? "-"}</div>
                    </div>
                    <div className="travel-sheet-field travel-sheet-field-wide">
                        <label className={label}>Nama</label>
                        <div className="travel-sheet-value">{employee?.name ?? "-"}</div>
                    </div>
                    <div className="travel-sheet-field">
                        <label className={label}>Level</label>
                        <div className="travel-sheet-value">{employee?.level ?? "-"}</div>
                    </div>
                    <div className="travel-sheet-field travel-sheet-field-wide">
                        <label className={label}>Dept / Jabatan</label>
                        <div className="travel-sheet-value">{employee?.department ?? "-"} / {employee?.job_title ?? "-"}</div>
                    </div>
                    <div className="travel-sheet-field">
                        <label className={label}>POH status</label>
                        <div className="travel-sheet-value">{employee ? (employee.poh_status === "local" ? "LOKAL" : "NON LOKAL") : "-"}</div>
                    </div>
                    <div className="travel-sheet-field travel-sheet-field-wide">
                        <label className={label}>Jabatan</label>
                        <div className="travel-sheet-value">{employee?.job_title ?? "-"}</div>
                    </div>
                    <div className="travel-sheet-field">
                        <label className={label}>POH</label>
                        <div className="travel-sheet-value">{employee?.poh_city ?? "-"}{employee?.poh_province ? ` - ${employee.poh_province}` : ""}</div>
                    </div>
                    <div className="travel-sheet-field travel-sheet-field-wide travel-sheet-purpose">
                        <FieldLabel className={label} required>Detail keperluan perjalanan dinas</FieldLabel>
                        <textarea
                            required
                            rows={3}
                            value={data.purpose ?? ""}
                            onChange={(event) =>
                                setData("purpose", event.target.value)
                            }
                            className={`${input} travel-sheet-input mt-1`}
                        />
                        <InputError message={errors.purpose} className="mt-1" />
                    </div>
                </div>
                <p className="travel-sheet-instruction"><strong>Silahkan isi di kolom yang berwarna biru saja.</strong></p>
            </section>

            <section className="travel-sheet-section overflow-hidden">
                <div className="worksheet-section-heading mb-4">
                    <p className="worksheet-eyebrow">B. RINCIAN HARI DINAS</p>
                    <h3 className="text-sm font-semibold text-gray-900">Periode Perjalanan Dinas</h3>
                </div>
                <div className="travel-sheet-days">
                    <div className="travel-sheet-days-label">DINAS LUAR (termasuk perjalanan)</div>
                    <FieldLabel required>Tgl awal<input required type="date" value={data.start_date ?? ""} onChange={(event) => setData("start_date", event.target.value)} className={input} /></FieldLabel>
                    <FieldLabel required>Tgl akhir<input required type="date" value={data.end_date ?? ""} onChange={(event) => setData("end_date", event.target.value)} className={input} /></FieldLabel>
                    <div className="travel-sheet-days-total">{travelDays ? `${travelDays} Hari` : "-"}</div>
                    <div className="travel-sheet-days-label">ONSITE</div>
                    <label>Tgl<input type="date" value={data.end_date ?? ""} readOnly className={input} /></label>
                    <div className="travel-sheet-days-empty">Tanggal onsite mengikuti tanggal selesai perjalanan</div>
                </div>
            </section>

            <section className="travel-sheet-section">
                <div className="worksheet-section-heading mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p className="worksheet-eyebrow">C. AKOMODASI &amp; ESTIMASI UANG MUKA DINAS</p>
                        <h3 className="text-sm font-semibold text-gray-900">Biaya Perjalanan &amp; Akomodasi</h3>
                        <p className="mt-0.5 text-xs text-gray-500">Jumlah per baris = kuantitas × harga satuan. Estimasi — final dari server.</p>
                    </div>
                    <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Estimasi {formatRupiah(total)}</span>
                </div>
                <div className="travel-sheet-cost-sections">
                    {costSections.map((section) => {
                        const rows = (data.items ?? [])
                            .map((item, index) => ({ item, index }))
                            .filter(({ item }) => item.category === section.category);
                        return (
                            <section key={section.category} className="travel-sheet-cost-section">
                                <header className="travel-sheet-cost-heading">
                                    <h4>{section.code} {section.title}</h4>
                                    <button type="button" onClick={() => add(section.category)}>+ Tambah baris</button>
                                </header>
                                <div>
                                    {rows.length ? rows.map(({ item, index }) => (
                                        <TravelItemRow
                                            key={index}
                                            item={{ ...item, metadata: item.metadata ?? {} }}
                                            index={index}
                                            options={options}
                                            errors={errors}
                                            onChange={(next) => updateItem(index, next)}
                                            onRemove={() => remove(index)}
                                        />
                                    )) : (
                                        <p className="travel-sheet-empty-row">Belum ada rincian. Tambahkan item pada sub-section ini.</p>
                                    )}
                                </div>
                            </section>
                        );
                    })}
                    <p className="travel-sheet-note">
                        <strong>Catatan:</strong> Biaya lainnya adalah biaya di luar transportasi, makan, dan hotel. HR &amp; Finance akan melakukan penyesuaian seluruh nominal biaya <em>advance</em> dan <em>settlement</em> berdasarkan kebijakan internal perusahaan.
                    </p>
                    <p className="worksheet-hint">
                        Total akhir dihitung ulang server dari kuantitas × harga satuan. Lampiran undangan atau dokumen pendukung dapat ditambahkan setelah draf tersimpan.
                    </p>
                </div>
            </section>

            <section className="travel-sheet-section" aria-labelledby="travel-terms-title">
                <div className="travel-terms-grid">
                    <h3 id="travel-terms-title">Karyawan setuju syarat &amp; ketentuan berikut:</h3>
                    <ul>
                        <li>Lampirkan undangan jika dinas diundang oleh instansi luar / vendor training jika keperluan untuk training.</li>
                        <li>Wajib melakukan <em>settlement advance</em> / deklarasi uang muka dinas ini paling lambat tanggal 21 bulan berjalan.</li>
                        <li>Payroll akan melakukan pemotongan gaji karyawan jika tidak dilakukan <em>settlement</em> / deklarasi pada tanggal 21 bulan selanjutnya.</li>
                    </ul>
                </div>
            </section>

            </fieldset>

            <div className="flex flex-wrap gap-3">
                <button type="button" disabled={processing || readOnly} onClick={openConfirmation} className="rounded-md bg-gray-900 px-5 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-60">
                    {processing ? "Menyimpan…" : submitLabel}
                </button>
                <Link href={batalHref} className="rounded-md border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</Link>
            </div>

            <Modal
                show={confirm}
                onClose={() => setConfirm(false)}
                maxWidth="md"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold">
                        Simpan pengajuan perjalanan?
                    </h2>
                    <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Draf akan disimpan dengan estimasi advance{" "}
                        {formatRupiah(total)}. Total final dihitung ulang oleh
                        server.
                    </p>
                    <ErrorList errors={errors} />
                    <div className="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setConfirm(false)}
                            className="rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700"
                        >
                            Periksa lagi
                        </button>
                        <button
                            type="button"
                            disabled={processing}
                            onClick={() => {
                                onSubmit();
                            }}
                            className="ui-button-primary rounded-lg px-4 py-2 text-sm"
                        >
                            Ya, simpan draf
                        </button>
                    </div>
                </div>
            </Modal>
        </form>
    );
}

export { emptyItem };
