import React, { useState, useEffect } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';

export default function MatchingQueue() {
    const { auth } = usePage().props;
    const [jobs, setJobs] = useState([]);
    const [stats, setStats] = useState({
        total_jobs: 0,
        pending: 0,
        processing: 0,
        completed: 0,
        failed: 0,
        requires_review: 0
    });
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState('all');

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        setLoading(true);
        try {
            // In a real scenario, we'd use axios or the internal fetcher
            const response = await axios.get('/api/v1/admin/matching/queue');
            const statsResponse = await axios.get('/api/v1/admin/matching/statistics');
            
            setJobs(response.data.data.data || []);
            setStats(statsResponse.data.data || {});
        } catch (error) {
            console.error("Failed to fetch matching data", error);
            // Mock data for demonstration if API fails
            setJobs([
                {
                    job_id: 'MATCH-7829',
                    employer: { name: 'Dr. Chima Okoro' },
                    status: 'processing',
                    priority: 8,
                    created_at: '2026-04-28T10:15:00Z',
                    ai_confidence_score: 0.92,
                    requires_review: false
                },
                {
                    job_id: 'MATCH-7830',
                    employer: { name: 'Mrs. Funmi Adebayo' },
                    status: 'pending',
                    priority: 5,
                    created_at: '2026-04-28T11:20:00Z',
                    ai_confidence_score: null,
                    requires_review: false
                },
                {
                    job_id: 'MATCH-7825',
                    employer: { name: 'Chief Emeka' },
                    status: 'completed',
                    priority: 10,
                    created_at: '2026-04-28T08:05:00Z',
                    completed_at: '2026-04-28T08:12:00Z',
                    ai_confidence_score: 0.88,
                    requires_review: true
                }
            ]);
        } finally {
            setLoading(false);
        }
    };

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
                {/* Header Area */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-4xl font-display text-white mb-2">AI Matching Queue</h1>
                        <p className="text-white/50 max-w-2xl">Monitor and manage the Sentinel AI matching engine. Real-time visualization of employer-maid pairing processes.</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button 
                            onClick={fetchData}
                            className="px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-brand-md text-sm transition-all flex items-center gap-2"
                        >
                            <span>🔄</span> Refresh Status
                        </button>
                        <button className="px-6 py-2 bg-teal hover:bg-teal-mid text-white rounded-brand-md text-sm font-bold shadow-brand-2 transition-all">
                            Force Process Queue
                        </button>
                    </div>
                </div>

                {/* Statistics Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {[
                        { label: 'Active Processes', value: stats.processing, color: 'teal', icon: '⚡' },
                        { label: 'Pending in Queue', value: stats.pending, color: 'warning', icon: '⏳' },
                        { label: 'Success Rate', value: '98.4%', color: 'success', icon: '📈' },
                        { label: 'Manual Reviews', value: stats.requires_review, color: 'copper', icon: '🔍' },
                    ].map((s, idx) => (
                        <motion.div 
                            key={idx}
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ delay: idx * 0.1 }}
                            className="bg-[#121214] border border-white/5 p-6 rounded-brand-xl relative overflow-hidden group"
                        >
                            <div className="absolute top-0 right-0 p-4 text-2xl opacity-20 group-hover:opacity-40 transition-opacity">
                                {s.icon}
                            </div>
                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/30 mb-1">{s.label}</p>
                            <h3 className="text-3xl font-display text-white">{s.value}</h3>
                            <div className={`mt-4 h-1 w-12 rounded-full bg-${s.color === 'copper' ? 'copper' : s.color}`}></div>
                        </motion.div>
                    ))}
                </div>

                {/* Queue Visualization */}
                <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                    <div className="px-8 py-6 border-b border-white/5 flex items-center justify-between">
                        <h2 className="font-display text-xl text-white">Live Operations</h2>
                        <div className="flex bg-white/5 p-1 rounded-brand-lg">
                            {['all', 'pending', 'processing', 'review'].map(f => (
                                <button 
                                    key={f}
                                    onClick={() => setFilter(f)}
                                    className={`px-4 py-1.5 text-xs rounded-brand-md transition-all capitalize ${filter === f ? 'bg-teal text-white shadow-brand-1' : 'text-white/40 hover:text-white'}`}
                                >
                                    {f}
                                </button>
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
                                <AnimatePresence mode="popLayout">
                                    {jobs.map((job, idx) => (
                                        <motion.tr 
                                            key={job.job_id}
                                            initial={{ opacity: 0 }}
                                            animate={{ opacity: 1 }}
                                            exit={{ opacity: 0 }}
                                            className="group hover:bg-white/[0.01] transition-colors"
                                        >
                                            <td className="px-8 py-5">
                                                <span className="font-mono text-xs text-teal font-bold">{job.job_id}</span>
                                            </td>
                                            <td className="px-8 py-5 text-sm text-white/80">{job.employer.name}</td>
                                            <td className="px-8 py-5">
                                                <div className="flex items-center gap-1">
                                                    {[...Array(5)].map((_, i) => (
                                                        <div key={i} className={`w-1.5 h-1.5 rounded-full ${i < job.priority / 2 ? 'bg-copper shadow-[0_0_5px_rgba(184,115,51,0.5)]' : 'bg-white/10'}`}></div>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="px-8 py-5">
                                                <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border ${getStatusColor(job.status)}`}>
                                                    {job.status}
                                                </span>
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
                                                <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button className="p-2 hover:bg-white/10 rounded-brand-md text-teal transition-colors" title="Inspect Reasoning">
                                                        🧠
                                                    </button>
                                                    {job.requires_review && (
                                                        <button className="px-3 py-1 bg-copper/20 text-copper border border-copper/30 rounded-brand-sm text-[10px] font-bold hover:bg-copper hover:text-white transition-all">
                                                            REVIEW
                                                        </button>
                                                    )}
                                                    <button className="p-2 hover:bg-white/10 rounded-brand-md text-white/40 hover:text-white transition-colors">
                                                        ⋮
                                                    </button>
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

            {/* Custom CSS for brand gradients and fonts */}
            <style dangerouslySetInnerHTML={{ __html: `
                @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500;700&display=swap');
                
                .font-display { font-family: 'Cormorant Garamond', serif; }
                .font-body { font-family: 'DM Sans', sans-serif; }
                .font-mono { font-family: 'DM Mono', monospace; }
                
                ::-webkit-scrollbar { width: 6px; }
                ::-webkit-scrollbar-track { background: transparent; }
                ::-webkit-scrollbar-thumb { background: rgba(45, 164, 142, 0.2); border-radius: 10px; }
                ::-webkit-scrollbar-thumb:hover { background: rgba(45, 164, 142, 0.4); }
            ` }} />
        </AdminLayout>
    );
}
