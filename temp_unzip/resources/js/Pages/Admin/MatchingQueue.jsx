import React, { useState, useCallback } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, usePage, router } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';

export default function MatchingQueue({ jobs: serverJobs, stats: serverStats }) {
    const { auth } = usePage().props;

    const defaultStats = {
        total_jobs: 0, pending: 0, processing: 0, completed: 0, failed: 0, requires_review: 0
    };

    const stats = serverStats && Object.keys(serverStats).length > 0 ? { ...defaultStats, ...serverStats } : defaultStats;
    const [filter, setFilter] = useState('all');
    const [processingId, setProcessingId] = useState(null);
    const [selectedJob, setSelectedJob] = useState(null);
    const [toastMessage, setToastMessage] = useState(null);

    const allJobs = (serverJobs && serverJobs.length > 0) ? serverJobs : [
        { job_id: 'MATCH-7829', employer: { name: 'Dr. Chima Okoro' }, status: 'processing', priority: 8, created_at: '2026-04-28T10:15:00Z', ai_confidence_score: 0.92, requires_review: false },
        { job_id: 'MATCH-7830', employer: { name: 'Mrs. Funmi Adebayo' }, status: 'pending', priority: 5, created_at: '2026-04-28T11:20:00Z', ai_confidence_score: null, requires_review: false },
        { job_id: 'MATCH-7825', employer: { name: 'Chief Emeka' }, status: 'completed', priority: 10, created_at: '2026-04-28T08:05:00Z', completed_at: '2026-04-28T08:12:00Z', ai_confidence_score: 0.88, requires_review: true }
    ];

    const jobs = filter === 'all' ? allJobs :
        filter === 'review' ? allJobs.filter(j => j.requires_review) :
        allJobs.filter(j => j.status === filter);

    const showToast = useCallback((message, type = 'success') => {
        setToastMessage({ message, type });
        setTimeout(() => setToastMessage(null), 4000);
    }, []);

    const fetchData = () => {
        router.reload({ only: ['jobs', 'stats'] });
        showToast('Queue data refreshed.');
    };

    const handleForceProcess = () => {
        if (!confirm('Force-process all pending items in the AI matching queue? This will trigger the matching algorithm for all pending jobs.')) return;
        setProcessingId('force');
        router.post('/admin/matching/force-process', {}, {
            preserveScroll: true,
            onSuccess: () => { showToast('Queue force-processing initiated.'); setProcessingId(null); },
            onError: (e) => { showToast(e?.message || 'Failed to process queue.', 'error'); setProcessingId(null); },
        });
    };

    const handleReview = (job) => {
        if (!confirm(`Approve the match result for ${job.employer.name} (${job.job_id})? This clears the review flag.`)) return;
        setProcessingId(job.job_id);
        router.post(`/admin/matching/${job.job_id}/approve`, {}, {
            preserveScroll: true,
            onSuccess: () => { showToast(`Match ${job.job_id} approved.`); setProcessingId(null); },
            onError: (e) => { showToast(e?.message || 'Failed to approve.', 'error'); setProcessingId(null); },
        });
    };

    const handleReject = (job) => {
        if (!confirm(`Reject the match result for ${job.employer.name} (${job.job_id})? This will mark the job as failed.`)) return;
        setProcessingId(job.job_id);
        router.post(`/admin/matching/${job.job_id}/reject`, {}, {
            preserveScroll: true,
            onSuccess: () => { showToast(`Match ${job.job_id} rejected.`); setProcessingId(null); },
            onError: (e) => { showToast(e?.message || 'Failed to reject.', 'error'); setProcessingId(null); },
        });
    };

    const handleRetry = (job) => {
        if (!confirm(`Re-queue ${job.job_id} for another AI matching attempt?`)) return;
        setProcessingId(job.job_id);
        router.post(`/admin/matching/${job.job_id}/retry`, {}, {
            preserveScroll: true,
            onSuccess: () => { showToast(`${job.job_id} re-queued.`); setProcessingId(null); },
            onError: (e) => { showToast(e?.message || 'Failed to retry.', 'error'); setProcessingId(null); },
        });
    };

    const handleInspect = (job) => setSelectedJob(job);

    const getStatusColor = (status) => {
        switch (status) {
            case 'completed': return 'text-success bg-success/10 border-success/20';
            case 'processing': return 'text-teal bg-teal/10 border-teal/20';
            case 'pending': return 'text-warning bg-warning/10 border-warning/20';
            case 'failed': return 'text-danger bg-danger/10 border-danger/20';
            default: return 'text-white/40 bg-white/5 border-white/10';
        }
    };

    return (
        <AdminLayout>
            <Head title="AI Matching Queue | Maids.ng" />

            <div className="space-y-8">
                {/* Toast */}
                <AnimatePresence>
                    {toastMessage && (
                        <motion.div initial={{ opacity: 0, y: -30, x: '-50%' }} animate={{ opacity: 1, y: 0, x: '-50%' }} exit={{ opacity: 0, y: -30, x: '-50%' }}
                            className={`fixed top-6 left-1/2 z-50 px-6 py-3 rounded-brand-lg shadow-brand-3 text-sm font-medium ${toastMessage.type === 'error' ? 'bg-red-900/90 border border-red-500/30 text-red-100' : 'bg-teal/90 border border-teal/30 text-white'}`}
                        >
                            {toastMessage.type === 'error' ? '✗ ' : '✓ '}{toastMessage.message}
                        </motion.div>
                    )}
                </AnimatePresence>

                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-4xl font-display text-white mb-2">AI Matching Queue</h1>
                        <p className="text-white/50 max-w-2xl">Monitor and manage the Sentinel AI matching engine. Real-time visualization of employer-maid pairing processes.</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button onClick={fetchData} className="px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-brand-md text-sm transition-all flex items-center gap-2 text-white/70 hover:text-white">
                            <span>🔄</span> Refresh Status
                        </button>
                        <button onClick={handleForceProcess} disabled={processingId === 'force'}
                            className="px-6 py-2 bg-teal hover:bg-teal-mid text-white rounded-brand-md text-sm font-bold shadow-brand-2 transition-all disabled:opacity-50 disabled:cursor-wait"
                        >
                            {processingId === 'force' ? '⏳ Processing...' : 'Force Process Queue'}
                        </button>
                    </div>
                </div>

                {/* Statistics Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {[
                        { label: 'Active Processes', value: stats.processing, color: 'teal', icon: '⚡' },
                        { label: 'Pending in Queue', value: stats.pending, color: 'warning', icon: '⏳' },
                        { label: 'Completed', value: stats.completed, color: 'success', icon: '✅' },
                        { label: 'Manual Reviews', value: stats.requires_review, color: 'copper', icon: '🔍' },
                    ].map((s, idx) => (
                        <motion.div key={idx} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: idx * 0.1 }}
                            className="bg-[#121214] border border-white/5 p-6 rounded-brand-xl relative overflow-hidden group"
                        >
                            <div className="absolute top-0 right-0 p-4 text-2xl opacity-20 group-hover:opacity-40 transition-opacity">{s.icon}</div>
                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/30 mb-1">{s.label}</p>
                            <h3 className="text-3xl font-display text-white">{s.value}</h3>
                            <div className={`mt-4 h-1 w-12 rounded-full bg-${s.color === 'copper' ? 'copper' : s.color}`}></div>
                        </motion.div>
                    ))}
                </div>

                {/* Queue Table */}
                <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                    <div className="px-8 py-6 border-b border-white/5 flex items-center justify-between">
                        <h2 className="font-display text-xl text-white">Live Operations</h2>
                        <div className="flex bg-white/5 p-1 rounded-brand-lg">
                            {['all', 'pending', 'processing', 'completed', 'failed', 'review'].map(f => (
                                <button key={f} onClick={() => setFilter(f)}
                                    className={`px-4 py-1.5 text-xs rounded-brand-md transition-all capitalize ${filter === f ? 'bg-teal text-white shadow-brand-1' : 'text-white/40 hover:text-white'}`}
                                >{f}</button>
                            ))}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-white/[0.02] text-[10px] font-mono uppercase tracking-widest text-white/30">
                                    <th className="px-8 py-4 font-bold">Process ID</th>
                                    <th className="px-8 py-4 font-bold">Employer</th>
                                    <th className="px-8 py-4 font-bold">Priority</th>
                                    <th className="px-8 py-4 font-bold">Status</th>
                                    <th className="px-8 py-4 font-bold">AI Confidence</th>
                                    <th className="px-8 py-4 font-bold">Created</th>
                                    <th className="px-8 py-4 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-white/5">
                                {jobs.length === 0 && (
                                    <tr><td colSpan={7} className="px-8 py-12 text-center text-white/30 text-sm">No {filter === 'all' ? '' : filter} jobs found.</td></tr>
                                )}
                                <AnimatePresence mode="popLayout">
                                    {jobs.map((job) => (
                                        <motion.tr key={job.job_id} initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="group hover:bg-white/[0.01] transition-colors">
                                            <td className="px-8 py-5"><span className="font-mono text-xs text-teal font-bold">{job.job_id}</span></td>
                                            <td className="px-8 py-5 text-sm text-white/80">{job.employer.name}</td>
                                            <td className="px-8 py-5">
                                                <div className="flex items-center gap-1">
                                                    {[...Array(5)].map((_, i) => (
                                                        <div key={i} className={`w-1.5 h-1.5 rounded-full ${i < job.priority / 2 ? 'bg-copper shadow-[0_0_5px_rgba(184,115,51,0.5)]' : 'bg-white/10'}`}></div>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="px-8 py-5">
                                                <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border ${getStatusColor(job.status)}`}>{job.status}</span>
                                            </td>
                                            <td className="px-8 py-5">
                                                {job.ai_confidence_score ? (
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex-1 h-1.5 bg-white/5 rounded-full w-20 overflow-hidden">
                                                            <div className="h-full bg-teal shadow-[0_0_8px_rgba(45,164,142,0.6)]" style={{ width: `${job.ai_confidence_score * 100}%` }}></div>
                                                        </div>
                                                        <span className="text-[11px] font-mono text-teal">{(job.ai_confidence_score * 100).toFixed(0)}%</span>
                                                    </div>
                                                ) : <span className="text-white/20 text-xs">Waiting...</span>}
                                            </td>
                                            <td className="px-8 py-5 text-xs text-white/40 font-mono">
                                                {new Date(job.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                            </td>
                                            <td className="px-8 py-5 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <button onClick={() => handleInspect(job)} className="p-2 hover:bg-white/10 rounded-brand-md text-teal transition-colors cursor-pointer" title="Inspect Reasoning">🧠</button>
                                                    {job.requires_review && (
                                                        <button onClick={() => handleReview(job)} disabled={processingId === job.job_id}
                                                            className="px-3 py-1 bg-copper/20 text-copper border border-copper/30 rounded-brand-sm text-[10px] font-bold hover:bg-copper hover:text-white transition-all cursor-pointer disabled:opacity-50"
                                                        >{processingId === job.job_id ? '⏳...' : 'APPROVE'}</button>
                                                    )}
                                                    {job.status === 'failed' && (
                                                        <button onClick={() => handleRetry(job)} disabled={processingId === job.job_id}
                                                            className="px-3 py-1 bg-warning/10 text-warning border border-warning/20 rounded-brand-sm text-[10px] font-bold hover:bg-warning hover:text-white transition-all cursor-pointer disabled:opacity-50"
                                                        >{processingId === job.job_id ? '⏳...' : 'RETRY'}</button>
                                                    )}
                                                    {job.requires_review && (
                                                        <button onClick={() => handleReject(job)} disabled={processingId === job.job_id}
                                                            className="p-2 hover:bg-danger/20 rounded-brand-md text-danger/50 hover:text-danger transition-colors cursor-pointer disabled:opacity-50" title="Reject"
                                                        >✗</button>
                                                    )}
                                                </div>
                                            </td>
                                        </motion.tr>
                                    ))}
                                </AnimatePresence>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Inspect Modal */}
            <AnimatePresence>
                {selectedJob && (
                    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" onClick={() => setSelectedJob(null)}></div>
                        <motion.div initial={{ opacity: 0, scale: 0.95, y: 20 }} animate={{ opacity: 1, scale: 1, y: 0 }} exit={{ opacity: 0, scale: 0.95, y: 20 }}
                            className="relative bg-[#121214] border border-white/10 rounded-brand-xl shadow-brand-3 w-full max-w-lg overflow-hidden"
                        >
                            <div className="px-8 py-6 border-b border-white/5 flex items-center justify-between">
                                <div>
                                    <h3 className="font-display text-2xl text-white">AI Match Reasoning</h3>
                                    <span className="text-[10px] font-mono text-teal uppercase tracking-widest">{selectedJob.job_id}</span>
                                </div>
                                <button onClick={() => setSelectedJob(null)} className="w-8 h-8 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/10 text-white/50 hover:text-white transition-all text-lg">×</button>
                            </div>
                            <div className="px-8 py-6 space-y-5">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-[10px] font-mono text-white/30 uppercase tracking-widest mb-1">Employer</p>
                                        <p className="text-sm text-white font-medium">{selectedJob.employer.name}</p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-mono text-white/30 uppercase tracking-widest mb-1">Status</p>
                                        <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border ${getStatusColor(selectedJob.status)}`}>{selectedJob.status}</span>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-[10px] font-mono text-white/30 uppercase tracking-widest mb-1">Priority</p>
                                        <p className="text-sm text-white">{selectedJob.priority}/10</p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-mono text-white/30 uppercase tracking-widest mb-1">AI Confidence</p>
                                        <p className="text-sm text-teal font-bold">{selectedJob.ai_confidence_score ? `${(selectedJob.ai_confidence_score * 100).toFixed(1)}%` : 'N/A'}</p>
                                    </div>
                                </div>
                                <div>
                                    <p className="text-[10px] font-mono text-white/30 uppercase tracking-widest mb-1">Submitted</p>
                                    <p className="text-sm text-white/60">{new Date(selectedJob.created_at).toLocaleString()}</p>
                                </div>
                                {selectedJob.completed_at && (
                                    <div>
                                        <p className="text-[10px] font-mono text-white/30 uppercase tracking-widest mb-1">Completed</p>
                                        <p className="text-sm text-success">{new Date(selectedJob.completed_at).toLocaleString()}</p>
                                    </div>
                                )}
                                {selectedJob.requires_review && (
                                    <div className="bg-copper/10 border border-copper/20 rounded-brand-md p-4">
                                        <p className="text-[10px] font-mono text-copper uppercase tracking-widest font-bold">⚠ Requires Human Review</p>
                                        <p className="text-xs text-white/50 mt-1">AI confidence is below threshold or flagged anomalies detected.</p>
                                    </div>
                                )}
                            </div>
                            <div className="px-8 py-5 border-t border-white/5 flex items-center justify-end gap-3">
                                {selectedJob.requires_review && (
                                    <>
                                        <button onClick={() => { handleReview(selectedJob); setSelectedJob(null); }} className="px-4 py-2 bg-teal text-white rounded-brand-md text-xs font-bold transition-all">✓ Approve Match</button>
                                        <button onClick={() => { handleReject(selectedJob); setSelectedJob(null); }} className="px-4 py-2 bg-danger/10 text-danger border border-danger/20 rounded-brand-md text-xs font-bold transition-all">✗ Reject</button>
                                    </>
                                )}
                                {selectedJob.status === 'failed' && (
                                    <button onClick={() => { handleRetry(selectedJob); setSelectedJob(null); }} className="px-4 py-2 bg-warning/10 text-warning border border-warning/20 rounded-brand-md text-xs font-bold transition-all">🔄 Retry</button>
                                )}
                                <button onClick={() => setSelectedJob(null)} className="px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-brand-md text-xs text-white/60 hover:text-white transition-all">Close</button>
                            </div>
                        </motion.div>
                    </motion.div>
                )}
            </AnimatePresence>

            <style dangerouslySetInnerHTML={{ __html: `
                @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500;700&display=swap');
                .font-display { font-family: 'Cormorant Garamond', serif; }
                .font-body { font-family: 'DM Sans', sans-serif; }
                .font-mono { font-family: 'DM Mono', monospace; }
            ` }} />
        </AdminLayout>
    );
}
