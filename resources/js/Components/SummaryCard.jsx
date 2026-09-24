function formatNilai(nilai) {
    if (typeof nilai === 'number') {
        return nilai.toLocaleString('id-ID');
    }
    return nilai;
}

export default function SummaryCard({
    judul,
    title,
    nilai,
    value,
    sub,
    subtitle,
    className = '',
}) {
    const displayJudul = judul ?? title ?? 'Ringkasan';
    const displayNilai = formatNilai(nilai ?? value ?? '—');
    const displaySub = sub ?? subtitle ?? null;

    return (
        <div
            className={`ui-card overflow-hidden p-5 ${className}`}
        >
            <p className="text-sm font-medium text-gray-500">{displayJudul}</p>
            <p className="mt-1 text-2xl font-semibold tracking-tight text-gray-900">
                {displayNilai}
            </p>
            {displaySub && (
                <p className="mt-1 text-sm text-gray-500">{displaySub}</p>
            )}
        </div>
    );
}
