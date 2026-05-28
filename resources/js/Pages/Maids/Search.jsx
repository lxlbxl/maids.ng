import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import DirectHireModal from '@/Components/DirectHireModal';

export default function Search({ maids, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [location, setLocation] = useState(filters?.location || '');
    const [schedule, setSchedule] = useState(filters?.schedule || '');
    const [hiringMaid, setHiringMaid] = useState(null);

    const handleFilter = () => {
        router.get('/maids', { search, location, schedule }, { preserveState: true });
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') handleFilter();
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
                                onKeyDown={handleKeyDown}
                                placeholder="Search by name, skill, or role..." 
                                className="flex-1 bg-white/10 border-none rounded-brand-md px-5 py-3.5 text-sm text-white placeholder-white/40 focus:ring-2 focus:ring-teal/40"
                            />
                            <input 
                                type="text" 
                                value={location}
                                onChange={e => setLocation(e.target.value)}
                                onKeyDown={handleKeyDown}
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
                                        {/* Photo + Info Layout */}
                                        <div className="flex">
                                            {/* Maid Photo — Large & Prominent */}
                                            <div className="w-[140px] md:w-[160px] flex-shrink-0 relative overflow-hidden bg-gradient-to-br from-teal/10 to-teal/5">
                                                {maid.avatar ? (
                                                    <img 
                                                        src={maid.avatar} 
                                                        alt={maid.name} 
                                                        className="w-full h-full object-cover min-h-[200px]"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <div className="w-full h-full min-h-[200px] flex items-center justify-center">
                                                        <span className="text-5xl font-bold text-teal/30">{maid.name?.charAt(0)}</span>
                                                    </div>
                                                )}
                                                {/* Availability badge overlay */}
                                                {maid.availability_status === 'available' && (
                                                    <div className="absolute top-2 left-2 bg-success/90 backdrop-blur-sm text-white text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide flex items-center gap-1">
                                                        <span className="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span>
                                                        Available
                                                    </div>
                                                )}
                                            </div>

                                            {/* Info Panel */}
                                            <div className="flex-1 p-4 flex flex-col justify-between min-w-0">
                                                {/* Top: Name + Role */}
                                                <div>
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0">
                                                            <h3 className="font-bold text-espresso text-base truncate group-hover:text-teal transition-colors">{maid.name}</h3>
                                                            <p className="text-teal text-[10px] font-mono uppercase tracking-widest mt-0.5 font-bold">{maid.role || 'Domestic Helper'}</p>
                                                        </div>
                                                        {maid.verified && (
                                                            <span className="bg-success/10 text-success text-[8px] font-mono px-1.5 py-0.5 rounded-full uppercase tracking-widest font-bold flex-shrink-0 whitespace-nowrap">✓ Verified</span>
                                                        )}
                                                    </div>
                                                    <p className="text-muted text-xs mt-1.5 flex items-center gap-1">
                                                        <svg className="w-3 h-3 text-muted/70" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clipRule="evenodd"/></svg>
                                                        <span className="truncate">{maid.location || 'Lagos'}</span>
                                                    </p>
                                                </div>

                                                {/* Middle: Stats */}
                                                <div className="flex items-center gap-3 mt-3 text-xs">
                                                    <div className="flex items-center gap-1">
                                                        <span className="text-amber-400">⭐</span>
                                                        <span className="font-bold text-espresso">{maid.rating || '—'}</span>
                                                    </div>
                                                    {maid.rate && (
                                                        <span className="text-muted font-medium">₦{Number(maid.rate).toLocaleString()}<span className="text-[10px]">/mo</span></span>
                                                    )}
                                                    {maid.experience_years > 0 && (
                                                        <span className="text-muted">{maid.experience_years}yr{maid.experience_years !== 1 ? 's' : ''} exp</span>
                                                    )}
                                                </div>

                                                {/* Skills Tags */}
                                                {maid.skills?.length > 0 && (
                                                    <div className="flex flex-wrap gap-1 mt-3">
                                                        {maid.skills.slice(0, 3).map(skill => (
                                                            <span key={skill} className="bg-gray-50 text-muted px-2 py-0.5 rounded text-[10px] capitalize border border-gray-100">{skill}</span>
                                                        ))}
                                                        {maid.skills.length > 3 && (
                                                            <span className="text-[10px] text-muted/70 px-1 py-0.5">+{maid.skills.length - 3} more</span>
                                                        )}
                                                    </div>
                                                )}

                                                {/* Bottom: View Profile + Quick Hire */}
                                                <div className="flex items-center gap-2 mt-3 pt-3 border-t border-gray-50">
                                                    <span className="flex-1 bg-teal text-white text-center py-2 rounded-brand-md text-xs font-bold group-hover:bg-teal/90 transition-all">
                                                        View Profile
                                                    </span>
                                                    <button
                                                        onClick={(e) => {
                                                            e.preventDefault();
                                                            e.stopPropagation();
                                                            setHiringMaid(maid);
                                                        }}
                                                        className="flex-shrink-0 bg-espresso text-white text-xs font-bold px-3 py-2 rounded-brand-md hover:bg-espresso/80 transition-all whitespace-nowrap"
                                                        title={`Hire ${maid.name?.split(' ')[0]}`}
                                                    >
                                                        ⚡ Hire
                                                    </button>
                                                    <span className="w-8 h-8 flex items-center justify-center rounded-brand-md border border-gray-200 text-muted/50 hover:text-rose-400 hover:border-rose-200 transition-colors text-sm">
                                                        ♡
                                                    </span>
                                                </div>
                                            </div>
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

            {hiringMaid && (
                <DirectHireModal
                    maid={hiringMaid}
                    onClose={() => setHiringMaid(null)}
                />
            )}
        </>
    );
}
