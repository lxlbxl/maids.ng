import { Head, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

export default function Disputes({ auth, disputes }) {
    const [selectedDispute, setSelectedDispute] = useState(null);
    const [refundProcessing, setRefundProcessing] = useState(false);
    const [toastMessage, setToastMessage] = useState(null);
    const { data, setData, post, processing } = useForm({
        notes: ''
    });

    const showToast = useCallback((message, type = 'success') => {
        setToastMessage({ message, type });
        setTimeout(() => setToastMessage(null), 4000);
    }, []);

    const handleResolve = (e) => {
        e.preventDefault();
        post(route('admin.disputes.resolve', selectedDispute.id), {
            onSuccess: () => {
                setSelectedDispute(null);
                setData('notes', '');
                showToast('Dispute resolved successfully.');
            },
            onError: () => showToast('Failed to resolve dispute.', 'error'),
        });
    };

    const handleRefund = () => {
        if (!selectedDispute) return;
        if (!confirm(`Initiate refund for dispute #DISP-${selectedDispute.id}? This will refund the employer's matching fee.`)) return;
        setRefundProcessing(true);
        router.post(`/admin/disputes/${selectedDispute.id}/refund`, {}, {
            preserveScroll: true,
            onSuccess: () => { showToast('Refund initiated successfully.'); setRefundProcessing(false); },
            onError: (e) => { showToast(e?.message || 'Failed to initiate refund.', 'error'); setRefundProcessing(false); },
        });
    };

    return (
        <AdminLayout>
            <Head title="Dispute Management | Mission Control" />

            {/* Toast */}
            <AnimatePresence>
                {toastMessage && (
                    <motion.div initial={{ opacity: 0, y: -30, x: '-50%' }} animate={{ opacity: 1, y: 0, x: '-50%' }} exit={{ opacity: 0, y: -30, x: '-50%' }}
                        className={`fixed top-6 left-1/2 z-50 px-6 py-3 rounded-brand-lg shadow-brand-3 text-sm font-medium ${toastMessage.type === 'error' ? 'bg-red-900/90 border border-red-500/30 text-red-100' : 'bg-teal/90 border border-teal/30 text-white'}`}
                    >{toastMessage.type === 'error' ? '✗ ' : '✓ '}{toastMessage.message}</motion.div>
                )}
            </AnimatePresence>
            
            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Dispute Resolution</h1>
                    <p className="text-white/40 text-sm font-light">Mediating conflicts between employers and helpers with administrative oversight.</p>
                </div>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-8">
                {/* Dispute List */}
                <div className="xl:col-span-2 bg-[#121214] border border-white/5 rounded-brand-lg overflow-hidden shadow-2xl">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm border-collapse">
                            <thead className="bg-[#0a0a0b] border-b border-white/5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/30">
                                <tr>
                                    <th className="px-8 py-5 font-bold">Status</th>
                                    <th className="px-8 py-5 font-bold">Priority</th>
                                    <th className="px-8 py-5 font-bold">Employer</th>
                                    <th className="px-8 py-5 font-bold">Reason</th>
                                    <th className="px-8 py-5 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-white/5">
                                {disputes.data.map(dispute => (
                                    <tr 
                                        key={dispute.id} 
                                        className={`hover:bg-white/[0.02] transition-colors cursor-pointer group ${selectedDispute?.id === dispute.id ? 'bg-white/5' : ''}`}
                                        onClick={() => setSelectedDispute(dispute)}
                                    >
                                        <td className="px-8 py-5">
                                            <span className={`px-2 py-0.5 rounded text-[10px] font-mono uppercase tracking-widest ${
                                                dispute.status === 'resolved' ? 'bg-success/10 text-success border border-success/20' : 
                                                'bg-copper/10 text-copper border border-copper/20 animate-pulse'
                                            }`}>
                                                {dispute.status}
                                            </span>
                                        </td>
                                        <td className="px-8 py-5">
                                            <span className={`text-[10px] font-mono uppercase ${dispute.priority === 'high' ? 'text-danger font-bold' : 'text-white/40'}`}>
                                                {dispute.priority}
                                            </span>
                                        </td>
                                        <td className="px-8 py-5">
                                            <div className="flex flex-col">
                                                <span className="font-bold text-white/80">{dispute.user?.name}</span>
                                                <span className="text-[10px] text-white/20 font-mono italic">#{dispute.user?.id}</span>
                                            </div>
                                        </td>
                                        <td className="px-8 py-5">
                                            <p className="text-xs text-white/60 line-clamp-1 italic">"{dispute.reason}"</p>
                                        </td>
                                        <td className="px-8 py-5 text-right">
                                            <button onClick={(e) => { e.stopPropagation(); setSelectedDispute(dispute); }} className="text-[10px] font-mono uppercase tracking-widest text-teal hover:underline font-bold cursor-pointer">Investigate →</button>
                                        </td>
                                    </tr>
                                ))}
                                {disputes.data.length === 0 && (
                                    <tr>
                                        <td colSpan="5" className="px-8 py-20 text-center">
                                            <div className="flex flex-col items-center gap-4 text-white/20">
                                                <span className="text-4xl">🕊️</span>
                                                <p className="text-sm font-mono uppercase tracking-widest">No Active Disputes Detected</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Tactical Intervention Panel */}
                <div className="space-y-6">
                    {selectedDispute ? (
                        <div className="bg-[#121214] border border-white/10 rounded-brand-xl p-8 sticky top-8 shadow-2xl animate-in fade-in slide-in-from-bottom-4 duration-500">
                            <div className="flex items-center justify-between mb-8 pb-4 border-b border-white/5">
                                <h2 className="font-display text-xl">Case #DISP-{selectedDispute.id}</h2>
                                <button onClick={() => setSelectedDispute(null)} className="text-white/20 hover:text-white transition-colors">✕</button>
                            </div>

                            <div className="space-y-8">
                                <div>
                                    <p className="font-mono text-[9px] uppercase tracking-[0.2em] text-white/30 mb-2">Subject Description</p>
                                    <p className="text-sm text-white/80 leading-relaxed italic border-l-2 border-copper/30 pl-4 py-1">
                                        "{selectedDispute.description}"
                                    </p>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="bg-white/5 p-4 rounded border border-white/5">
                                        <p className="font-mono text-[8px] uppercase tracking-widest text-white/30 mb-1">Employer</p>
                                        <p className="text-xs font-bold text-white/80">{selectedDispute.user?.name}</p>
                                    </div>
                                    <div className="bg-white/5 p-4 rounded border border-white/5">
                                        <p className="font-mono text-[8px] uppercase tracking-widest text-white/30 mb-1">Hired Helper</p>
                                        <p className="text-xs font-bold text-white/80">{selectedDispute.booking?.maid_profile?.user?.name || 'Assigned'}</p>
                                    </div>
                                </div>

                                {selectedDispute.status !== 'resolved' ? (
                                    <form onSubmit={handleResolve} className="space-y-6 pt-6 border-t border-white/5">
                                        <div className="space-y-2">
                                            <label className="font-mono text-[9px] uppercase tracking-widest text-white/30">Administrative Resolution Notes</label>
                                            <textarea 
                                                value={data.notes}
                                                onChange={e => setData('notes', e.target.value)}
                                                placeholder="Document final mediation decision..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-md p-4 text-xs text-white focus:border-teal/50 outline-none h-24 resize-none"
                                                required
                                            ></textarea>
                                        </div>
                                        <div className="flex flex-col gap-3">
                                            <button 
                                                type="submit"
                                                disabled={processing}
                                                className="w-full bg-teal text-espresso py-4 rounded-brand-lg text-[10px] font-bold uppercase tracking-widest hover:brightness-110 shadow-[0_0_20px_rgba(45,164,142,0.2)] transition-all"
                                            >
                                                {processing ? 'Processing...' : '✅ Resolve & Close Case'}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={handleRefund}
                                                disabled={refundProcessing}
                                                className="w-full bg-danger/10 text-danger border border-danger/20 py-3 rounded-brand-lg text-[10px] font-mono tracking-widest uppercase hover:bg-danger/20 transition-all disabled:opacity-50 cursor-pointer"
                                            >
                                                {refundProcessing ? '⏳ Processing...' : '🚨 Initiate Refund Protocol'}
                                            </button>
                                        </div>
                                    </form>
                                ) : (
                                    <div className="bg-teal/5 border border-teal/20 p-6 rounded-brand-lg text-center">
                                        <span className="text-2xl mb-2 block">✅</span>
                                        <p className="text-xs font-mono text-teal uppercase tracking-widest font-bold">Case Closed</p>
                                        <p className="text-[10px] text-white/40 mt-2 font-light">Resolution logged in platform history.</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    ) : (
                        <div className="bg-[#121214] border border-white/5 rounded-brand-xl p-8 text-center py-20 opacity-40">
                            <span className="text-4xl block mb-4">📂</span>
                            <p className="text-xs font-mono uppercase tracking-widest">Select a case from the list<br/>to begin mediation</p>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
