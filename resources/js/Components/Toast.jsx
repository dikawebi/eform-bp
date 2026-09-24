import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function toFlashMessage(value) {
    if (value === null || value === undefined) {
        return '';
    }
    if (Array.isArray(value)) {
        return value.map((v) => String(v)).join(', ');
    }
    if (typeof value === 'string') {
        return value;
    }
    return String(value);
}

export default function Toast() {
    const { props } = usePage();
    const flash = props.flash || {};

    const [visible, setVisible] = useState(false);
    const [message, setMessage] = useState('');
    const [type, setType] = useState('success');

    // Kontrak backend (HandleInertiaRequests): flash.success / flash.error.
    // Dukung flash.info / flash.message sebagai info tanpa merusak kontrak.
    const successRaw = flash.success;
    const errorRaw = flash.error;
    const infoRaw = flash.info ?? flash.message;

    const successMsg = toFlashMessage(successRaw);
    const errorMsg = toFlashMessage(errorRaw);
    const infoMsg = toFlashMessage(infoRaw);

    useEffect(() => {
        // Prioritas: error > success > info. Normalisasi String(msg).
        const msg = errorMsg || successMsg || infoMsg;
        if (msg) {
            setMessage(String(msg));
            if (errorMsg) {
                setType('error');
            } else if (successMsg) {
                setType('success');
            } else {
                setType('info');
            }
            setVisible(true);
            const timer = setTimeout(() => setVisible(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [successMsg, errorMsg, infoMsg]);

    if (!visible || !message) {
        return null;
    }

    const color =
        type === 'error'
            ? 'border-rose-200 bg-rose-50 text-rose-800'
            : type === 'info'
              ? 'border-sky-200 bg-sky-50 text-sky-800'
              : 'border-emerald-200 bg-emerald-50 text-emerald-800';

    const title =
        type === 'error'
            ? 'Terjadi kesalahan'
            : type === 'info'
              ? 'Informasi'
              : 'Berhasil';

    return (
        <div
            role="status"
            aria-live="polite"
            className={`fixed bottom-4 right-4 z-50 w-full max-w-sm rounded-lg border p-4 shadow-lg ${color}`}
        >
            <div className="flex items-start gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold">{title}</p>
                    <p className="mt-0.5 break-words text-sm">{message}</p>
                </div>
                <button
                    type="button"
                    onClick={() => setVisible(false)}
                    aria-label="Tutup notifikasi"
                    className="rounded-md p-1 text-current opacity-70 transition hover:opacity-100 focus:outline-none"
                >
                    <svg
                        className="h-4 w-4"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </button>
            </div>
        </div>
    );
}
