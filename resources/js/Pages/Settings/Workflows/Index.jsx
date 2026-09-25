import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const DEFAULT_OPTIONS = [
    { value: 'supervisor', label: 'Supervisor / SPT' },
    { value: 'hod', label: 'HOD' },
    { value: 'project_manager', label: 'Project Manager' },
    { value: 'hrga', label: 'HRGA' },
    { value: 'finance', label: 'Finance' },
];

const normalizeStep = (step = {}, index = 0) => ({
    id: step.id,
    step_order: index + 1,
    step_code: step.step_code ?? step.code ?? '',
    approver_role: step.approver_role ?? '',
    approver_resolver: step.approver_resolver ?? 'role_users',
    is_required: Boolean(step.is_required ?? true),
    can_skip_if_no_supervisor: Boolean(step.can_skip_if_no_supervisor),
});

const normalizeWorkflow = (workflow = {}) => ({
    name: workflow.name ?? '',
    is_active: Boolean(workflow.is_active),
    steps: (workflow.steps ?? []).map(normalizeStep),
});

const ErrorText = ({ children }) => children ? <p className="mt-1 text-xs font-medium text-red-600">{children}</p> : null;

const PREVIEW_LAYOUTS = [
    { key: 'horizontal', label: 'Stepper horizontal', description: 'Ringkas untuk melihat urutan tahap.' },
    { key: 'vertical', label: 'Timeline vertikal', description: 'Nyaman dibaca pada layar kecil.' },
    { key: 'swimlane', label: 'Alur per role', description: 'Memetakan tahap berdasarkan approver.' },
];

const optionLabel = (options, value, fallback = 'Belum ditentukan') => options.find((option) => option.value === value)?.label ?? value ?? fallback;

