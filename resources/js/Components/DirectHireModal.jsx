import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

export default function DirectHireModal({ maid, onClose }) {
    const { auth } = usePage().props;
    const isEmployer = !!auth?.user && (auth.user.roles?.includes('employer') || auth.user.roles?.includes('admin'));

    const [loading, setLoading] = useState(false);
    const [step, setStep] = useState(isEmployer ? 2 : 1);
    
    // Form data
    const [formData, setFormData] = useState({
        contact_name: auth?.user?.name || '',
        contact_email: auth?.user?.email || '',
        contact_phone: auth?.user?.phone || '',
        location: maid.location || '',
        schedule: 'full-time',
        maid_id: maid.id,
    });

    const [errors, setErrors] = useState({});

    const handleNext = () => {
        const newErrors = {};
        if (!formData.contact_name) newErrors.contact_name = 'Name is required';
        if (!formData.contact_email) newErrors.contact_email = 'Email is required';
        if (!formData.location) newErrors.location = 'Location is required';
        
        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }
        
        setErrors({});
        setStep(2);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            const response = await fetch('/onboarding/direct-hire', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (!response.ok) {
                if (response.status === 422) {
                    setErrors(data.errors || {});
                } else {
                    setErrors({ general: data.message || 'An error occurred. Please try again.' });
                }
                setLoading(false);
                return;
            }

            // Success! Redirect to payment
            window.location.href = data.redirect;
        } catch (error) {
            setErrors({ general: 'Network error. Please try again.' });
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-espresso/40 backdrop-blur-sm">
            <div className="bg-white rounded-brand-xl shadow-brand-3 w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div className="bg-espresso text-white px-6 py-4 flex items-center justify-between">
                    <h3 className="font-display text-xl font-light">Hire {maid.name?.split(' ')[0]}</h3>
                    <button onClick={onClose} className="text-white/60 hover:text-white transition-colors text-xl">×</button>
                </div>

                <div className="p-6">
                    {/* Maid Summary */}
                    <div className="flex items-center gap-4 p-3 bg-gray-50 rounded-brand-lg border border-gray-100 mb-6">
                        <div className="w-12 h-12 bg-teal text-white rounded-full flex items-center justify-center font-bold text-lg">
                            {maid.name?.charAt(0)}
                        </div>
                        <div>
                            <p className="font-bold text-espresso">{maid.name}</p>
                            <p className="text-xs text-muted font-mono uppercase tracking-widest">{maid.role || 'Domestic Helper'}</p>
                        </div>
                        <div className="ml-auto text-right">
                            <p className="text-sm font-bold text-espresso">₦{Number(maid.rate).toLocaleString()}</p>
                            <p className="text-[10px] text-muted">per month</p>
                        </div>
                    </div>

                    {errors.general && (
                        <div className="bg-red-50 text-red-500 p-3 rounded-brand-md text-sm mb-4 border border-red-100">
                            {errors.general}
                        </div>
                    )}

                    {step === 1 ? (
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-espresso mb-1">Your Name</label>
                                <input 
                                    type="text" 
                                    value={formData.contact_name}
                                    onChange={e => setFormData({...formData, contact_name: e.target.value})}
                                    className={`w-full bg-gray-50 border ${errors.contact_name ? 'border-red-300' : 'border-gray-200'} rounded-brand-md px-4 py-2.5 text-sm focus:ring-2 focus:ring-teal/20`}
                                    placeholder="Jane Doe"
                                />
                                {errors.contact_name && <p className="text-xs text-red-500 mt-1">{errors.contact_name}</p>}
                            </div>
                            
                            <div>
                                <label className="block text-sm font-medium text-espresso mb-1">Email Address</label>
                                <input 
                                    type="email" 
                                    value={formData.contact_email}
                                    onChange={e => setFormData({...formData, contact_email: e.target.value})}
                                    className={`w-full bg-gray-50 border ${errors.contact_email ? 'border-red-300' : 'border-gray-200'} rounded-brand-md px-4 py-2.5 text-sm focus:ring-2 focus:ring-teal/20`}
                                    placeholder="jane@example.com"
                                />
                                {errors.contact_email && <p className="text-xs text-red-500 mt-1">{errors.contact_email}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-espresso mb-1">Phone Number (Optional)</label>
                                <input 
                                    type="text" 
                                    value={formData.contact_phone}
                                    onChange={e => setFormData({...formData, contact_phone: e.target.value})}
                                    className="w-full bg-gray-50 border border-gray-200 rounded-brand-md px-4 py-2.5 text-sm focus:ring-2 focus:ring-teal/20"
                                    placeholder="08012345678"
                                />
                            </div>

                            <div className="pt-2">
                                <button 
                                    onClick={handleNext}
                                    className="w-full bg-teal text-white py-3 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-colors"
                                >
                                    Continue →
                                </button>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-espresso mb-1">Job Location</label>
                                <input 
                                    type="text" 
                                    value={formData.location}
                                    onChange={e => setFormData({...formData, location: e.target.value})}
                                    className={`w-full bg-gray-50 border ${errors.location ? 'border-red-300' : 'border-gray-200'} rounded-brand-md px-4 py-2.5 text-sm focus:ring-2 focus:ring-teal/20`}
                                    placeholder="e.g. Lekki, Lagos"
                                />
                                {errors.location && <p className="text-xs text-red-500 mt-1">{errors.location}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-espresso mb-1">Required Schedule</label>
                                <select
                                    value={formData.schedule}
                                    onChange={e => setFormData({...formData, schedule: e.target.value})}
                                    className="w-full bg-gray-50 border border-gray-200 rounded-brand-md px-4 py-2.5 text-sm focus:ring-2 focus:ring-teal/20"
                                >
                                    <option value="full-time">Full-Time (Mon-Fri)</option>
                                    <option value="part-time">Part-Time</option>
                                    <option value="live-in">Live-In</option>
                                    <option value="weekends">Weekends Only</option>
                                </select>
                            </div>

                            <div className="pt-4 flex items-center gap-3">
                                {!isEmployer && (
                                    <button 
                                        type="button"
                                        onClick={() => setStep(1)}
                                        className="px-4 py-3 border border-gray-200 text-espresso rounded-brand-md font-bold text-sm hover:bg-gray-50 transition-colors"
                                    >
                                        Back
                                    </button>
                                )}
                                <button 
                                    onClick={handleSubmit}
                                    disabled={loading}
                                    className="flex-1 bg-teal text-white py-3 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-colors disabled:opacity-70 flex items-center justify-center"
                                >
                                    {loading ? 'Processing...' : 'Confirm & Pay Matching Fee ⚡'}
                                </button>
                            </div>
                            
                            <p className="text-center text-xs text-muted mt-2">
                                You will be redirected to secure payment.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
