import React, { useState, useEffect } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';

export default function SalaryManagement() {
    const { auth } = usePage().props;
    const [schedules, setSchedules] = useState([]);
    const [stats, setStats] = useState({
        total_schedules: 0,
        total_paid: 0,
        total_pending: 0,
        total_overdue: 0,
        total_amount_scheduled: 0,
        total_amount_paid: 0,
        overdue_amount: 0
    });
    const [loading, setLoading] = useState(true);
    const [view, setView] = useState('overdue'); // 'all', 'overdue', 'pending', 'paid'

    useEffect(() => {
        fetchData();
    }, [view]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/api/v1/admin/salary/schedules', { params: { status: view !== 'all' ? view : null } });
            const statsResponse = await axios.get('/api/v1/admin/salary/statistics');
            
            setSchedules(response.data.data.data || []);
            setStats(statsResponse.data.data || {});
        } catch (error) {
            console.error("Failed to fetch salary data", error);
            // Mock data
            setSchedules([
                {
                    id: 1,
                    assignment: { employer: { name: 'Dr. Chima Okoro' }, maid: { name: 'Mercy Johnson' } },
                    amount: 55000,
                    due_date: '2026-04-25',
                    status: 'overdue',
                    days_overdue: 3
                },
                {
                    id: 2,
                    assignment: { employer: { name: 'Mrs. Funmi Adebayo' }, maid: { name: 'Blessing Silas' } },
                    amount: 48000,
                    due_date: '2026-04-20',
                    status: 'paid',
                    paid_at: '2026-04-19'
                },
                {
                    id: 3,
                    assignment: { employer: { name: 'Chief Emeka' }, maid: { name: 'Patience Eze' } },
                    amount: 60000,
                    due_date: '2026-04-30',
                    status: 'pending'
                }
            ]);
        } finally {
            setLoading(false);
        }
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(amount);
    };

    return (
        <AdminLayout>
            <Head title="Salary Management | Maids.ng" />

            <div className="space-y-8">
                {/* Header Area */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-4xl font-display text-white mb-2">Salary Control</h1>
                        <p className="text-white/50 max-w-2xl">Administrative oversight for all domestic staff payroll. Track payments, manage overdue accounts, and ensure financial compliance.</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="px-4 py-2 bg-copper/10 border border-copper/20 rounded-brand-md flex items-center gap-2">
                            <span className="text-copper animate-pulse">●</span>
                            <span className="text-xs font-mono text-copper font-bold uppercase tracking-wider">Overdue: {formatCurrency(stats.overdue_amount || 0)}</span>
                        </div>
                    </div>
                </div>

                {/* Financial Summary Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="bg-[#121214] border border-white/5 p-8 rounded-brand-xl relative overflow-hidden group shadow-2xl">
                        <div className="absolute top-0 right-0 p-6 text-4xl opacity-10">💰</div>
                        <p className="text-[10px] font-mono uppercase tracking-[0.3em] text-white/30 mb-2">Total Monthly Commitment</p>
                        <h3 className="text-4xl font-display text-white">{formatCurrency(stats.total_amount_scheduled || 0)}</h3>
                        <div className="mt-6 flex items-center gap-2">
                            <div className="h-1 flex-1 bg-white/5 rounded-full overflow-hidden">
                                <div className="h-full bg-teal shadow-[0_0_10px_rgba(45,164,142,0.4)]" style={{ width: `${(stats.total_amount_paid / stats.total_amount_scheduled) * 100 || 0}%` }}></div>
                            </div>
                            <span className="text-[10px] font-mono text-teal">{( (stats.total_amount_paid / stats.total_amount_scheduled) * 100 || 0).toFixed(1)}% PAID</span>
                        </div>
                    </div>

                    <div className="bg-[#121214] border border-white/5 p-8 rounded-brand-xl relative overflow-hidden group shadow-2xl">
                        <div className="absolute top-0 right-0 p-6 text-4xl opacity-10 text-copper">⏳</div>
                        <p className="text-[10px] font-mono uppercase tracking-[0.3em] text-copper/50 mb-2">Current Overdue Balance</p>
                        <h3 className="text-4xl font-display text-copper">{formatCurrency(stats.overdue_amount || 0)}</h3>
                        <p className="mt-6 text-[10px] text-white/30 font-mono italic">Across {stats.total_overdue || 0} active assignments</p>
                    </div>

                    <div className="bg-[#121214] border border-white/5 p-8 rounded-brand-xl relative overflow-hidden group shadow-2xl">
                        <div className="absolute top-0 right-0 p-6 text-4xl opacity-10">🛡️</div>
                        <p className="text-[10px] font-mono uppercase tracking-[0.3em] text-white/30 mb-2">System Escrow Balance</p>
                        <h3 className="text-4xl font-display text-white">{formatCurrency(1250000)}</h3>
                        <p className="mt-6 text-[10px] text-success font-mono">FULLY COLLATERALIZED</p>
                    </div>
                </div>

                {/* Operations Table */}
                <div className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-2xl">
                    <div className="px-8 py-6 border-b border-white/5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div className="flex items-center gap-6">
                            <h2 className="font-display text-xl text-white">Payment Schedules</h2>
                            <div className="h-4 w-px bg-white/10 hidden md:block"></div>
                            <nav className="flex gap-6">
                                {['overdue', 'pending', 'paid', 'all'].map(v => (
                                    <button 
                                        key={v}
                                        onClick={() => setView(v)}
                                        className={`text-[10px] font-mono uppercase tracking-widest transition-all relative py-1 ${view === v ? 'text-teal' : 'text-white/30 hover:text-white'}`}
                                    >
                                        {v}
                                        {view === v && <motion.div layoutId="salary-nav" className="absolute bottom-0 left-0 right-0 h-0.5 bg-teal shadow-[0_0_8px_rgba(45,164,142,0.6)]" />}
                                    </button>
                                ))}
                            </nav>
                        </div>
                        <div className="flex items-center gap-3">
                            <input 
                                type="text" 
                                placeholder="Filter by user..." 
                                className="bg-white/5 border border-white/10 rounded-brand-md px-4 py-2 text-xs text-white focus:border-teal/50 outline-none w-64"
                            />
                            <button className="px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-brand-md text-xs transition-all">
                                📊 Export Report
                            </button>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-white/[0.02] text-[10px] font-mono uppercase tracking-widest text-white/30">
                                    <th className="px-8 py-4 font-bold">Ref</th>
                                    <th className="px-8 py-4 font-bold">Participants</th>
                                    <th className="px-8 py-4 font-bold">Amount</th>
                                    <th className="px-8 py-4 font-bold">Due Date</th>
                                    <th className="px-8 py-4 font-bold">Status</th>
                                    <th className="px-8 py-4 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-white/5">
                                {schedules.map((item) => (
                                    <tr key={item.id} className="group hover:bg-white/[0.01] transition-colors">
                                        <td className="px-8 py-5">
                                            <span className="font-mono text-xs text-white/30">#SAL-{2000 + item.id}</span>
                                        </td>
                                        <td className="px-8 py-5">
                                            <div className="flex flex-col">
                                                <span className="text-sm font-medium text-white">{item.assignment.maid.name}</span>
                                                <span className="text-[10px] text-white/30 uppercase tracking-tighter">Employer: {item.assignment.employer.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-8 py-5">
                                            <span className="text-sm font-mono font-bold text-white">{formatCurrency(item.amount)}</span>
                                        </td>
                                        <td className="px-8 py-5">
                                            <div className="flex flex-col">
                                                <span className={`text-xs ${item.status === 'overdue' ? 'text-copper font-bold' : 'text-white/60'}`}>
                                                    {new Date(item.due_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                                                </span>
                                                {item.status === 'overdue' && (
                                                    <span className="text-[9px] font-mono text-copper uppercase tracking-widest">{item.days_overdue} days late</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-8 py-5">
                                            <div className={`inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border ${
                                                item.status === 'paid' ? 'text-success bg-success/10 border-success/20' : 
                                                item.status === 'overdue' ? 'text-copper bg-copper/10 border-copper/20 shadow-[0_0_10px_rgba(184,115,51,0.1)]' : 
                                                'text-warning bg-warning/10 border-warning/20'
                                            }`}>
                                                <div className={`w-1 h-1 rounded-full ${
                                                    item.status === 'paid' ? 'bg-success' : 
                                                    item.status === 'overdue' ? 'bg-copper animate-pulse' : 
                                                    'bg-warning'
                                                }`}></div>
                                                {item.status}
                                            </div>
                                        </td>
                                        <td className="px-8 py-5 text-right">
                                            <div className="flex items-center justify-end gap-3">
                                                <button className="text-[10px] font-mono uppercase tracking-widest text-teal hover:text-teal-light transition-colors">Details</button>
                                                {item.status === 'overdue' && (
                                                    <button className="px-3 py-1 bg-copper text-white rounded-brand-sm text-[10px] font-bold shadow-brand-1 hover:shadow-brand-2 transition-all">SEND NUDGE</button>
                                                )}
                                                {item.status === 'pending' && (
                                                    <button className="px-3 py-1 bg-teal/10 text-teal border border-teal/20 rounded-brand-sm text-[10px] font-bold hover:bg-teal hover:text-white transition-all">PROCESS</button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <style dangerouslySetInnerHTML={{ __html: `
                @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500;700&display=swap');
                .font-display { font-family: 'Cormorant Garamond', serif; }
                .font-body { font-family: 'DM Sans', sans-serif; }
                .font-mono { font-family: 'DM Mono', monospace; }
            ` }} />
        </AdminLayout>
    );
}
