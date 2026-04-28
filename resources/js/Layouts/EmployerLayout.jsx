import { Link } from '@inertiajs/react';
import Toast from '@/Components/Toast';

export default function EmployerLayout({ children, user }) {
    const navItems = [
        { name: 'Dashboard', href: '/employer/dashboard', icon: '📊' },
        { name: 'Matches', href: '/onboarding', icon: '🔍' },
        { name: 'Bookings', href: '/employer/bookings', icon: '📅' },
        { name: 'Payments', href: '/employer/payments', icon: '💳' },
        { name: 'Reviews', href: '/employer/reviews', icon: '⭐' },
        { name: 'Profile', href: '/employer/profile', icon: '👤' },
    ];

    return (
        <div className="min-h-screen bg-ivory font-body flex flex-col">
            {/* Top Nav */}
            <nav className="bg-white border-b border-gray-200 px-6 py-4 shadow-sm relative z-20">
                <div className="max-w-7xl mx-auto flex items-center justify-between">
                    <Link href="/">
                        <img src="/maids-logo.png" alt="Maids.ng" className="h-8" />
                    </Link>
                    <div className="flex items-center gap-6">
                        <Link href="/onboarding" className="bg-teal text-white px-4 py-2 rounded-brand-md text-sm hover:bg-teal-dark transition-all">Find Helper</Link>
                        <div className="flex items-center gap-2 border-l border-gray-200 pl-6 cursor-pointer group">
                            <div className="w-8 h-8 bg-teal/10 rounded-full flex items-center justify-center text-teal font-medium">
                                {user?.name?.charAt(0) || 'E'}
                            </div>
                            <span className="text-sm font-medium text-espresso group-hover:text-teal">{user?.name}</span>
                        </div>
                    </div>
                </div>
            </nav>

            <div className="flex-1 max-w-7xl w-full mx-auto flex flex-col md:flex-row py-8 px-6 gap-8 relative z-10">
                {/* Sidebar Nav */}
                <aside className="w-full md:w-64 flex-shrink-0">
                    <div className="bg-white rounded-brand-lg border border-gray-200 p-4 shadow-brand-1 space-y-1">
                        <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-muted mb-4 px-3">Employer Menu</p>
                        {navItems.map((item) => {
                            const isActive = typeof window !== 'undefined' && window.location.pathname === item.href;
                            return (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className={`flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors ${
                                        isActive 
                                            ? 'bg-teal/5 text-teal' 
                                            : 'text-muted hover:bg-gray-50 hover:text-espresso'
                                    }`}
                                >
                                    <span>{item.icon}</span>
                                    {item.name}
                                </Link>
                            );
                        })}
                        <div className="pt-4 mt-4 border-t border-gray-100">
                            <Link 
                                href="/logout" 
                                method="post" 
                                as="button" 
                                className="w-full flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-danger hover:bg-danger/5 transition-colors"
                            >
                                <span>🚪</span>
                                Logout
                            </Link>
                        </div>
                    </div>
                </aside>

                {/* Main Content */}
                <main className="flex-1">
                    <Toast />
                    {children}
                </main>
            </div>
        </div>
    );
}
