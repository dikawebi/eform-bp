function formatNilai(nilai) {
    if (typeof nilai === 'number') {
        return nilai.toLocaleString('id-ID');
    }
    return nilai;
}

import { Link } from '@inertiajs/react';

export default function SummaryCard({
    judul,
    title,
    nilai,
    value,
    sub,
    subtitle,
    className = '',
    href = null,
}) {
    const displayJudul = judul ?? title ?? 'Ringkasan';
    const displayNilai = formatNilai(nilai ?? value ?? '—');
    const displaySub = sub ?? subtitle ?? null;

    const body = (
        <>
            <p className="text-sm font-medium text-gray-500">{displayJudul}</p>
            <p className="mt-1 text-2xl font-semibold tracking-tight text-gray-900">
                {displayNilai}
            </p>
            {displaySub && (
                <p className="mt-1 text-sm text-gray-500">{displaySub}</p>
            )}
        </>
    );

    if (!href) {
        return (
            <div className={`ui-card overflow-hidden p-5 ${className}`}>
                {body}
            </div>
        );
    }

    return (
        <Link
            href={href}
            className={`ui-card group overflow-hidden p-5 transition hover:-translate-y-0.5 hover:shadow-md ${className}`}
        >
            {body}
            <p className="mt-2 text-xs font-semibold text-slate-400 group-hover:text-[#0066FF]">Lihat laporan →</p>
        </Link>
    );
}
