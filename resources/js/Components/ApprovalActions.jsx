import Modal from '@/Components/Modal';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const labels = { approve: 'Setujui', return: 'Kembalikan', reject: 'Tolak' };

export default function ApprovalActions({ approval = null }) {
    const { errors = {} } = usePage().props;
    const [action, setAction] = useState(null);
    const [comments, setComments] = useState('');
    const [processing, setProcessing] = useState(false);

    if (!approval) return null;

    const submit = (event) => {
        event.preventDefault();
        setProcessing(true);
        router.post(route('approvals.action', [approval.id, action]), { comments }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                setAction(null);
                setComments('');
            },
        });
    };

    return (
        <>
            <div className="flex flex-wrap gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/30">
                <span className="mr-1 self-center text-xs font-semibold text-blue-900 dark:text-blue-200">Tindakan approval</span>
                {['approve', 'return', 'reject'].filter((name) => approval[name]).map((name) => (
                    <button key={name} type="button" onClick={() => setAction(name)} className={`rounded-lg px-3 py-2 text-sm font-bold ${name === 'approve' ? 'bg-blue-600 text-white hover:bg-blue-700' : name === 'reject' ? 'border border-rose-300 bg-white text-rose-700 hover:bg-rose-50' : 'border border-amber-300 bg-white text-amber-800 hover:bg-amber-50'}`}>
                        {labels[name]}
                    </button>
                ))}
            </div>

            <Modal show={Boolean(action)} onClose={() => setAction(null)} maxWidth="md">
                <form onSubmit={submit} className="p-6">
                    <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                        {action === 'approve' ? 'Setujui pengajuan?' : action === 'return' ? 'Kembalikan untuk perbaikan?' : 'Tolak pengajuan?'}
                    </h2>
                    {action !== 'approve' && <div className="mt-4"><label htmlFor="transaction-approval-comments" className="text-sm font-medium">Catatan wajib</label><textarea id="transaction-approval-comments" required value={comments} onChange={(event) => setComments(event.target.value)} className="mt-1 block min-h-28 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Tuliskan catatan untuk pemohon..." />{errors.comments && <p className="mt-1 text-sm text-rose-600">{errors.comments}</p>}</div>}
                    {errors.approval && <p className="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{errors.approval}</p>}
                    <div className="mt-5 flex justify-end gap-2"><button type="button" onClick={() => setAction(null)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm">Batal</button><button type="submit" disabled={processing} className="ui-button-primary rounded-lg px-4 py-2 text-sm disabled:opacity-60">{processing ? 'Memproses...' : labels[action]}</button></div>
                </form>
            </Modal>
        </>
    );
}
