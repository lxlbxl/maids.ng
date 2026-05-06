import { Link, usePage } from '@inertiajs/react';
import Toast from '@/Components/Toast';
import BottomNavBar from '@/Components/BottomNavBar';

export default function AdminLayout({ children }) {
    const { auth, agentHealth = {} } = usePage().props;

    // Primary nav items for bottom bar (most important)
    const primaryNavItems = [
        { name: 'Dashboard', href: '/admin/dashboard', icon: '📡' },
        { name: 'Maids', href: '/admin/maids', icon: '🧹' },
        { name: 'Users', href: '/admin/users', icon: '👤' },
        { name: 'Bookings', href: '/admin/bookings', icon: '📅' },
    ];

    // Secondary nav items (shown in "More" menu)
    const secondaryNavItems = [
        { name: 'Agent Control', href: '/admin/agents', icon: '🤖' },
        { name: 'Agent Activity', href: '/admin/audit', icon: '📋' },
        { name: 'Escalations', href: '/admin/escalations', icon: '⚖️' },
        { name: 'Verification Hub', href: '/admin/verifications', icon: '🛡️' },
        { name: 'Verification Transactions', href: '/admin/verification-transactions', icon: '📄' },
        { name: 'Staff Control', href: '/admin/staff', icon: '👮' },
        { name: 'Matching Queue', href: '/admin/matching', icon: '🧠' },
        { name: 'Prompt Management', href: '/admin/agent/prompts', icon: '📝' },
        { name: 'Knowledge Base', href: '/admin/agent/knowledge', icon: '📚' },
        { name: 'Salary Ops', href: '/admin/salary', icon: '💸' },
        { name: 'Financial Control', href: '/admin/payments', icon: '💰' },
        { name: 'System Settings', href: '/admin/settings', icon: '⚙️' },
    ];

    const navItems = [...primaryNavItems, ...secondaryNavItems];

    const agents = [
        { name: 'Sentinel', status: 'Active', color: 'text-teal' },
        { name: 'Treasurer', status: 'Active', color: 'text-success' },
        { name: 'Referee', status: 'Standby', color: 'text-copper' },
        { name: 'Gatekeeper', status: 'Active', color: 'text-teal' },
    ];

    const handleLogout = () => {
        document.getElementById('logout-form')?.submit();
    };

    return (
        <div className="min-h-screen bg-[#0a0a0b] text-white font-body flex flex-col">
            {/* Mission Control Top Bar */}
            <nav className="bg-[#121214] border-b border-white/5 px-4 md:px-6 py-3 md:py-4 relative z-20 shadow-2xl">
                <div className="max-w-[1600px] mx-auto flex items-center justify-between">
                    <div className="flex items-center gap-3 md:gap-6">
                        <Link href="/admin/dashboard" className="flex items-center gap-2 md:gap-3 group">
                            <div className="w-7 h-7 md:w-8 md:h-8 bg-teal rounded-brand-sm flex items-center justify-center shadow-[0_0_15px_rgba(45,164,142,0.4)]">
                                <img src="/maids-logo-white.png" alt="M" className="h-3 md:h-4 brightness-0 invert" />
                            </div>
                            <span className="font-display text-sm md:text-lg tracking-tight group-hover:text-teal transition-colors hidden sm:inline">Mission Control</span>
                        </Link>

                        <div className="hidden lg:flex items-center gap-4 ml-8 border-l border-white/10 pl-8">
                            {agents.map(agent => (
                                <div key={agent.name} className="flex items-center gap-2">
                                    <div className={`w-1.5 h-1.5 rounded-full ${agent.status === 'Active' ? 'bg-teal animate-pulse' : 'bg-white/20'}`}></div>
                                    <span className="font-mono text-[9px] uppercase tracking-widest text-white/40">{agent.name}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-3 md:gap-6">
                        <div className="flex flex-col items-end hidden sm:flex">
                            <span className="text-xs font-bold text-teal">{auth?.user?.name}</span>
                            <span className="text-[9px] font-mono uppercase tracking-[0.2em] text-white/30">Platform Administrator</span>
                        </div>
                        <div className="w-8 h-8 md:w-9 md:h-9 bg-white/5 rounded-full border border-white/10 flex items-center justify-center text-xs md:text-sm">
                            A
                        </div>
                    </div>
                </div>
            </nav>

            <div className="flex-1 max-w-[1600px] w-full mx-auto flex overflow-hidden">
                {/* Tactical Sidebar - Hidden on mobile */}
                <aside className="hidden md:flex w-64 bg-[#121214] border-r border-white/5 flex-col py-8 px-4 gap-8">
                    <div className="space-y-1">
                        <p className="font-mono text-[9px] uppercase tracking-[0.25em] text-white/20 mb-4 px-3 font-bold">Main Console</p>
                        {navItems.map((item) => {
                            const isActive = typeof window !== 'undefined' && window.location.pathname === item.href;
                            return (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className={`flex items-center gap-3 px-3 py-2.5 rounded-brand-md text-sm transition-all group ${isActive
                                        ? 'bg-white/5 text-teal border border-teal/20 shadow-[inset_0_0_10px_rgba(45,164,142,0.1)]'
                                        : 'text-white/40 hover:text-white hover:bg-white/5'
                                        }`}
                                >
                                    <span className={`text-lg transition-transform group-hover:scale-110 ${isActive ? 'grayscale-0' : 'grayscale'}`}>{item.icon}</span>
                                    <span className="tracking-tight font-medium">{item.name}</span>
                                </Link>
                            );
                        })}
                    </div>

                    <div className="mt-auto pt-8 border-t border-white/5 space-y-4">
                        <div className="bg-teal/5 border border-teal/10 rounded-brand-lg p-4">
                            <p className="font-mono text-[9px] uppercase text-teal mb-2">Network Status</p>
                            <div className="flex items-center justify-between mb-1">
                                <span className="text-[10px] text-white/40">Latency</span>
                                <span className="text-[10px] text-success">Optimal</span>
                            </div>
                            <div className="w-full h-1 bg-white/5 rounded-full overflow-hidden mt-2">
                                <div className="h-full bg-teal w-4/5 shadow-[0_0_5px_rgba(45,164,142,0.5)]"></div>
                            </div>
                        </div>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="w-full flex items-center gap-3 px-3 py-2 text-sm text-white/40 hover:text-danger transition-colors font-mono uppercase tracking-widest text-[10px]"
                        >
                            <span>🚪</span>
                            Terminate Session
                        </Link>
                    </div>
                </aside>

                {/* Command Output Area */}
                <main className="flex-1 overflow-y-auto bg-[#0a0a0b] relative">
                    {/* Background Grid Accent */}
                    <div className="absolute inset-0 z-0 opacity-[0.03] pointer-events-none"
                        style={{ backgroundImage: 'radial-gradient(circle at 1px 1px, white 1px, transparent 0)', backgroundSize: '40px 40px' }}></div>

                    <div className="relative z-10 p-4 md:p-10 h-full">
                        <Toast />
                        {children}
                    </div>
                </main>
            </div>

            {/* Mobile Bottom Navigation */}
            <BottomNavBar
                items={primaryNavItems}
                moreItems={secondaryNavItems}
                activeColor="text-teal"
                activeBg="bg-white/5"
                bgColor="bg-[#121214]"
                borderColor="border-white/10"
                onLogout={handleLogout}
            />

            {/* Hidden logout form for mobile */}
            <form id="logout-form" action="/logout" method="POST" className="hidden">
                <button type="submit">Logout</button>
            </form>
        </div>
    );
}
