import { Head, useForm } from '@inertiajs/react';
import EmployerLayout from '@/Layouts/EmployerLayout';

export default function Profile({ auth, user }) {
    const { data, setData, post, processing } = useForm({
        name: user?.name || '',
        phone: user?.phone || '',
        location: user?.location || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('employer.profile.update'));
    };

    return (
        <EmployerLayout user={auth?.user}>
            <Head title="My Profile | Employer" />
            
            <div className="mb-8">
                <h1 className="font-display text-3xl font-light text-espresso">My Profile</h1>
                <p className="text-muted mt-2">Manage your account information and preferences.</p>
            </div>

            {/* Profile Header */}
            <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6 mb-6">
                <div className="flex flex-col md:flex-row items-start md:items-center gap-6">
                    <div className="w-20 h-20 bg-teal/10 rounded-full flex items-center justify-center text-3xl text-teal font-bold border-2 border-teal/20">
                        {user?.name?.charAt(0) || 'E'}
                    </div>
                    <div className="flex-1">
                        <h2 className="text-xl font-bold text-espresso">{user?.name}</h2>
                        <p className="text-sm text-muted mt-1">{user?.email}</p>
                        <span className="inline-block mt-2 bg-teal/10 text-teal px-3 py-1 rounded-full text-[10px] font-mono uppercase tracking-widest font-bold">Employer</span>
                    </div>
                    <button className="bg-gray-100 text-muted px-4 py-2 rounded-brand-md text-sm hover:bg-gray-200 transition-all">
                        📷 Change Photo
                    </button>
                </div>
            </div>

            {/* Edit Form */}
            <form onSubmit={handleSubmit}>
                <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6 mb-6">
                    <h3 className="font-display text-lg text-espresso mb-6 border-b border-gray-100 pb-3">Personal Information</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">Full Name</label>
                            <input type="text" value={data.name} onChange={e => setData('name', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">Phone Number</label>
                            <input type="tel" value={data.phone} onChange={e => setData('phone', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">Location</label>
                            <input type="text" value={data.location} onChange={e => setData('location', e.target.value)} className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm focus:border-teal focus:ring-1 focus:ring-teal/20" placeholder="e.g. Lekki, Lagos" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-espresso mb-2">Email Address</label>
                            <input type="email" value={user?.email || ''} disabled className="w-full border border-gray-200 rounded-brand-md px-4 py-3 text-sm bg-gray-50 text-muted cursor-not-allowed" />
                            <p className="text-xs text-muted mt-1">Email cannot be changed</p>
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end">
                        <button type="submit" disabled={processing} className="bg-teal text-white px-8 py-3 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all disabled:opacity-50">
                            {processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </div>
            </form>

            {/* Account Security */}
            <div className="bg-white rounded-brand-lg border border-gray-200 shadow-brand-1 p-6">
                <h3 className="font-display text-lg text-espresso mb-4 border-b border-gray-100 pb-3">🔒 Account Security</h3>
                <div className="flex items-center justify-between py-3 border-b border-gray-50">
                    <div>
                        <p className="text-sm font-medium text-espresso">Password</p>
                        <p className="text-xs text-muted">Last changed: Never</p>
                    </div>
                    <button className="text-teal text-sm font-medium hover:underline">Change Password</button>
                </div>
                <div className="flex items-center justify-between py-3">
                    <div>
                        <p className="text-sm font-medium text-espresso">Account Status</p>
                        <p className="text-xs text-muted">Your account is active and in good standing</p>
                    </div>
                    <span className="bg-success/10 text-success px-3 py-1 rounded-full text-[10px] font-mono uppercase tracking-widest font-bold">Active</span>
                </div>
            </div>
        </EmployerLayout>
    );
}