function WorkflowPreviewGallery({ steps, stepOptions, roleOptions }) {
    const [layout, setLayout] = useState('horizontal');
    const visibleSteps = steps.length ? steps : [{ step_code: '', approver_role: '', is_required: true }];
    const roleGroups = visibleSteps.reduce((groups, step, index) => {
        const role = optionLabel(roleOptions, step.approver_role, 'Role belum dipilih');
        const existing = groups.find((group) => group.role === role);
        if (existing) existing.steps.push({ step, index });
        else groups.push({ role, steps: [{ step, index }] });
        return groups;
    }, []);

    return <section aria-label="Pratinjau layout workflow" className="mb-6 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div><h3 className="font-semibold text-[#0F172A]">Pratinjau alur</h3><p className="mt-1 text-sm text-slate-500">Pilih tampilan untuk meninjau workflow. Ini hanya pratinjau lokal dan tidak mengubah konfigurasi.</p></div>
            <div role="tablist" aria-label="Pilihan layout pratinjau" className="grid grid-cols-1 gap-1 rounded-xl border border-slate-200 bg-white p-1 sm:grid-cols-3">
                {PREVIEW_LAYOUTS.map((item) => <button key={item.key} type="button" role="tab" aria-selected={layout === item.key} onClick={() => setLayout(item.key)} className={`rounded-lg px-3 py-2 text-left text-xs font-semibold transition sm:text-center ${layout === item.key ? 'bg-[#0066FF] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'}`}><span className="block">{item.label}</span><span className={`mt-0.5 block text-[10px] font-normal ${layout === item.key ? 'text-blue-100' : 'text-slate-400'}`}>{item.description}</span></button>)}
            </div>
        </div>
        <div className="mt-5 overflow-x-auto rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
            {layout === 'horizontal' && <div className="flex min-w-[520px] items-start">
                {visibleSteps.map((step, index) => <div key={step.id ?? `preview-${index}`} className="flex min-w-[125px] flex-1 items-start">
                    <div className="flex min-w-0 flex-1 flex-col items-center text-center"><div className="flex h-9 w-9 items-center justify-center rounded-full bg-[#EEF6FF] text-sm font-bold text-[#0066FF] ring-4 ring-white">{index + 1}</div><p className="mt-2 text-xs font-semibold text-slate-700">{optionLabel(stepOptions, step.step_code)}</p><p className="mt-1 text-[11px] text-slate-400">{optionLabel(roleOptions, step.approver_role)}</p></div>{index < visibleSteps.length - 1 && <div className="mt-4 h-0.5 flex-1 bg-blue-100" />}
                </div>)}
            </div>}
            {layout === 'vertical' && <ol className="mx-auto max-w-xl space-y-0">
                {visibleSteps.map((step, index) => <li key={step.id ?? `preview-${index}`} className="relative flex gap-4 pb-5 last:pb-0"><div className="absolute left-4 top-9 h-full w-px bg-blue-100 last:hidden" /><div className="z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#0066FF] text-xs font-bold text-white">{index + 1}</div><div className="min-w-0 flex-1 rounded-lg border border-slate-100 bg-slate-50 p-3"><p className="text-sm font-semibold text-slate-700">{optionLabel(stepOptions, step.step_code)}</p><p className="mt-1 text-xs text-slate-500">Penanggung jawab: {optionLabel(roleOptions, step.approver_role)}</p><span className="mt-2 inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700">{step.is_required ? 'Wajib' : 'Opsional'}</span></div></li>)}
            </ol>}
            {layout === 'swimlane' && <div className="min-w-[560px] space-y-3">{roleGroups.map((group) => <div key={group.role} className="grid grid-cols-[minmax(130px,0.7fr)_1fr] items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3"><div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Role</p><p className="mt-1 text-sm font-semibold text-slate-700">{group.role}</p></div><div className="flex flex-wrap gap-2">{group.steps.map(({ step, index }) => <div key={step.id ?? `lane-${index}`} className="rounded-lg border border-blue-100 bg-white px-3 py-2 shadow-sm"><p className="text-xs font-semibold text-[#0066FF]">Tahap {index + 1}</p><p className="mt-0.5 text-xs text-slate-600">{optionLabel(stepOptions, step.step_code)}</p></div>)}</div></div>)}</div>}
        </div>
    </section>;
}

function WorkflowCard({ workflow, stepOptions, roleOptions }) {
    const [confirming, setConfirming] = useState(false);
    const { data, setData, put, processing, errors } = useForm(normalizeWorkflow(workflow));
    const steps = data.steps ?? [];
    const setStep = (index, key, value) => setData('steps', steps.map((step, i) => i === index ? { ...step, [key]: value } : step));
    const reorder = (index, direction) => {
        const target = index + direction;
        if (target < 0 || target >= steps.length) return;
        const next = [...steps];
        [next[index], next[target]] = [next[target], next[index]];
        setData('steps', next.map((step, i) => ({ ...step, step_order: i + 1 })));
    };
    const addStep = () => setData('steps', [...steps, normalizeStep({}, steps.length)]);
    const removeStep = (index) => setData('steps', steps.filter((_, i) => i !== index).map((step, i) => ({ ...step, step_order: i + 1 })));
    const save = () => put(workflow.update_url ?? workflow.updateUrl ?? `/settings/workflows/${workflow.id}`, { preserveScroll: true, onSuccess: () => setConfirming(false) });

    return <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-col gap-4 border-b border-slate-100 bg-slate-50/70 p-5 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0 flex-1"><p className="mb-1 text-xs font-bold uppercase tracking-[0.16em] text-[#0066FF]">{workflow.entity_type ?? 'Workflow'}</p><label htmlFor={`workflow-name-${workflow.id}`} className="block text-sm font-semibold text-[#0F172A]">Nama workflow</label><input id={`workflow-name-${workflow.id}`} value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-2 w-full max-w-xl rounded-xl border-slate-300 text-sm shadow-sm focus:border-[#0066FF] focus:ring-[#0066FF]" /><ErrorText>{errors.name}</ErrorText></div>
            <label className="flex shrink-0 cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700"><input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-slate-300 text-[#0066FF] focus:ring-[#0066FF]" />Workflow aktif</label>
        </div>
         <div className="p-5"><WorkflowPreviewGallery steps={steps} stepOptions={stepOptions} roleOptions={roleOptions} /><div className="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><h3 className="font-semibold text-[#0F172A]">Urutan persetujuan</h3><p className="text-sm text-slate-500">Atur tahap yang harus dilalui sesuai matrix perusahaan.</p></div><span className="text-xs font-medium text-slate-400">{steps.length} tahap</span></div>
            <div className="space-y-3">{steps.map((step, index) => <div key={step.id ?? `new-${index}`} className="rounded-xl border border-slate-200 p-4"><div className="flex items-start gap-3"><div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#EEF6FF] text-sm font-bold text-[#0066FF]">{index + 1}</div><div className="min-w-0 flex-1 space-y-3"><div className="grid gap-3 md:grid-cols-2"><label className="text-sm font-medium text-slate-700">Kode tahap<select value={step.step_code} onChange={(e) => setStep(index, 'step_code', e.target.value)} className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-[#0066FF] focus:ring-[#0066FF]"><option value="">Pilih tahap</option>{stepOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select><ErrorText>{errors[`steps.${index}.step_code`]}</ErrorText></label><label className="text-sm font-medium text-slate-700">Role approver<select value={step.approver_role} onChange={(e) => setStep(index, 'approver_role', e.target.value)} className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-[#0066FF] focus:ring-[#0066FF]"><option value="">Pilih role</option>{roleOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select><ErrorText>{errors[`steps.${index}.approver_role`]}</ErrorText></label></div><div className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-600"><label className="flex items-center gap-2"><input type="checkbox" checked={step.is_required} onChange={(e) => setStep(index, 'is_required', e.target.checked)} className="rounded border-slate-300 text-[#0066FF] focus:ring-[#0066FF]" />Wajib</label><label className="flex items-center gap-2"><input type="checkbox" checked={step.can_skip_if_no_supervisor} onChange={(e) => setStep(index, 'can_skip_if_no_supervisor', e.target.checked)} className="rounded border-slate-300 text-[#0066FF] focus:ring-[#0066FF]" />Lewati jika tidak ada atasan</label></div></div><div className="flex shrink-0 flex-col gap-1 sm:flex-row"><button type="button" onClick={() => reorder(index, -1)} disabled={index === 0} aria-label="Naikkan tahap" className="rounded-lg p-2 text-slate-400 hover:bg-[#EEF6FF] hover:text-[#0066FF] disabled:opacity-30">↑</button><button type="button" onClick={() => reorder(index, 1)} disabled={index === steps.length - 1} aria-label="Turunkan tahap" className="rounded-lg p-2 text-slate-400 hover:bg-[#EEF6FF] hover:text-[#0066FF] disabled:opacity-30">↓</button><button type="button" onClick={() => removeStep(index)} aria-label="Hapus tahap" className="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600">×</button></div></div></div>)}</div>
            <button type="button" onClick={addStep} className="mt-4 rounded-xl border border-dashed border-[#0066FF] px-4 py-2 text-sm font-semibold text-[#0066FF] hover:bg-[#EEF6FF]">+ Tambah tahap</button><ErrorText>{errors.steps}</ErrorText>
            <div className="mt-6 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-end">{confirming ? <div className="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 sm:flex-row sm:items-center"><span>Perubahan workflow akan memengaruhi pengajuan berikutnya. Simpan perubahan?</span><div className="flex gap-2"><button type="button" onClick={() => setConfirming(false)} className="rounded-lg px-3 py-2 font-medium hover:bg-amber-100">Batal</button><button type="button" onClick={save} disabled={processing} className="rounded-lg bg-[#F59E0B] px-3 py-2 font-semibold text-white hover:bg-amber-600 disabled:opacity-60">{processing ? 'Menyimpan...' : 'Ya, simpan'}</button></div></div> : <button type="button" onClick={() => setConfirming(true)} disabled={processing} className="rounded-xl bg-[#0066FF] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60">Simpan workflow</button>}</div>
        </div>
    </section>;
}

const workflowScopes = [
    { key: 'leave', label: 'Cuti / Izin' },
    { key: 'travel', label: 'Perjalanan Dinas' },
    { key: 'settlement', label: 'Settlement' },
    { key: 'medical-claim', label: 'Medical Claim' },
];

export default function Index({ workflows = [], stepCodes = [], approverRoles = [], scope = null }) {
    const toOptions = (items) => (items.length ? items : DEFAULT_OPTIONS).map((item) => typeof item === 'string' ? { value: item, label: item } : { value: item.value ?? item.code ?? item.key, label: item.label ?? item.name ?? item.value ?? item.code });
    const stepOptions = toOptions(stepCodes); const roleOptions = toOptions(approverRoles);
    const selectedLabel = workflowScopes.find((item) => item.key === scope)?.label;
    const title = selectedLabel ? `Workflow ${selectedLabel}` : 'Pengaturan Workflow';
    return <AuthenticatedLayout title={title} breadcrumbs={[{ label: 'Administrasi', href: '/settings/workflows' }, { label: 'Pengaturan' }, ...(selectedLabel ? [{ label: selectedLabel }] : [{ label: 'Workflow' }])]}><Head title={title} /><div className="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6 lg:px-8"><div className="rounded-2xl border border-[#BFDBFE] bg-[#EEF6FF] p-4 text-sm leading-6 text-[#1E3A8A]">Workflow menentukan urutan pemeriksaan pengajuan berikutnya. Perubahan tidak mengubah chain approval yang sudah berjalan.</div><nav aria-label="Pilih workflow" className="flex gap-2 overflow-x-auto rounded-xl border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900"><Link href={route('settings.workflows.index')} className={`shrink-0 rounded-lg px-3 py-2 text-sm font-semibold ${!scope ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}`}>Semua</Link>{workflowScopes.map((item) => <Link key={item.key} href={route('settings.workflows.scope', item.key)} className={`shrink-0 rounded-lg px-3 py-2 text-sm font-semibold ${scope === item.key ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}`}>{item.label}</Link>)}</nav>{workflows.length ? workflows.map((workflow) => <WorkflowCard key={workflow.id ?? workflow.code} workflow={workflow} stepOptions={stepOptions} roleOptions={roleOptions} />) : <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900">Belum ada konfigurasi workflow untuk modul ini.</div>}</div></AuthenticatedLayout>;
}
