import { Link } from '@inertiajs/react';

function FeatureIcon({ type }) {
    const paths = {
        workflow: <><path d="M4 7h16v10H4z" /><path d="M8 7v10M12 7v10M16 7v10" /></>,
        approval: <><rect x="4" y="4" width="16" height="16" rx="2" /><path d="m8 12 2.5 2.5L16 9" /></>,
        audit: <><path d="M5 19V9M10 19V5M15 19v-7M20 19V3M3 19h19" /></>,
    };

    return <svg className="bp-login-feature-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{paths[type]}</svg>;
}

export default function GuestLayout({ children }) {
    return (
        <div className="bp-login">
            <main className="bp-login-form-col">
                <div className="bp-login-form-inner">
                    <Link href="/" className="bp-login-brand">
                        <span className="bp-login-brand-row">
                            <img src="/images/logo-bp.svg" alt="PT Borneo Prima" className="bp-login-brand-logo" fetchPriority="high" decoding="async" />
                            <span className="bp-login-brand-wordmark">
                                <span className="bp-login-brand-name">PT BORNEO PRIMA</span>
                                <span className="bp-login-brand-sub">eForm BP</span>
                            </span>
                        </span>
                    </Link>
                    {children}
                    <p className="bp-login-internal-note">
                        <svg className="bp-login-internal-note-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                            <rect x="5" y="10" width="14" height="10" rx="2" />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        </svg>
                        Portal internal — khusus karyawan PT Borneo Prima.
                    </p>
                </div>
            </main>

            <aside className="bp-login-side">
                <div className="bp-login-photo" role="img" aria-label="Operasi PT Borneo Prima" />
                <div className="bp-login-veil" />
                <div className="bp-login-side-inner">
                    <div className="bp-login-features">
                        {[
                            ['workflow', 'Pengajuan Terstruktur', 'Cuti, perjalanan dinas, dan settlement dalam satu alur kerja.'],
                            ['approval', 'Approval Terkendali', 'Setiap persetujuan mengikuti matrix dan kewenangan yang berlaku.'],
                            ['audit', 'Audit Terlacak', 'Status, dokumen, dan perubahan tercatat rapi dalam satu portal.'],
                        ].map(([icon, title, description]) => (
                            <div key={title} className="bp-login-feature">
                                <span className="bp-login-feature-icon"><FeatureIcon type={icon} /></span>
                                <span>
                                    <strong>{title}</strong>
                                    <small>{description}</small>
                                </span>
                            </div>
                        ))}
                    </div>
                    <div className="bp-login-side-foot">
                        <span>Kunjungi website utama perusahaan</span>
                        <a href="https://borneoprima.com/" target="_blank" rel="noopener">borneoprima.com &#8599;</a>
                    </div>
                </div>
            </aside>
        </div>
    );
}
