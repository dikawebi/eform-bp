const STATUS_ALIASES = {
    inreview: "in_review",
    review: "in_review",
    settlementrequired: "settlement_required",
    advancepaid: "advance_paid",
    paymentprocessing: "payment_processing",
};

const BRANCH_STATUSES = {
    returned: { label: "Dikembalikan", className: "border-orange-200 bg-orange-50 text-orange-800" },
    rejected: { label: "Ditolak", className: "border-rose-200 bg-rose-50 text-rose-800" },
    cancelled: { label: "Dibatalkan", className: "border-zinc-200 bg-zinc-50 text-zinc-700" },
};

const normalizeStatus = (value) => {
    const key = String(value ?? "draft").trim().toLowerCase().replace(/[\s-]+/g, "_");
    return STATUS_ALIASES[key.replace(/_/g, "")] ?? key;
};

/** Stepper visual saja. Status dan kewenangan tetap ditentukan oleh server. */
export default function WorkflowStepper({ currentStatus = "draft", steps = [], title = "Tahapan proses", note }) {
    const status = normalizeStatus(currentStatus);
    const normalizedSteps = steps.map((step) => (typeof step === "string" ? { key: step, label: step } : step));
    const currentIndex = normalizedSteps.findIndex((step) => normalizeStatus(step.key ?? step.status) === status);
    const branch = BRANCH_STATUSES[status];
    const activeIndex = currentIndex >= 0 ? currentIndex : branch ? Math.max(0, normalizedSteps.findIndex((step) => normalizeStatus(step.key ?? step.status) === "in_review")) : 0;
    const terminal = status === "completed";

    return (
        <section className="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label={title}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Alur pengajuan</p>
                    <h2 className="mt-1 text-sm font-semibold text-slate-900">{title}</h2>
                </div>
                {branch && <span className={`rounded-full border px-2.5 py-1 text-xs font-semibold ${branch.className}`}>{branch.label}</span>}
            </div>
            <div className="mt-5 overflow-x-auto pb-1">
                <ol className="flex min-w-max items-start" aria-current={branch ? "step" : undefined}>
                    {normalizedSteps.map((step, index) => {
                         const done = !branch && currentIndex >= 0 && (index < currentIndex || (terminal && index === currentIndex));
                        const active = index === activeIndex;
                        return (
                            <li key={step.key ?? index} className="flex items-start">
                                <div className="flex w-28 flex-col items-center text-center sm:w-36">
                                    <span className={`flex h-8 w-8 items-center justify-center rounded-full border-2 text-xs font-bold ${done ? "border-indigo-600 bg-indigo-600 text-white" : active ? "border-indigo-600 bg-white text-indigo-700 ring-4 ring-indigo-50" : "border-slate-300 bg-white text-slate-400"}`}>
                                        {done ? "✓" : index + 1}
                                    </span>
                                     <span className={`mt-2 text-xs leading-tight ${active ? "font-bold text-indigo-700" : done ? "font-semibold text-slate-700" : "text-slate-500"}`}>{step.label}</span>
                                     {step.pic && <span className="mt-1 max-w-32 truncate text-[10px] text-slate-400" title={`PIC: ${step.pic}`}>PIC: {step.pic}</span>}
                                </div>
                                {index < normalizedSteps.length - 1 && <span aria-hidden="true" className={`mt-4 h-0.5 w-8 sm:w-16 ${done ? "bg-indigo-600" : "bg-slate-200"}`} />}
                            </li>
                        );
                    })}
                </ol>
            </div>
            {(note || branch) && <p className="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">{note ?? "Status ini merupakan cabang proses. Tindakan berikutnya mengikuti keputusan dan kewenangan di server."}</p>}
        </section>
    );
}
