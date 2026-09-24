export default function EmptyState({
    title = 'Tidak ada data',
    description = 'Belum ada data untuk ditampilkan.',
    children,
    className = '',
}) {
    return (
        <div
            className={`flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center ${className}`}
        >
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                <svg
                    className="h-6 w-6 text-gray-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={1.8}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                >
                    <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" />
                    <path d="M3 7v10a2 2 0 0 0 2 2h14" />
                </svg>
            </div>
            <h3 className="mt-4 text-sm font-semibold text-gray-900">
                {title}
            </h3>
            {description && (
                <p className="mt-1 max-w-sm text-sm text-gray-500">
                    {description}
                </p>
            )}
            {children && <div className="mt-6">{children}</div>}
        </div>
    );
}
