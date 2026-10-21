import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const ACCENTS = [
    'border-t-blue-600',
    'border-t-emerald-500',
    'border-t-amber-400',
    'border-t-violet-500',
    'border-t-rose-400',
];

const SECONDARY_BUTTON = 'inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

function initials(name) {
    return String(name ?? '?')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function collectIds(nodes, ids = []) {
    for (const node of nodes ?? []) {
        ids.push(node.id);
        collectIds(node.children, ids);
    }

    return ids;
}

function collapseFromDepth(nodes, depth = 0, ids = new Set()) {
    for (const node of nodes ?? []) {
        if (depth >= 1 && (node.children?.length ?? 0) > 0) {
            ids.add(node.id);
        }
        collapseFromDepth(node.children, depth + 1, ids);
    }

    return ids;
}

function initialCollapsed(groups) {
    const ids = new Set();

    for (const group of groups ?? []) {
        collapseFromDepth(group.tree, 0, ids);
    }

    return ids;
}

function matchesQuery(node, query) {
    return [node.name, node.employee_number, node.department, node.job_title, node.level, node.site]
        .some((value) => String(value ?? '').toLowerCase().includes(query));
}

function filterByQuery(nodes, query) {
    const output = [];

    for (const node of nodes ?? []) {
        if (matchesQuery(node, query)) {
            output.push(node);
            continue;
        }

        const children = filterByQuery(node.children, query);
        if (children.length) {
            output.push({ ...node, children });
        }
    }

    return output;
}

function OrgNode({ node, depth, collapsed, onToggle, forceOpen }) {
    const hasChildren = (node.children?.length ?? 0) > 0;
    const isOpen = forceOpen || !collapsed.has(node.id);

    return (
        <li className="org-node">
            <article className={`org-card ui-card border-t-4 p-3 ${ACCENTS[depth % ACCENTS.length]}`}>
                <div className="flex items-start gap-2.5">
                    <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-extrabold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                        {initials(node.name)}
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-1.5">
                            <h3 className="truncate text-sm font-bold text-slate-900 dark:text-slate-100" title={node.name}>{node.name}</h3>
                            <span
                                className={`h-2 w-2 shrink-0 rounded-full ${node.active ? 'bg-emerald-500' : 'bg-slate-400'}`}
                                title={node.active ? 'Aktif' : 'Nonaktif'}
                                aria-label={node.active ? 'Aktif' : 'Nonaktif'}
                            />
                        </div>
                        <p className="font-mono text-[10px] font-semibold text-slate-500 dark:text-slate-400">{node.employee_number}</p>
                    </div>
                </div>

                <p className="mt-2 line-clamp-2 text-xs font-semibold text-slate-700 dark:text-slate-200" title={node.job_title ?? ''}>
                    {node.job_title ?? 'Jabatan belum diisi'}
                </p>
                <p className="mt-0.5 truncate text-[11px] text-slate-500 dark:text-slate-400" title={`${node.department ?? '-'} · Level ${node.level ?? '-'}`}>
                    {node.department ?? 'Departemen belum diisi'} &middot; Level {node.level ?? '-'}
                </p>
                <p className="mt-1 truncate text-[10px] text-slate-400 dark:text-slate-500" title={node.hod_name ? `HOD: ${node.hod_name}` : 'HOD belum diisi'}>
                    HOD: {node.hod_name ?? '-'}
                </p>

                <div className="mt-2 flex items-center justify-between gap-2 border-t border-slate-100 pt-2 dark:border-slate-800">
                    <span className="text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                        {node.direct_reports} langsung &middot; {node.total_reports} total
                    </span>
                    <Link
                        href={route('master.employees.show', node.id)}
                        className="rounded px-1.5 py-0.5 text-[10px] font-bold text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/50"
                    >
                        Detail &rarr;
                    </Link>
                </div>

                {hasChildren && (
                    <button
                        type="button"
                        onClick={() => onToggle(node.id)}
                        aria-expanded={isOpen}
                        className="mt-2 inline-flex w-full items-center justify-center gap-1 rounded-md border border-slate-200 px-2 py-1 text-[10px] font-bold text-slate-600 transition hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-blue-700 dark:hover:text-blue-300"
                    >
                        <svg viewBox="0 0 20 20" fill="none" className={`h-3 w-3 transition-transform ${isOpen ? 'rotate-180' : ''}`} aria-hidden="true">
                            <path d="M5 8l5 5 5-5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                        {isOpen ? 'Sembunyikan' : 'Tampilkan'} {node.children.length} bawahan
                    </button>
                )}
            </article>

            {hasChildren && isOpen && (
                <ul className="org-children">
                    {node.children.map((child) => (
                        <OrgNode
                            key={child.id}
                            node={child}
                            depth={depth + 1}
                            collapsed={collapsed}
                            onToggle={onToggle}
                            forceOpen={forceOpen}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

export default function OrgChart({ groups, departments, summary, filters }) {
    const [active, setActive] = useState(filters?.active ?? '1');
    const [query, setQuery] = useState('');
    const [department, setDepartment] = useState('all');
    const [collapsed, setCollapsed] = useState(() => initialCollapsed(groups));

    const normalizedQuery = query.trim().toLowerCase();
    const isFiltering = normalizedQuery !== '' || department !== 'all';

    const filteredGroups = useMemo(() => {
        return (groups ?? [])
            .filter((group) => department === 'all' || group.department === department)
            .map((group) => (
                normalizedQuery === '' ? group : { ...group, tree: filterByQuery(group.tree, normalizedQuery) }
            ))
            .filter((group) => group.tree.length > 0);
    }, [groups, department, normalizedQuery]);

    const visibleIds = filteredGroups.flatMap((group) => collectIds(group.tree));

    const toggle = (id) => {
        setCollapsed((previous) => {
            const next = new Set(previous);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const applyStatus = (event) => {
        event.preventDefault();
        router.get(route('master.employees.org-chart'), { active }, { preserveScroll: true, preserveState: false });
    };

    const resetFilters = () => {
        setQuery('');
        setDepartment('all');
        setCollapsed(initialCollapsed(groups));
    };

    return (
        <AuthenticatedLayout title="Struktur Organisasi" breadcrumbs={['Administrasi', 'Master Data', 'Karyawan', 'Struktur Organisasi']}>
            <Head title="Struktur Organisasi" />

            <div className="mx-auto max-w-7xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Administrasi / Master Data / Karyawan</p>
                        <h1 className="mt-1 text-2xl font-extrabold tracking-tight">Struktur Organisasi</h1>
                        <p className="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                            Grafik ditampilkan per departemen dan hierarki mengikuti atasan langsung pada master karyawan.
                        </p>
                    </div>
                    <Link href={route('master.employees.index')} className={`${SECONDARY_BUTTON} w-fit`}>
                        Kembali ke daftar
                    </Link>
                </header>

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <article className="ui-card border-l-4 border-l-blue-600 p-4">
                        <p className="text-xs font-medium text-slate-500">Karyawan ditampilkan</p>
                        <p className="mt-1 text-2xl font-extrabold">{summary?.total ?? 0}</p>
                        <p className="mt-1 text-xs text-slate-500">Sesuai filter status</p>
                    </article>
                    <article className="ui-card border-l-4 border-l-violet-500 p-4">
                        <p className="text-xs font-medium text-slate-500">Puncak struktur</p>
                        <p className="mt-1 text-2xl font-extrabold">{summary?.roots ?? 0}</p>
                        <p className="mt-1 text-xs text-slate-500">Akar pohon per departemen</p>
                    </article>
                    <article className="ui-card border-l-4 border-l-emerald-500 p-4">
                        <p className="text-xs font-medium text-slate-500">Karyawan aktif</p>
                        <p className="mt-1 text-2xl font-extrabold">{summary?.active ?? 0}</p>
                        <p className="mt-1 text-xs text-slate-500">Dari data yang ditampilkan</p>
                    </article>
                    <article className="ui-card border-l-4 border-l-amber-400 p-4">
                        <p className="text-xs font-medium text-slate-500">Departemen</p>
                        <p className="mt-1 text-2xl font-extrabold">{summary?.departments ?? 0}</p>
                        <p className="mt-1 text-xs text-slate-500">Grup pohon organisasi</p>
                    </article>
                </section>

                <section className="ui-card p-4">
                    <form onSubmit={applyStatus} className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label htmlFor="org-search" className="text-xs font-semibold text-slate-600 dark:text-slate-300">Cari karyawan</label>
                            <input
                                id="org-search"
                                type="search"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Nama, NIK, jabatan, atau departemen"
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                        </div>
                        <div>
                            <label htmlFor="org-department" className="text-xs font-semibold text-slate-600 dark:text-slate-300">Tampilkan departemen</label>
                            <select
                                id="org-department"
                                value={department}
                                onChange={(event) => setDepartment(event.target.value)}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                <option value="all">Semua departemen</option>
                                {(departments ?? []).map((item) => <option key={item} value={item}>{item}</option>)}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="org-active" className="text-xs font-semibold text-slate-600 dark:text-slate-300">Status karyawan</label>
                            <select
                                id="org-active"
                                value={active}
                                onChange={(event) => setActive(event.target.value)}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                                <option value="all">Semua</option>
                            </select>
                        </div>
                        <div className="flex items-end gap-2">
                            <button type="submit" className="ui-button-primary rounded-lg px-4 py-2 text-sm">Terapkan</button>
                            <button type="button" onClick={resetFilters} className={SECONDARY_BUTTON}>Reset</button>
                        </div>
                    </form>

                    <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                        <button type="button" onClick={() => setCollapsed(new Set())} className="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:text-slate-300">
                            Perluas semua
                        </button>
                        <button type="button" onClick={() => setCollapsed(new Set(visibleIds))} className="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:text-slate-300">
                            Tutup semua
                        </button>
                        {isFiltering && (
                            <span className="text-xs font-medium text-blue-700 dark:text-blue-300">
                                Menampilkan {visibleIds.length} dari {summary?.total ?? 0} karyawan
                            </span>
                        )}
                        <span className="ml-auto hidden text-xs text-slate-500 sm:inline dark:text-slate-400">
                            Klik tombol pada kartu untuk membuka cabang; geser ke samping bila struktur melebar.
                        </span>
                    </div>
                </section>

                {filteredGroups.length ? (
                    <div className="space-y-5">
                        {filteredGroups.map((group) => {
                            const visibleCount = collectIds(group.tree).length;

                            return (
                                <section key={group.department} className="ui-card overflow-hidden">
                                    <header className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50/70 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/40">
                                        <div>
                                            <p className="text-[10px] font-bold uppercase tracking-[0.15em] text-blue-600">Departemen</p>
                                            <h2 className="text-base font-extrabold text-slate-900 dark:text-slate-100">{group.department}</h2>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-1.5 text-[11px]">
                                            <span className="ui-badge bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                {visibleCount} karyawan
                                            </span>
                                            <span className="ui-badge bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                                                {group.roots} puncak
                                            </span>
                                        </div>
                                    </header>

                                    <div className="org-chart-scroll p-4">
                                        <ul className="org-tree">
                                            {group.tree.map((node) => (
                                                <OrgNode
                                                    key={node.id}
                                                    node={node}
                                                    depth={0}
                                                    collapsed={collapsed}
                                                    onToggle={toggle}
                                                    forceOpen={isFiltering}
                                                />
                                            ))}
                                        </ul>
                                    </div>
                                </section>
                            );
                        })}
                    </div>
                ) : (
                    <section className="ui-card p-5">
                        <EmptyState
                            title={isFiltering ? 'Karyawan tidak ditemukan' : 'Belum ada data struktur'}
                            description={isFiltering ? 'Ubah kata kunci, departemen, atau status karyawan.' : 'Lengkapi master karyawan beserta atasan langsung untuk membentuk struktur organisasi.'}
                        >
                            {isFiltering && (
                                <button type="button" onClick={resetFilters} className={SECONDARY_BUTTON}>Reset filter</button>
                            )}
                        </EmptyState>
                    </section>
                )}

                <p className="text-xs text-slate-500 dark:text-slate-400">
                    Setiap departemen memiliki pohon sendiri. Garis penghubung menunjukkan atasan langsung (<span className="font-mono">supervisor_id</span>); atasan di luar departemen dianggap puncak grup.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
