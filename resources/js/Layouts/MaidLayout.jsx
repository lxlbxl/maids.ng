import { Link } from '@inertiajs/react';
import Toast from '@/Components/Toast';

export default function MaidLayout({ children, user }) {
    const navItems = [
        { name: 'Dashboard', href: '/maid/dashboard', icon: '📊' },
        { name: 'My Bookings', href: '/maid/bookings', icon: '📅' },
        { name: 'Earnings', href: '/maid/earnings', icon: '💰' },
        { name: 'Verification', href: '/maid/verification', icon: '🛡️' },
        { name: 'Reviews', href: '/maid/reviews', icon: '⭐' },
        { name: 'Profile', href: '/maid/profile', icon: '👤' },
    ];

    return (
        <div className="min-h-screen bg-ivory font-body flex flex-col">
            {/* Top Nav */}
            <nav className="bg-espresso text-white px-6 py-4 shadow-md relative z-20">
                <div className="max-w-7xl mx-auto flex items-center justify-between">
                    <Link href="/">
                        <img src="/maids-logo-white.png" alt="Maids.ng" className="h-8 brightness-0 invert" />
                    </Link>
                    <div className="flex items-center gap-6">
                        <div className="flex items-center gap-2 pr-6 border-r border-white/10">
                            <span className="text-xs font-mono uppercase tracking-[0.2em] text-white/60">Helper Portal</span>
                        </div>
                        <div className="flex items-center gap-2 cursor-pointer group">
                            <div className="w-8 h-8 bg-white/10 rounded-full flex items-center justify-center text-white font-medium">
                                {user?.name?.charAt(0) || 'H'}
                            </div>
                            <span className="text-sm font-medium group-hover:text-teal transition-colors">{user?.name}</span>
                        </div>
                    </div>
                </div>
            </nav>

            <div className="flex-1 max-w-7xl w-full mx-auto flex flex-col md:flex-row py-8 px-6 gap-8 relative z-10">
                {/* Sidebar Nav */}
                <aside className="w-full md:w-64 flex-shrink-0">
                    <div className="bg-white rounded-brand-lg border border-gray-200 p-4 shadow-brand-1 space-y-1">
                        <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-muted mb-4 px-3">Helper Menu</p>
                        {navItems.map((item) => {
                            const isActive = typeof window !== 'undefined' && window.location.pathname === item.href;
                            return (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className={`flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-all ${
                                        isActive 
                                            ? 'bg-teal text-white shadow-brand-1 scale-[1.02]' 
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

                    {/* Sentinel Status Card */}
                    <div className="mt-6 bg-teal/5 rounded-brand-lg p-5 border border-teal/10">
                        <div className="flex items-center gap-3 mb-2">
                            <span className="text-xl">🛡️</span>
                            <span className="text-[10px] font-mono uppercase tracking-[0.15em] text-teal font-bold">Sentinel Active</span>
                        </div>
                        <p className="text-[11px] text-muted leading-relaxed">
                            Your performance is being monitored for quality assurance. Maintain high ratings to unlock more matches.
                        </p>
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
