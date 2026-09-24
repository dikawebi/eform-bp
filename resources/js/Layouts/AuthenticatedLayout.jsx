import Dropdown from '@/Components/Dropdown';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Toast from '@/Components/Toast';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const DEFAULT_MENUS = [
    { key: 'dashboard', label: 'Dashboard', href: '/dashboard' },
    { key: 'leaves', label: 'Cuti/Izin', href: '/leaves' },
    { key: 'travels', label: 'Perjalanan Dinas', href: '/travels' },
    { key: 'settlements', label: 'Settlement', href: '/settlements' },
    {
        key: 'medical-claims',
        label: 'Medical Claim',
        href: '/medical-claims',
    },
    { key: 'approvals', label: 'Approval', href: '/approvals' },
    { key: 'reports', label: 'Laporan', href: '/reports' },
    { key: 'master', label: 'Master Data', href: '/master/employees' },
    { key: 'settings', label: 'Pengaturan', href: '/settings/workflows' },
];

function keyFromUrl(url) {
    switch (String(url || '')) {
        case '/dashboard':
            return 'dashboard';
        case '/leaves':
            return 'leaves';
        case '/travels':
            return 'travels';
        case '/settlements':
            return 'settlements';
        case '/medical-claims':
            return 'medical-claims';
        case '/approvals':
            return 'approvals';
        case '/reports':
            return 'reports';
        case '/master/employees':
            return 'master';
        case '/settings/workflows':
            return 'settings';
        default:
            return null;
    }
}

function MenuIcon({ menuKey }) {
    const paths = {
        dashboard: (
            <>
                <path d="M3 3h7v7H3zM14 3h7v4h-7zM14 12h7v9h-7zM3 14h7v7H3z" />
            </>
        ),
        leaves: (
            <>
                <rect x="3" y="5" width="18" height="16" rx="2" />
                <path d="M8 3v4M16 3v4M3 10h18" />
            </>
        ),
        travels: (
            <>
                <rect x="3" y="7" width="18" height="13" rx="2" />
                <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            </>
        ),
        settlements: (
            <>
                <path d="M3 7a2 2 0 0 1 2-2h14a1 1 0 0 1 1 1v3M3 7v10a2 2 0 0 0 2 2h16a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1H5" />
                <circle cx="16.5" cy="14" r="1.2" />
            </>
        ),
        'medical-claims': (
            <>
                <circle cx="12" cy="12" r="9" />
                <path d="M12 8v8M8 12h8" />
            </>
        ),
        approvals: (
            <>
                <circle cx="12" cy="12" r="9" />
                <path d="M8.5 12.5l2.5 2.5 4.5-5.5" />
            </>
        ),
        reports: (
            <>
                <path d="M4 20V10M10 20V4M16 20v-7M3 20h18" />
            </>
        ),
        master: (
            <>
                <ellipse cx="12" cy="5" rx="8" ry="3" />
                <path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5" />
                <path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" />
            </>
        ),
        settings: (
            <>
                <circle cx="12" cy="12" r="3" />
                <path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1" />
            </>
        ),
    };

    return (
        <svg
            className="h-5 w-5 shrink-0"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.8}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {paths[menuKey] ?? (menuKey.startsWith('settings-') ? paths.settings : (
                <circle cx="12" cy="12" r="8" />
            ))}
        </svg>
    );
}

