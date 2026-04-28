import { Head, Link, useForm } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';

export default function BookingDetail({ auth, booking, disputes = [], agentLogs = [] }) {
    const { post, processing } = useForm();

    const handleAction = (route) => {
        post(route);
    };

    const refereeLogs = agentLogs.filter(log => log.agent === 'Referee');
    const treasurerLogs = agentLogs.filter(log => log.agent === 'Treasurer');

    return (
        <MaidLayout user={auth?.user}>
            <Head title={`Job #${booking.id} Details | Helper`} />
            
            <div className="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <Link href="/maid/bookings" className="text-xs font-mono uppercase tracking-widest text-muted hover:text-teal mb-2 inline-block">← Back to Assignments</Link>
                    <h1 className="font-display text-3xl font-light text-espresso">Job #{booking.id}</h1>
                </div>
                <div className="flex gap-3">
                    {booking.status === 'pending' && (
                        <>
                            <button onClick={() => handleAction(route('maid.bookings.accept', booking.id))} disabled={processing} className="px-6 py-2.5 bg-success text-white rounded-brand-md text-sm font-semibold shadow-brand-1 hover:brightness-110 transition-all">Accept Job</button>
                            <button onClick={() => handleAction(route('maid.bookings.reject', booking.id))} disabled={processing} className="px-6 py-2.5 bg-danger text-white rounded-brand-md text-sm font-semibold shadow-brand-1 hover:brightness-110 transition-all">Decline</button>
                        </>
                    )}
                    {booking.status === 'accepted' && (
                        <button onClick={() => handleAction(route('maid.bookings.start', booking.id))} disabled={processing} className="px-6 py-2.5 bg-teal text-white rounded-brand-md text-sm font-semibold shadow-brand-1 hover:bg-teal-dark transition-all">Start Working</button>
                    )}
                    {booking.status === 'active' && (
                        <button onClick={() => handleAction(route('maid.bookings.complete', booking.id))} disabled={processing} className="px-6 py-2.5 bg-espresso text-white rounded-brand-md text-sm font-semibold shadow-brand-1 hover:brightness-110 transition-all">Mark as Completed</button>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Main Job Info */}
                <div className="lg:col-span-2 space-y-8">
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-8">
                        <div className="flex items-center justify-between mb-8">
                            <h2 className="font-display text-2xl text-espresso">Employer Information</h2>
                            <span className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest ${booking.status === 'active' ? 'bg-success/10 text-success' : 'bg-gray-100 text-muted'}`}>
                                Status: {booking.status}
                            </span>
                        </div>

                        <div className="grid md:grid-cols-2 gap-8">
                            <div>
                                <p className="text-[10px] font-mono uppercase tracking-[0.2em] text-muted mb-2">Employer Name</p>
                                <p className="text-lg font-medium text-espresso">{booking.employer?.name}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-mono uppercase tracking-[0.2em] text-muted mb-2">Location</p>
                                <p className="text-lg font-medium text-espresso">{booking.employer?.employer_profile?.location || 'Lagos, Nigeria'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-mono uppercase tracking-[0.2em] text-muted mb-2">Start Date</p>
                                <p className="text-lg font-medium text-espresso">{booking.start_date || 'TBD'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-mono uppercase tracking-[0.2em] text-muted mb-2">Proposed Salary</p>
                                <p className="text-lg font-bold text-teal">₦{booking.agreed_salary?.toLocaleString() || 'Negotiable'}</p>
                            </div>
                        </div>

                        <div className="mt-10 pt-8 border-t border-gray-100">
                            <p className="text-[10px] font-mono uppercase tracking-[0.2em] text-muted mb-2">Special Instructions</p>
                            <p className="text-sm text-espresso leading-relaxed italic">
                                "{booking.notes || 'No specific instructions provided.'}"
                            </p>
                        </div>
                    </div>

                    {/* Agent Activity Logs for Maid Transparency */}
                    <div className="bg-ivory/50 rounded-brand-lg border border-gray-200 p-8">
                        <h2 className="font-display text-xl text-espresso mb-6 flex items-center gap-3">
                            <span>🕵️</span> AI System Activity
                        </h2>
                        
                        <div className="space-y-6">
                            {agentLogs.length > 0 ? agentLogs.map((log) => (
                                <div key={log.id} className="flex gap-4">
                                    <div className="flex-shrink-0 w-10 h-10 rounded-full bg-white border border-gray-100 flex items-center justify-center text-lg shadow-sm">
                                        {log.agent === 'Referee' ? '⚖️' : log.agent === 'Treasurer' ? '💰' : '🤖'}
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-center justify-between mb-1">
                                            <p className="text-sm font-bold text-espresso">{log.agent} Agent <span className="text-muted font-normal">({log.action})</span></p>
                                            <span className="text-[10px] font-mono text-muted">{new Date(log.created_at).toLocaleTimeString()}</span>
                                        </div>
                                        <div className="bg-white p-4 rounded-brand-md border border-gray-100 shadow-brand-1">
                                            <p className="text-xs text-muted mb-3 italic">"{log.reasoning}"</p>
                                            <div className="flex items-center gap-3">
                                                <div className="flex-1 h-1 bg-gray-100 rounded-full overflow-hidden">
                                                    <div 
                                                        className={`h-full transition-all ${log.decision === 'approved' || log.decision === 'upheld' ? 'bg-success' : 'bg-copper'}`}
                                                        style={{ width: `${log.confidence_score}%` }}
                                                    ></div>
                                                </div>
                                                <span className="text-[10px] font-mono text-muted uppercase">{log.confidence_score}% Confidence</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )) : (
                                <p className="text-sm text-muted text-center py-4">No active monitoring logs for this job yet.</p>
                            )}
                        </div>
                    </div>
                </div>

                {/* Sidebar - Support & Disputes */}
                <div className="space-y-6">
                    <div className="bg-espresso text-white rounded-brand-lg p-6 shadow-brand-2">
                        <h3 className="font-display text-lg mb-4">Support & Disputes</h3>
                        <p className="text-xs text-white/60 leading-relaxed mb-6">
                            Need help with this assignment? Our Referee Agent is available to resolve disagreements fairly based on contract terms.
                        </p>
                        <button className="w-full py-2.5 bg-white/10 border border-white/20 rounded-brand-md text-xs font-mono uppercase tracking-widest hover:bg-white/20 transition-all">
                            Open Dispute Alert
                        </button>
                    </div>

                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6">
                        <h3 className="font-mono text-[10px] uppercase tracking-widest text-muted mb-4">Treasurer Protocol</h3>
                        <ul className="space-y-4">
                            <li className="flex items-start gap-3">
                                <span className="text-teal">▸</span>
                                <p className="text-xs text-espresso leading-relaxed">Payments are held in escrow by the Treasurer upon booking start.</p>
                            </li>
                            <li className="flex items-start gap-3">
                                <span className="text-teal">▸</span>
                                <p className="text-xs text-espresso leading-relaxed">Funds are auto-released to your wallet upon successful completion of service.</p>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </MaidLayout>
    );
}
