import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Search({ maids, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [location, setLocation] = useState(filters?.location || '');
    const [schedule, setSchedule] = useState(filters?.schedule || '');

    const handleFilter = () => {
        router.get('/maids', { search, location, schedule }, { preserveState: true });
    };

    return (
        <>
            <Head title="Find Trusted Helpers | Maids.ng" />
            
            <div className="min-h-screen bg-ivory font-body">
                {/* Header */}
                <nav className="bg-white border-b border-gray-100 px-6 py-4 shadow-sm sticky top-0 z-30">
                    <div className="max-w-7xl mx-auto flex items-center justify-between">
                        <Link href="/">
                            <img src="/maids-logo.png" alt="Maids.ng" className="h-8" />
                        </Link>
                        <div className="flex items-center gap-4">
                            <Link href="/login" className="text-sm text-muted hover:text-espresso transition-colors">Sign In</Link>
                            <Link href="/register" className="bg-teal text-white px-5 py-2 rounded-brand-md text-sm font-bold hover:bg-teal/90 transition-all">Get Started</Link>
                        </div>
                    </div>
                </nav>

                {/* Hero Search */}
                <div className="bg-espresso text-white px-6 py-16 relative overflow-hidden">
                    <div className="absolute inset-0 opacity-5" style={{ backgroundImage: 'radial-gradient(circle at 1px 1px, white 1px, transparent 0)', backgroundSize: '30px 30px' }}></div>
                    <div className="max-w-4xl mx-auto text-center relative z-10">
                        <h1 className="font-display text-5xl font-light mb-4 tracking-tight">Find Your <span className="text-teal">Perfect Helper</span></h1>
                        <p className="text-white/60 mb-10 text-lg">AI-matched, verified, and background-checked domestic helpers across Nigeria.</p>
                        
                        <div className="bg-white/10 backdrop-blur-md rounded-brand-xl p-2 flex flex-col md:flex-row gap-2 border border-white/10">
                            <input 
                                type="text" 
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                placeholder="Search by name, skill, or role..." 
                                className="flex-1 bg-white/10 border-none rounded-brand-md px-5 py-3.5 text-sm text-white placeholder-white/40 focus:ring-2 focus:ring-teal/40"
                            />
                            <input 
                                type="text" 
                                value={location}
                                onChange={e => setLocation(e.target.value)}
                                placeholder="Location..." 
                                className="md:w-48 bg-white/10 border-none rounded-brand-md px-5 py-3.5 text-sm text-white placeholder-white/40 focus:ring-2 focus:ring-teal/40"
                            />
                            <select 
                                value={schedule}
                                onChange={e => setSchedule(e.target.value)}
                                className="md:w-40 bg-white/10 border-none rounded-brand-md px-5 py-3.5 text-sm text-white focus:ring-2 focus:ring-teal/40"
                            >
                                <option value="" className="bg-espresso">Any Schedule</option>
                                <option value="full-time" className="bg-espresso">Full-Time</option>
                                <option value="part-time" className="bg-espresso">Part-Time</option>
                                <option value="weekends" className="bg-espresso">Weekends</option>
                                <option value="live-in" className="bg-espresso">Live-In</option>
                            </select>
                            <button 
                                onClick={handleFilter}
                                className="bg-teal text-white px-8 py-3.5 rounded-brand-md font-bold text-sm hover:bg-teal/90 transition-all shadow-lg shadow-teal/30"
                            >
                                🔍 Search
                            </button>
                        </div>
                    </div>
                </div>

                {/* Results Grid */}
                <div className="max-w-7xl mx-auto px-6 py-12">
                    <div className="flex items-center justify-between mb-8">
                        <p className="text-muted text-sm">
                            Showing <span className="font-bold text-espresso">{maids.data?.length || 0}</span> verified helpers
                        </p>
                    </div>

                    {maids.data?.length > 0 ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {maids.data.map(maid => (
                                <Link key={maid.id} href={`/maids/${maid.id}`} className="group">
                                    <div className="bg-white rounded-brand-xl border border-gray-100 shadow-brand-1 hover:shadow-brand-3 transition-all duration-300 overflow-hidden group-hover:-translate-y-1">
                                        {/* Card Header */}
                                        <div className="h-3 bg-gradient-to-r from-teal to-teal/60"></div>
                                        <div className="p-6">
                                            <div className="flex items-start gap-4">
                                                <div className="w-14 h-14 bg-teal/10 rounded-full flex items-center justify-center text-xl text-teal font-bold flex-shrink-0 border-2 border-teal/20">
                                                    {maid.name?.charAt(0)}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <h3 className="font-bold text-espresso text-lg truncate group-hover:text-teal transition-colors">{maid.name}</h3>
                                                    <p className="text-teal text-xs font-mono uppercase tracking-widest mt-0.5">{maid.role || 'Domestic Helper'}</p>
                                                    <p className="text-muted text-sm mt-1">📍 {maid.location || 'Lagos'}</p>
                                                </div>
                                            </div>
                                            
                                            {/* Stats Row */}
                                            <div className="flex items-center gap-4 mt-5 pt-4 border-t border-gray-50">
                                                <div className="flex items-center gap-1">
                                                    <span className="text-amber-400">⭐</span>
                                                    <span className="text-sm font-bold text-espresso">{maid.rating || '—'}</span>
                                                </div>
                                                {maid.rate && (
                                                    <span className="text-sm text-muted">₦{Number(maid.rate).toLocaleString()}/mo</span>
                                                )}
                                                {maid.verified && (
                                                    <span className="ml-auto bg-success/10 text-success text-[9px] font-mono px-2 py-0.5 rounded-full uppercase tracking-widest font-bold">✓ Verified</span>
                                                )}
                                            </div>

                                            {/* Skills */}
                                            {maid.skills?.length > 0 && (
                                                <div className="flex flex-wrap gap-1.5 mt-3">
                                                    {maid.skills.slice(0, 4).map(skill => (
                                                        <span key={skill} className="bg-gray-50 text-muted px-2 py-0.5 rounded text-[10px] capitalize">{skill}</span>
                                                    ))}
                                                    {maid.skills.length > 4 && (
                                                        <span className="text-[10px] text-muted">+{maid.skills.length - 4} more</span>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-20">
                            <div className="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl">🔍</div>
                            <h3 className="text-xl font-display text-espresso mb-2">No helpers found</h3>
                            <p className="text-muted text-sm">Try adjusting your search criteria or clearing filters.</p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