function normalizeMenus(serverMenus) {
    // Fail-closed (B-1): jangan tampilkan menu penuh bila server tidak mengirim daftar valid.
    if (!Array.isArray(serverMenus) || serverMenus.length === 0) {
        return [];
    }

    return serverMenus.map((item, index) => {
        if (typeof item === 'string') {
            const inferredKey = keyFromUrl(item) || `${item}-${index}`;
            return {
                key: inferredKey,
                label: item,
                href: keyFromUrl(item) ? item : '#',
                group: menuGroup(inferredKey),
            };
        }

        const href =
            item.href ||
            item.url ||
            item.path ||
            (typeof item.route === 'string' && item.route.startsWith('/')
                ? item.route
                : '#');

        return {
            key:
                item.key ||
                item.name ||
                keyFromUrl(href) ||
                (typeof item.route === 'string' && !item.route.startsWith('/')
                    ? item.route
                    : `menu-${index}`),
            label:
                item.label ||
                item.name ||
                item.title ||
                `Menu ${index + 1}`,
            href,
            route:
                typeof item.route === 'string' && !item.route.startsWith('/')
                    ? item.route
                    : undefined,
            permission: item.permission ?? item.permissions ?? item.can,
            active: typeof item.active === 'boolean' ? item.active : undefined,
            group: item.group || menuGroup(keyFromUrl(href) || item.key || item.name),
        };
    });
}

function menuGroup(key) {
    if (['dashboard', 'leaves', 'travels', 'settlements', 'medical-claims'].includes(key)) {
        return 'Ruang Kerja';
    }
    if (['approvals', 'reports'].includes(key)) {
        return 'Kendali';
    }
    return 'Administrasi';
}

function getPermissionSet(pageProps) {
    const collected = [
        ...(Array.isArray(pageProps?.auth?.permissions)
            ? pageProps.auth.permissions
            : []),
        ...(Array.isArray(pageProps?.auth?.user?.permissions)
            ? pageProps.auth.user.permissions
            : []),
        ...(Array.isArray(pageProps?.permissions)
            ? pageProps.permissions
            : []),
    ];

    return new Set(collected.filter((p) => typeof p === 'string'));
}

function isMenuActive(menu, currentUrl) {
    if (typeof menu.active === 'boolean') {
        return menu.active;
    }

    if (menu.route) {
        try {
            if (
                typeof route !== 'undefined' &&
                route().current &&
                route().current(menu.route)
            ) {
                return true;
            }
        } catch (e) {
            // Abaikan, lanjut ke pencocokan href.
        }
    }

    const href = menu.href;
    if (!href || href === '#') {
        return false;
    }

    const cleanUrl = String(currentUrl || '/')
        .split('?')[0]
        .split('#')[0];
    const cleanHref = String(href).split('?')[0];

    if (cleanHref === '/dashboard') {
        return cleanUrl === '/dashboard' || cleanUrl === '/';
    }

    return cleanUrl === cleanHref || cleanUrl.startsWith(`${cleanHref}/`);
}

