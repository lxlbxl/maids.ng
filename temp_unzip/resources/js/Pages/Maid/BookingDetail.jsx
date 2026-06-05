import { Head, Link, useForm } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';

export default function BookingDetail({ auth, booking, disputes = [], agentLogs = [] }) {
    const { post, processing } = useForm();

    const handleAction = (route) => {
        post(route);
    };

    const statusLabel = (status) => {
        switch (status) {
            case 'pending': return { text: '⏳ Waiting for Your Reply', cls: 'bg-yellow-100 text-yellow-700' };
            case 'accepted': return { text: '✅ Accepted', cls: 'bg-green-100 text-green-700' };
            case 'active': return { text: '🟢 Job Active', cls: 'bg-success/10 text-success' };
            case 'completed': return { text: '✔️ Finished', cls: 'bg-gray-100 text-gray-600' };
            case 'cancelled': return { text: '❌ Cancelled', cls: 'bg-danger/10 text-danger' };
            default: return { text: status, cls: 'bg-gray-100 text-muted' };
        }
    };

    const { text: statusText, cls: statusCls } = statusLabel(booking.status);

    return (
        <MaidLayout user={auth?.user}>
            <Head title={`Job #${booking.id} | Maids.ng`} />
            
            <div className="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <Link href="/maid/bookings" className="text-xs font-mono uppercase tracking-widest text-muted hover:text-teal mb-2 inline-block">← Back to My Jobs</Link>
                    <h1 className="font-display text-3xl font-light text-espresso">Job Details</h1>
                    <p className="text-muted text-sm mt-1">Job Reference: #{booking.id}</p>
                </div>
                <div className="flex gap-3 flex-wrap">
                    {booking.status === 'pending' && (
                        <>
                            <button onClick={() => handleAction(route('maid.bookings.accept', booking.id))} disabled={processing} className="px-6 py-2.5 bg-success text-white rounded-brand-md text-sm font-semibold shadow-brand-1 hover:brightness-110 transition-all">
                                ✅ Accept This Job
                            </button>
                            <button onClick={() => handleAction(route('maid.bookings.reject', booking.id))} disabled={processing} className="px-6 py-2.5 bg-danger text-white rounded-brand-md text-sm font-semibold shadow-brand-1 hover:brightness-110 transition-all">
                                ❌ Turn Down
                            </button>
                        </>
                    )}
                    {booking.status === 'accepted' && (
                        <button onClick={() => handleAction(route('maid.bookings.start', booking.id))} disabled={processing} className="px-6 py-2.5 bg-teal text-white rounded-brand-md text-sm font-semibold shadow-brand-1 hover:bg-teal-dark transition-all">
                            🚀 Start Working
                        </button>
                    )}
                    {booking.status === 'active' && (
                        <button onClick={() => handleAction(route('maid.bookings.complete', booking.id))} disabled={processing} className="px-6 py-2.5 bg-espresso text-white rounded-brand-md text-sm font-semibold shadow-brand-1 hover:brightness-110 transition-all">
                            ✔️ Mark Job as Done
                        </button>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Main Job Info */}
                <div className="lg:col-span-2 space-y-8">
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-8">
                        <div className="flex items-center justify-between mb-8 flex-wrap gap-3">
                            <h2 className="font-display text-2xl text-espresso">Employer Details</h2>
                            <span className={`px-3 py-1 rounded-full text-[11px] font-bold ${statusCls}`}>
                                {statusText}
                            </span>
                        </div>

                        <div className="grid md:grid-cols-2 gap-8">
                            <div>
                                <p className="text-[10px] font-mono uppercase tracking-[0.2em] text-muted mb-2">Who You Work For</p>
                                <p className="text-lg font-medium text-espresso">{booking.employer?.name || '—'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-mono uppercase tracking-[0.2em] text-muted mb-2">Job Location</p>
                                <p className="text-lg font-medium text-espresso">{booking.employer?.employer_profile?.location || 'Lagos, Nigeria'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-mono uppercase tracking-[0.2em] text-muted mb-2">When Do You Start?</p>
                                <p className="text-lg font-medium text-espresso">{booking.start_date ? new Date(booking.start_date).toLocaleDateString('en-NG', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : 'To be confirmed'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-mono uppercase tracking-[0.2em] text-muted mb-2">Your Monthly Pay</p>
                                <p className="text-lg font-bold text-teal">₦{booking.agreed_salary?.toLocaleString() || 'To be agreed'}</p>
                            </div>
                        </div>

                        {booking.notes && (
                            <div className="mt-10 pt-8 border-t border-gray-100">
                                <p className="text-[10px] font-mono uppercase tracking-[0.2em] text-muted mb-2">Special Instructions from Employer</p>
                                <p className="text-sm text-espresso leading-relaxed italic bg-gray-50 p-4 rounded-brand-md border border-gray-100">
                                    "{booking.notes}"
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Activity Log (simplified) */}
                    {agentLogs.length > 0 && (
                        <div className="bg-ivory/50 rounded-brand-lg border border-gray-200 p-8">
                            <h2 className="font-display text-xl text-espresso mb-6">📋 What Has Happened on This Job</h2>
                            
                            <div className="space-y-6">
                                {agentLogs.map((log) => (
                                    <div key={log.id} className="flex gap-4">
                                        <div className="flex-shrink-0 w-10 h-10 rounded-full bg-white border border-gray-100 flex items-center justify-center text-lg shadow-sm">
                                            {log.agent === 'Referee' ? '⚖️' : log.agent === 'Treasurer' ? '💰' : '📌'}
                                        </div>
                                        <div className="flex-1">
                                            <div className="flex items-center justify-between mb-1 flex-wrap gap-2">
                                                <p className="text-sm font-bold text-espresso">{log.action}</p>
                                                <span className="text-[10px] font-mono text-muted">{new Date(log.created_at).toLocaleDateString('en-NG', { day: 'numeric', month: 'short' })}</span>
                                            </div>
                                            <div className="bg-white p-4 rounded-brand-md border border-gray-100 shadow-brand-1">
                                                <p className="text-xs text-muted leading-relaxed">{log.reasoning}</p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Help & Problem Reporting */}
                    <div className="bg-espresso text-white rounded-brand-lg p-6 shadow-brand-2">
                        <h3 className="font-display text-lg mb-3">Need Help? 🆘</h3>
                        <p className="text-xs text-white/60 leading-relaxed mb-6">
                            If there is a problem with this job — like you are not being paid, or you feel unsafe — you can report it here. We will help you sort it out.
                        </p>
                        <button className="w-full py-2.5 bg-white/10 border border-white/20 rounded-brand-md text-xs font-semibold hover:bg-white/20 transition-all">
                            Report a Problem
                        </button>
                    </div>

                    {/* How Pay Works */}
                    <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6">
                        <h3 className="font-mono text-[10px] uppercase tracking-widest text-muted mb-4">💰 How Your Pay Works</h3>
                        <ul className="space-y-4">
                            <li className="flex items-start gap-3">
                                <span className="text-teal">▸</span>
                                <p className="text-xs text-espresso leading-relaxed">When the job starts, the employer's money is kept safely by Maids.ng.</p>
                            </li>
                            <li className="flex items-start gap-3">
                                <span className="text-teal">▸</span>
                                <p className="text-xs text-espresso leading-relaxed">After the job is marked as done, your pay is sent straight to your wallet.</p>
                            </li>
                            <li className="flex items-start gap-3">
                                <span className="text-teal">▸</span>
                                <p className="text-xs text-espresso leading-relaxed">You can then withdraw your money to your bank account from the Wallet page.</p>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </MaidLayout>
    );
}
