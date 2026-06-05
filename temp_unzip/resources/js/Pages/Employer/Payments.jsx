import { Head } from '@inertiajs/react';
import EmployerLayout from '@/Layouts/EmployerLayout';

export default function Payments({ auth, matchingFees = { data: [] }, payouts = [] }) {
    return (
        <EmployerLayout user={auth?.user}>
            <Head title="Payment History | Employer" />
            
            <div className="mb-8">
                <h1 className="font-display text-3xl font-light text-espresso">Payment History</h1>
                <p className="text-muted mt-2">Manage your matching fees and salary payout records.</p>
            </div>

            <div className="grid grid-cols-1 gap-8">
                {/* Matching Fees Section */}
                <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 className="font-display text-lg text-espresso">Matching Fee History</h2>
                        <span className="bg-teal/5 text-teal text-[10px] font-mono px-2 py-0.5 rounded-full uppercase tracking-widest">Treasurer Supervised</span>
                    </div>
                    
                    {matchingFees.data.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-50 font-mono text-[10px] tracking-[0.1em] uppercase text-muted">
                                    <tr>
                                        <th className="px-6 py-4 font-medium">Reference</th>
                                        <th className="px-6 py-4 font-medium">Amount</th>
                                        <th className="px-6 py-4 font-medium">Date</th>
                                        <th className="px-6 py-4 font-medium">Status</th>
                                        <th className="px-6 py-4 font-medium">Gateway</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {matchingFees.data.map(fee => (
                                        <tr key={fee.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-6 py-4 font-mono text-xs">{fee.reference}</td>
                                            <td className="px-6 py-4 font-semibold text-espresso">₦{fee.amount?.toLocaleString()}</td>
                                            <td className="px-6 py-4 text-muted">{new Date(fee.created_at).toLocaleDateString()}</td>
                                            <td className="px-6 py-4">
                                                <span className={`px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-tighter ${
                                                    fee.status === 'success' || fee.status === 'paid' ? 'bg-success text-white' : 'bg-gray-200 text-muted'
                                                }`}>
                                                    {fee.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-muted capitalize">{fee.gateway}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="p-12 text-center">
                            <p className="text-muted text-sm">No matching fee records found.</p>
                        </div>
                    )}
                </div>

                {/* Salary Payouts Note */}
                <div className="bg-ivory rounded-brand-lg border border-teal/10 p-8 flex items-center gap-6">
                    <div className="w-16 h-16 bg-teal/10 text-teal rounded-full flex items-center justify-center text-3xl">🏦</div>
                    <div>
                        <h3 className="font-display text-xl text-espresso mb-2">Automated Payouts</h3>
                        <p className="text-muted text-sm max-w-xl">
                            Our Treasurer Agent automatically calculates and initiates maid salary payouts upon booking completion. 
                            You will see these records appear here once your first booking is finalized.
                        </p>
                    </div>
                </div>
            </div>
        </EmployerLayout>
    );
}