function SidebarContent({ menus, currentUrl, onNavigate }) {
    const groups = ['Ruang Kerja', 'Kendali'];
    const adminMenus = menus.filter((menu) => menu.group === 'Administrasi');
    const settingMenus = menus.filter((menu) => menu.group === 'Pengaturan');

    const renderMenu = (menu) => {
        const active = isMenuActive(menu, currentUrl);
        return <li key={menu.key}><Link href={menu.href} onClick={onNavigate} className={active ? 'app-sidebar-link app-sidebar-link-active' : 'app-sidebar-link'}><span className="app-sidebar-icon"><MenuIcon menuKey={menu.key} /></span>{menu.label}</Link></li>;
    };

    return (
        <div className="app-sidebar flex grow flex-col gap-y-5 overflow-y-auto px-4 pb-4">
            <div className="flex h-16 shrink-0 items-center gap-2">
                <Link
                    href="/dashboard"
                    className="flex items-center gap-2"
                    onClick={onNavigate}
                >
                    <img
                        src="/images/logo-bp.svg"
                        alt="PT Borneo Prima"
                        className="block h-9 w-9 object-contain drop-shadow-[0_6px_14px_rgb(0_102_255_/_0.2)]"
                    />
                    <span className="flex flex-col gap-0.5">
                        <span className="text-xs font-extrabold tracking-[0.02em] text-white">
                            PT BORNEO PRIMA
                        </span>
                        <span className="text-[9px] font-bold uppercase tracking-[0.18em] text-blue-300">
                            eForm BP
                        </span>
                    </span>
                </Link>
            </div>
            <nav aria-label="Menu utama" className="flex flex-1 flex-col">
                <div className="space-y-6">
                    {groups.map((group) => {
                        const groupMenus = menus.filter((menu) => menu.group === group);
                        if (!groupMenus.length) return null;
                        return (
                            <section key={group}>
                                <p className="app-sidebar-label mb-2 px-3">{group}</p>
                                <ul role="list" className="-mx-2 space-y-1">
                                    {groupMenus.map(renderMenu)}
                                </ul>
                            </section>
                        );
                    })}
                    {adminMenus.length > 0 && <details className="app-sidebar-disclosure" open={adminMenus.some((menu) => isMenuActive(menu, currentUrl))}>
                        <summary className="app-sidebar-disclosure-title"><span>Administrasi</span><span aria-hidden="true">⌄</span></summary>
                        <div className="app-sidebar-subgroup"><p className="app-sidebar-label mb-2 px-3">Master Data</p><ul role="list" className="-mx-2 space-y-1">{adminMenus.map(renderMenu)}</ul></div>
                    </details>}
                    {settingMenus.length > 0 && <details className="app-sidebar-disclosure" open={settingMenus.some((menu) => isMenuActive(menu, currentUrl))}>
                        <summary className="app-sidebar-disclosure-title"><span>Pengaturan</span><span aria-hidden="true">⌄</span></summary>
                        <div className="app-sidebar-subgroup"><p className="app-sidebar-label mb-2 px-3">Workflow Approval</p><ul role="list" className="-mx-2 space-y-1">{settingMenus.map(renderMenu)}</ul></div>
                    </details>}
                </div>
                <p className="mt-auto px-3 pt-6 text-[11px] leading-4 text-gray-400">
                    Tampilan menu mengikuti hak akses. Otorisasi tetap
                    diperiksa di server.
                </p>
            </nav>
        </div>
    );
}

function NotificationBell() {
    const { props } = usePage();
    const [open, setOpen] = useState(false);
    const notifications = props.notifications ?? [];
    const unread = Number(props.unreadNotifications ?? 0);

    const openNotification = (notification) => {
        const visit = () => router.visit(notification.url || '/dashboard');
        if (notification.read_at) {
            visit();
            return;
        }
        router.post(route('notifications.read', notification.id), {}, {
            preserveScroll: true,
            onSuccess: visit,
        });
    };

    return (
        <div className="relative">
            <button type="button" className="app-notification-trigger" aria-label={`Notifikasi${unread ? `, ${unread} belum dibaca` : ''}`} aria-expanded={open} onClick={() => setOpen((value) => !value)}>
                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
                {unread > 0 && <span className="app-notification-count">{unread > 99 ? '99+' : unread}</span>}
            </button>
            {open && <>
                <button className="fixed inset-0 z-40 cursor-default" aria-label="Tutup panel notifikasi" onClick={() => setOpen(false)} />
                <section className="ui-popover app-notification-panel absolute right-0 z-50 mt-3 w-[min(24rem,calc(100vw-2rem))] overflow-hidden">
                    <header className="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                        <div><h2 className="text-sm font-bold">Notifikasi</h2><p className="text-xs text-slate-500 dark:text-slate-400">{unread} belum dibaca</p></div>
                        {unread > 0 && <button type="button" onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })} className="text-xs font-semibold text-blue-600 hover:text-blue-700">Tandai semua dibaca</button>}
                    </header>
                    <div className="max-h-[min(24rem,65vh)] overflow-y-auto">
                        {notifications.length === 0 ? <div className="px-5 py-10 text-center"><p className="text-sm font-semibold">Belum ada notifikasi</p><p className="mt-1 text-xs text-slate-500 dark:text-slate-400">Pembaruan workflow akan muncul di sini.</p></div> : notifications.map((notification) => <button key={notification.id} type="button" onClick={() => openNotification(notification)} className={`app-notification-item w-full border-b border-slate-100 px-4 py-3 text-left transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/70 ${notification.read_at ? '' : 'app-notification-unread'}`}>
                            <span className="flex items-start gap-3"><span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-blue-600" style={{ opacity: notification.read_at ? 0 : 1 }} /><span className="min-w-0"><span className="block text-sm font-semibold">{notification.title}</span><span className="mt-0.5 block text-xs leading-5 text-slate-500 dark:text-slate-400">{notification.message}</span><span className="mt-1 block text-[11px] text-slate-400">{notification.created_at ? new Date(notification.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : ''}</span></span></span>
                        </button>)}
                    </div>
                    <footer className="border-t border-slate-200 p-3 text-center dark:border-slate-700"><Link href={route('notifications.index')} onClick={() => setOpen(false)} className="text-xs font-bold text-blue-700 hover:underline dark:text-blue-400">Lihat semua notifikasi</Link></footer>
                </section>
            </>}
        </div>
    );
}

