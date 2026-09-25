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
                            onClick={() => choose(employee)}
                        >
                            <strong>{employee.employee_number}</strong> · {employee.name}
                            <small>{employee.department}</small>
                        </button>
                    )) : <p>Tidak ada karyawan yang cocok.</p>}
                </div>
            )}
        </div>
    );
}
