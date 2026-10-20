import { useEffect, useId, useMemo, useRef, useState } from "react";

export default function EmployeeLookup({ employees, value, onChange, label = "Pilih karyawan / NIK" }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState("");
    const [active, setActive] = useState(0);
    const listId = useId();
    const listRef = useRef(null);
    const selected = employees.find((employee) => String(employee.id) === String(value));
    const selectedLabel = selected
        ? `${selected.employee_number} · ${selected.name} · ${selected.department ?? ""}`
        : "";
    const matches = useMemo(() => {
        const keyword = query.trim().toLocaleLowerCase("id-ID");
        return employees.filter((employee) =>
            `${employee.employee_number} ${employee.name} ${employee.department ?? ""}`
                .toLocaleLowerCase("id-ID")
                .includes(keyword),
        );
    }, [employees, query]);

    useEffect(() => {
        if (open) {
            listRef.current?.querySelector('[data-active="true"]')?.scrollIntoView({ block: "nearest" });
        }
    }, [active, open, query]);

    const choose = (employee) => {
        onChange(String(employee.id));
        setOpen(false);
        setQuery("");
    };

    return (
        <div className="employee-lookup" onBlur={(event) => {
            if (!event.currentTarget.contains(event.relatedTarget)) {
                setOpen(false);
                setQuery("");
            }
        }}>
            <label htmlFor={`${listId}-input`}>{label}</label>
            <div className="employee-lookup-field">
                <svg className="employee-lookup-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-3.5-3.5" />
                </svg>
                <input
                    id={`${listId}-input`}
                    type="text"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded={open}
                    aria-controls={listId}
                    aria-activedescendant={open && matches.length ? `${listId}-${active}` : undefined}
                    autoComplete="off"
                    placeholder="Cari NIK atau nama karyawan"
                    value={open ? query : selectedLabel}
                    onFocus={() => { setOpen(true); setQuery(""); setActive(0); }}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setActive(0);
                        onChange("");
                        setOpen(true);
                    }}
                    onKeyDown={(event) => {
                        if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                            event.preventDefault();
                            setOpen(true);
                            setActive((current) => event.key === "ArrowDown"
                                ? Math.max(0, Math.min(current + 1, matches.length - 1))
                                : Math.max(current - 1, 0));
                        } else if (event.key === "Enter" && open) {
                            event.preventDefault();
                            if (matches[active]) choose(matches[active]);
                        } else if (event.key === "Escape") {
                            setOpen(false);
                            setQuery("");
                        }
                    }}
                />
                <svg className={`employee-lookup-chevron${open ? " is-open" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </div>
            {open && (
                <div id={listId} role="listbox" ref={listRef} className="employee-lookup-options">
                    {matches.length ? matches.map((employee, index) => (
                        <button
                            key={employee.id}
                            id={`${listId}-${index}`}
                            role="option"
                            aria-selected={index === active}
                            data-active={index === active}
                            type="button"
                            onMouseDown={(event) => event.preventDefault()}
                            onMouseEnter={() => setActive(index)}
                            onClick={() => choose(employee)}
                        >
                            <span className="employee-lookup-option-main"><strong>{employee.employee_number}</strong> · {employee.name}</span>
                            <small>{employee.department || "Tanpa departemen"}{employee.job_title ? ` · ${employee.job_title}` : ""}</small>
                        </button>
                    )) : <p>Tidak ada karyawan yang cocok.</p>}
                    {matches.length > 0 && <p className="employee-lookup-count">{matches.length} karyawan · ↑↓ untuk memilih, Enter untuk menetapkan</p>}
                </div>
            )}
        </div>
    );
}
