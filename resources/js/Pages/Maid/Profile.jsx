import { Head, useForm } from '@inertiajs/react';
import MaidLayout from '@/Layouts/MaidLayout';

export default function Profile({ auth, user, profile }) {
    const { data, setData, post, processing, errors } = useForm({
        name: user?.name || '',
        phone: user?.phone || '',
        location: user?.location || '',
        bio: profile?.bio || '',
        expected_salary: profile?.expected_salary || '',
        experience_years: profile?.experience_years || 0,
        schedule_preference: profile?.schedule_preference || 'full-time',
        bank_name: profile?.bank_name || '',
        account_number: profile?.account_number || '',
        account_name: profile?.account_name || '',
    });

    const availableSkills = ['cleaning', 'cooking', 'laundry', 'childcare', 'elderly-care', 'gardening', 'ironing', 'shopping', 'tutoring', 'pet-care', 'deep-cleaning', 'organizing', 'baking', 'meal-planning', 'first-aid', 'companionship'];
    const currentSkills = profile?.skills || [];

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('maid.profile.update'));
    };

    const handleBankSubmit = (e) => {
        e.preventDefault();
        post(route('maid.profile.bank'), {
            data: { bank_name: data.bank_name, account_number: data.account_number, account_name: data.account_name },
        });
    };

    const statusColors = {
        available: 'bg-teal/10 text-teal',
        busy: 'bg-copper/10 text-copper',
        unavailable: 'bg-danger/10 text-danger',
    };

    const statusLabels = {
        available: '✅ Available for Work',
        busy: '🔴 Busy',
        unavailable: '⏸️ Not Available',
    };

    return (
        <MaidLayout user={auth?.user}>
            <Head title="My Profile | Maids.ng" />
            
            <div className="mb-8">
                <h1 className="font-display text-3xl font-light text-espresso">My Profile</h1>
                <p className="text-muted mt-2">Update your details, skills, and bank account for receiving payment.</p>
            </div>

            {/* Name & Status Card */}
            <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6 mb-6">
                <div className="flex flex-col md:flex-row items-start md:items-center gap-6">
                    <div className="w-20 h-20 bg-teal/10 rounded-full flex items-center justify-center text-3xl text-teal font-bold border-2 border-teal/20">
                        {user?.name?.charAt(0) || 'H'}
                    </div>
                    <div className="flex-1">
                        <h2 className="text-xl font-bold text-espresso">{user?.name}</h2>
                        <p className="text-sm text-muted mt-1">{user?.email}</p>
                        <div className="flex items-center gap-3 mt-3 flex-wrap">
                            <span className={`px-3 py-1 rounded-full text-[10px] font-mono uppercase tracking-widest font-bold ${statusColors[profile?.availability_status] || 'bg-gray-100 text-muted'}`}>
                                {statusLabels[profile?.availability_status] || 'Status Unknown'}
                            </span>
                            {profile?.nin_verified && (
                                <span className="bg-success/10 text-success px-3 py-1 rounded-full text-[10px] font-mono uppercase tracking-widest font-bold">✓ ID Verified</span>
                            )}
                            <span className="text-sm text-muted">⭐ {profile?.rating || '—'} ({profile?.total_reviews || 0} ratings)</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Personal Info Form */}
            <form onSubmit={handleSubmit}>
                <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6 mb-6">
                    <h3 className="font-display text-lg text-espresso mb-1 border-b border-gray-100 pb-3">My Personal Information</h3>
                    <p className="text-xs text-muted mb-6">Make sure your details are correct. Employers will see this.</p>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">Your Full Name</label>
                            <input type="text" value={data.name} onChange={e => setData('name', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" />
                            {errors.name && <p className="text-danger text-xs mt-1">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">Your Phone Number</label>
                            <input type="tel" value={data.phone} onChange={e => setData('phone', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">Where Do You Live? (Town / Area)</label>
                            <input type="text" value={data.location} onChange={e => setData('location', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" placeholder="e.g. Lekki, Lagos" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">How Much Pay Do You Want? (₦ per month)</label>
                            <input type="number" value={data.expected_salary} onChange={e => setData('expected_salary', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" placeholder="e.g. 50000" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">How Many Years Have You Been Working as a Helper?</label>
                            <input type="number" value={data.experience_years} onChange={e => setData('experience_years', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">What Type of Work Do You Want?</label>
                            <select value={data.schedule_preference} onChange={e => setData('schedule_preference', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20">
                                <option value="full-time">Full-Time (Monday – Saturday)</option>
                                <option value="part-time">Part-Time (A few hours each day)</option>
                                <option value="weekends">Weekends Only</option>
                                <option value="live-in">Live-In (Stay at employer's house)</option>
                            </select>
                        </div>
                        <div className="md:col-span-2">
                            <label className="block text-sm font-medium text-espresso mb-2">Tell Employers a Little About Yourself</label>
                            <textarea rows={4} value={data.bio} onChange={e => setData('bio', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" placeholder="Write a few sentences about yourself — what kind of work you do, how many years you've worked, and why you are a good helper..." />
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end">
                        <button type="submit" disabled={processing} className="bg-teal text-white px-8 py-3 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all disabled:opacity-50">
                            {processing ? 'Saving...' : 'Save My Info'}
                        </button>
                    </div>
                </div>
            </form>

            {/* Skills */}
            <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6 mb-6">
                <h3 className="font-display text-lg text-espresso mb-1 border-b border-gray-100 pb-3">What Can I Do? (My Skills)</h3>
                <p className="text-xs text-muted mb-4">Tap on the skills that match what you can do. The ones in green are already selected.</p>
                <div className="flex flex-wrap gap-2">
                    {availableSkills.map(skill => (
                        <span key={skill} className={`px-4 py-2 rounded-full text-sm cursor-pointer transition-all ${currentSkills.includes(skill) ? 'bg-teal text-white shadow-brand-1' : 'bg-gray-100 text-muted hover:bg-gray-200'}`}>
                            {skill}
                        </span>
                    ))}
                </div>
                <p className="text-xs text-muted mt-4">Tap a skill to select or remove it.</p>
            </div>

            {/* Bank Details */}
            <form onSubmit={handleBankSubmit}>
                <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6">
                    <h3 className="font-display text-lg text-espresso mb-1 border-b border-gray-100 pb-3">💰 My Bank Account (Where I Receive Money)</h3>
                    <p className="text-xs text-muted mb-6">This is the account your salary will be sent to. Make sure it is correct.</p>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">Bank Name</label>
                            <input type="text" value={data.bank_name} onChange={e => setData('bank_name', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" placeholder="e.g. First Bank" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">Account Number (10 digits)</label>
                            <input type="text" value={data.account_number} onChange={e => setData('account_number', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" maxLength={10} />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">Account Name (Name on the account)</label>
                            <input type="text" value={data.account_name} onChange={e => setData('account_name', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" />
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end">
                        <button type="submit" disabled={processing} className="bg-espresso text-white px-8 py-3 rounded-brand-md text-sm font-bold hover:bg-espresso/90 transition-all disabled:opacity-50">
                            {processing ? 'Saving...' : 'Save Bank Details'}
                        </button>
                    </div>
                </div>
            </form>
        </MaidLayout>
    );
}