export default function AuthenticatedLayout({
    header,
    title,
    breadcrumbs = [],
    children,
}) {
    const { props, url } = usePage();
    const user = props.auth?.user;

    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [darkMode, setDarkMode] = useState(() => {
        if (typeof window === 'undefined') return false;
        const savedTheme = localStorage.getItem('eform-theme');
        return savedTheme ? savedTheme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    });

    useEffect(() => {
        document.documentElement.classList.toggle('dark', darkMode);
        localStorage.setItem('eform-theme', darkMode ? 'dark' : 'light');
    }, [darkMode]);

    const normalizedMenus = normalizeMenus(props.menus);
    const permissionSet = getPermissionSet(props);
    const canObject =
        props.can && typeof props.can === 'object' ? props.can : {};
    const hasPermissionInfo =
        permissionSet.size > 0 || Object.keys(canObject).length > 0;

    const visibleMenus = normalizedMenus.filter((menu) => {
        const need = menu.permission;
        if (!need) {
            return true;
        }
        // Fail-closed (D6/D7): bila menu butuh permission tapi info permission
        // tidak ada dari backend, sembunyikan menu. Backend tetap sumber kebenaran.
        if (!hasPermissionInfo) {
            return false;
        }
        if (typeof need === 'string') {
            return permissionSet.has(need) || Boolean(canObject[need]);
        }
        if (Array.isArray(need)) {
            return need.some(
                (p) => permissionSet.has(p) || Boolean(canObject[p]),
            );
        }
        return true;
    });

    const pageTitle = title || null;
    const showHeader =
        Boolean(header) || Boolean(pageTitle) || breadcrumbs.length > 0;

    return (
            <div className="app-shell min-h-screen bg-slate-50 text-slate-900 transition-colors">
            {/* Sidebar desktop */}
            <aside className="app-sidebar-frame hidden border-r lg:fixed lg:inset-y-0 lg:z-30 lg:flex lg:w-64 lg:flex-col">
                <SidebarContent menus={visibleMenus} currentUrl={url} />
            </aside>

            {/* Sidebar mobile */}
            <div
                className={`relative z-40 lg:hidden ${sidebarOpen ? '' : 'hidden'}`}
            >
                <div
                    className="fixed inset-0 bg-gray-900/50"
                    onClick={() => setSidebarOpen(false)}
                    aria-hidden="true"
                />
                <div className="app-mobile-sidebar fixed inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col border-r shadow-xl">
                    <div className="flex h-16 items-center justify-between px-4">
                        <span className="app-topbar-title text-sm font-semibold">
                            Menu eForm BP
                        </span>
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(false)}
                            aria-label="Tutup menu"
                            className="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none"
                        >
                            <svg
                                className="h-5 w-5"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                            </svg>
                        </button>
                    </div>
                    <div className="min-h-0 flex-1 overflow-y-auto">
                        <SidebarContent
                            menus={visibleMenus}
                            currentUrl={url}
                            onNavigate={() => setSidebarOpen(false)}
                        />
                    </div>
                </div>
            </div>

            <div className="flex min-h-screen flex-col lg:pl-64">
                {/* Topbar */}
                <nav className="app-topbar sticky top-0 z-30 border-b">
                    <div className="mx-auto flex h-16 max-w-7xl items-center gap-x-3 px-4 sm:px-6 lg:px-8">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(true)}
                            aria-label="Buka menu"
                            className="app-icon-button rounded-md p-2 focus:outline-none lg:hidden"
                        >
                            <svg
                                className="h-6 w-6"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>

                        <Link
                            href="/dashboard"
                            className="flex items-center gap-2 lg:hidden"
                        >
                            <ApplicationLogo className="block h-8 w-auto fill-current text-gray-800" />
                            <span className="app-topbar-title text-sm font-semibold">
                                eForm BP
                            </span>
                        </Link>

                        <div className="hidden min-w-0 flex-1 lg:block">
                            <p className="app-topbar-title truncate text-sm font-medium">
                                eForm BP — PT Borneo Prima
                            </p>
                            <p className="app-topbar-muted truncate text-xs">
                                Cuti/Izin, Perjalanan Dinas, Settlement, dan
                                Medical Claim
                            </p>
                        </div>

                        <div className="ms-auto flex items-center gap-2">
                            <NotificationBell />
                            <button type="button" onClick={() => setDarkMode((value) => !value)} className="app-theme-toggle" aria-label={darkMode ? 'Gunakan tema terang' : 'Gunakan tema gelap'}>
                                <span aria-hidden="true">{darkMode ? '☀' : '☾'}</span>
                                <span className="hidden sm:inline">{darkMode ? 'Terang' : 'Gelap'}</span>
                            </button>
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="app-user-trigger inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium leading-4 transition focus:outline-none"
                                            >
                                                {user?.name || 'Pengguna'}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Profil
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Keluar
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>
                    </div>
                </nav>

                {showHeader && (
                    <header className="app-page-header shadow-sm">
                        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {breadcrumbs.length > 0 && (
                                <nav
                                    aria-label="Breadcrumb"
                                     className="app-breadcrumb mb-2"
                                >
                                    <ol className="flex flex-wrap items-center gap-1 text-sm text-gray-500">
                                        {breadcrumbs.map((crumb, index) => {
                                            const label =
                                                typeof crumb === 'string'
                                                    ? crumb
                                                    : crumb.label;
                                            const href =
                                                typeof crumb === 'string'
                                                    ? undefined
                                                    : crumb.href;
                                            const isLast =
                                                index ===
                                                breadcrumbs.length - 1;

                                            return (
                                                <li
                                                    key={`${label}-${index}`}
                                                    className="flex items-center gap-1"
                                                >
                                                    {index > 0 && (
                                                        <span
                                                            aria-hidden="true"
                                                            className="text-gray-300"
                                                        >
                                                            /
                                                        </span>
                                                    )}
                                                    {href && !isLast ? (
                                                        <Link
                                                            href={href}
                                                            className="hover:text-gray-700 hover:underline"
                                                        >
                                                            {label}
                                                        </Link>
                                                    ) : (
                                                        <span
                                                            aria-current={
                                                                isLast
                                                                    ? 'page'
                                                                    : undefined
                                                            }
                                                            className={
                                                                isLast
                                                                 ? 'font-medium'
                                                                    : ''
                                                            }
                                                        >
                                                            {label}
                                                        </span>
                                                    )}
                                                </li>
                                            );
                                        })}
                                    </ol>
                                </nav>
                            )}
                            {header ||
                                (pageTitle && (
                                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                                        {pageTitle}
                                    </h2>
                                ))}
                        </div>
                    </header>
                )}

                <main className="flex-1">{children}</main>

                <footer className="app-footer border-t">
                    <div className="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-4 text-xs sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                        <p>
                            &copy; {new Date().getFullYear()} PT Borneo
                            Prima &mdash; eForm BP
                        </p>
                        <p>Versi 1.0 (MVP) &middot; Bahasa Indonesia</p>
                    </div>
                </footer>
            </div>

            <Toast />
        </div>
    );
}
