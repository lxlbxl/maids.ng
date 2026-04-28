import { Head, Link, useForm } from '@inertiajs/react';
import EmployerLayout from '@/Layouts/EmployerLayout';

export default function BookingDetail({ auth, booking, disputes = [], agentLogs = [] }) {
    const { post, processing } = useForm();

    const handleCancel = () => {
        if (confirm('Are you sure you want to cancel this booking? This may involve AI Referee review if already started.')) {
            post(route('employer.bookings.cancel', booking.id));
        }
    };

    const handleComplete = () => {
        if (confirm('Mark this booking as completed? This will trigger the Treasurer Agent to process final payouts.')) {
            post(route('employer.bookings.complete', booking.id));
        }
    };

    const getStatusColor = (status) => {
        switch (status) {
            case 'active': return 'bg-success/10 text-success';
            case 'completed': return 'bg-teal/10 text-teal';
            case 'cancelled': return 'bg-danger/10 text-danger';
            case 'pending': return 'bg-gray-100 text-muted';
            default: return 'bg-gray-100 text-muted';
        }
    };

    return (
        <EmployerLayout user={auth?.user}>
            <Head title={`Booking #${booking.id} Details`} />

            <div className="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div className="flex items-center gap-3 mb-1">
                        <Link href="/employer/bookings" className="text-muted hover:text-teal transition-colors text-sm">← Back to Bookings</Link>
                    </div>
                    <h1 className="font-display text-3xl font-light text-espresso">Booking Reference #{booking.id}</h1>
                    <div className="flex items-center gap-3 mt-2">
                        <span className={`px-2 py-1 rounded-full text-[11px] font-medium uppercase tracking-[0.05em] ${getStatusColor(booking.status)}`}>
                            {booking.status}
                        </span>
                        <p className="text-muted text-sm italic">Created on {new Date(booking.created_at).toLocaleDateString()}</p>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    {booking.status === 'active' && (
                        <button 
                            onClick={handleComplete}
                            disabled={processing}
                            className="bg-teal text-white px-6 py-2.5 rounded-brand-md text-sm font-medium hover:bg-teal-dark transition-all disabled:opacity-50"
                        >
                            Mark as Completed
                        </button>
                    )}
                    {(booking.status === 'pending' || booking.status === 'accepted' || (booking.status === 'active' && !booking.completed_at)) && (
                        <button 
                            onClick={handleCancel}
                            disabled={processing}
                            className="bg-white text-danger border border-danger/20 px-6 py-2.5 rounded-brand-md text-sm font-medium hover:bg-danger/5 transition-all disabled:opacity-50"
                        >
                            Cancel Booking
                        </button>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Left Column: Details */}
                <div className="lg:col-span-2 space-y-8">
                    {/* Maid Info Card */}
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6">
                        <h2 className="font-display text-xl text-espresso mb-6 border-b border-gray-100 pb-3">Helper Details</h2>
                        <div className="flex items-start gap-6">
                            {booking.maid?.avatar ? (
                                <img src={`/storage/${booking.maid.avatar}`} alt="" className="w-20 h-20 rounded-brand-lg object-cover"/>
                            ) : (
                                <div className="w-20 h-20 bg-teal/5 text-teal rounded-brand-lg flex items-center justify-center text-3xl font-light">
                                    {booking.maid?.name?.charAt(0)}
                                </div>
                            )}
                            <div className="flex-1">
                                <h3 className="text-xl font-semibold text-espresso mb-1">{booking.maid?.name}</h3>
                                <p className="text-muted text-sm mb-4">{booking.maid?.maid_profile?.get_maid_role || 'Helper'} · {booking.maid?.maid_profile?.location || 'Lagos, NG'}</p>
                                
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="bg-gray-50 p-3 rounded-brand-md">
                                        <p className="font-mono text-[9px] uppercase tracking-wider text-muted mb-1">Agreed Salary</p>
                                        <p className="text-espresso font-semibold">₦{booking.agreed_salary?.toLocaleString()}/mo</p>
                                    </div>
                                    <div className="bg-gray-50 p-3 rounded-brand-md">
                                        <p className="font-mono text-[9px] uppercase tracking-wider text-muted mb-1">Start Date</p>
                                        <p className="text-espresso font-semibold">{booking.start_date ? new Date(booking.start_date).toLocaleDateString() : 'TBD'}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Agent Activity / AI Log */}
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6">
                        <div className="flex items-center justify-between mb-6 border-b border-gray-100 pb-3">
                            <h2 className="font-display text-xl text-espresso">Autonomous Agent Updates</h2>
                            <span className="bg-teal/5 text-teal text-[10px] font-mono px-2 py-0.5 rounded-full uppercase tracking-widest">Live Monitoring</span>
                        </div>
                        
                        <div className="space-y-6">
                            {agentLogs.length > 0 ? agentLogs.map((log, i) => (
                                <div key={log.id} className="relative pl-8">
                                    {/* Timeline Line */}
                                    {i !== agentLogs.length - 1 && <div className="absolute left-[11px] top-6 bottom-0 w-[2px] bg-gray-100"></div>}
                                    
                                    {/* Timeline Dot */}
                                    <div className={`absolute left-0 top-1 w-6 h-6 rounded-full flex items-center justify-center border-2 border-white shadow-sm text-[10px] ${
                                        log.agent === 'Treasurer' ? 'bg-success text-white' : 
                                        log.agent === 'Referee' ? 'bg-danger text-white' : 'bg-teal text-white'
                                    }`}>
                                        {log.agent.charAt(0)}
                                    </div>

                                    <div>
                                        <div className="flex items-center gap-2 mb-1">
                                            <p className="font-medium text-espresso text-sm">{log.agent} Agent: <span className="text-muted font-normal">{log.action.replace('_', ' ')}</span></p>
                                            <span className="text-[10px] text-muted">{new Date(log.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                                        </div>
                                        <div className="bg-ivory/50 rounded-brand-md p-3 border border-gray-100">
                                            <p className="text-xs text-espresso leading-relaxed italic">"{log.reasoning}"</p>
                                            <div className="mt-2 flex items-center gap-3">
                                                <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                                                    <div className="h-full bg-teal transition-all duration-1000" style={{width: `${log.confidence_score}%`}}></div>
                                                </div>
                                                <span className="text-[10px] font-mono whitespace-nowrap text-muted">{log.confidence_score}% Confidence</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )) : (
                                <div className="flex flex-col items-center justify-center py-8 text-center bg-gray-50 rounded-brand-lg border border-dashed border-gray-200">
                                    <div className="w-12 h-12 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mb-3 text-xl">🤖</div>
                                    <p className="text-sm text-muted">No active agent interventions currently recorded for this booking.</p>
                                    <p className="text-[10px] text-muted mt-1 uppercase tracking-widest">System Operational</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Right Column: Support & Status */}
                <div className="space-y-8">
                    {/* Status Summary */}
                    <div className="bg-espresso text-white rounded-brand-lg p-6 shadow-brand-2">
                        <h3 className="font-display text-lg mb-4 opacity-90 border-b border-white/10 pb-2">Booking Status</h3>
                        <div className="space-y-4">
                            <div className="flex justify-between items-center text-sm">
                                <span className="opacity-60 font-light">Status:</span>
                                <span className="font-semibold uppercase tracking-wider">{booking.status}</span>
                            </div>
                            <div className="flex justify-between items-center text-sm">
                                <span className="opacity-60 font-light">Verification:</span>
                                <span className="text-success font-medium">Verified by Gatekeeper</span>
                            </div>
                            <div className="flex justify-between items-center text-sm">
                                <span className="opacity-60 font-light">Guarantee:</span>
                                <span className="text-copper font-medium">10-Day Protection</span>
                            </div>
                        </div>
                        <div className="mt-6 pt-4 border-t border-white/10">
                            <button className="w-full bg-white/10 hover:bg-white/20 transition-all text-white text-xs font-medium py-3 rounded-brand-md uppercase tracking-widest">
                                Contact Concierge
                            </button>
                        </div>
                    </div>

                    {/* Disputes Card */}
                    {disputes.length > 0 && (
                        <div className="bg-white rounded-brand-lg border border-danger/20 shadow-brand-1 p-6">
                            <h3 className="font-display text-lg text-danger mb-4 flex items-center gap-2">
                                <span className="text-xl">⚖️</span> Active Disputes
                            </h3>
                            <div className="space-y-4">
                                {disputes.map(dispute => (
                                    <div key={dispute.id} className="p-3 bg-danger/5 rounded-brand-md border border-danger/10">
                                        <p className="text-xs font-medium text-danger uppercase mb-1">{dispute.reason}</p>
                                        <p className="text-xs text-espresso mb-3 leading-relaxed">{dispute.status === 'resolved' ? dispute.resolution : 'Under review by AI Referee.'}</p>
                                        <span className={`text-[10px] px-2 py-0.5 rounded-full uppercase font-bold tracking-tighter ${
                                            dispute.status === 'resolved' ? 'bg-success text-white' : 'bg-danger text-white'
                                        }`}>
                                            {dispute.status}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </EmployerLayout>
    );
}
