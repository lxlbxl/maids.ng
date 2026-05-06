import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function KnowledgeEdit({ article, categories, agents, tiers }) {
    const { data, setData, put, processing, errors } = useForm({
        category: article.category,
        title: article.title,
        content: article.content,
        applies_to: article.applies_to,
        visible_to_tiers: article.visible_to_tiers,
        priority: article.priority,
        is_active: article.is_active,
    });

    const toggleArray = (field, value) => {
        const current = data[field];
        if (current.includes(value)) {
            setData(
                field,
                current.filter((v) => v !== value)
            );
        } else {
            setData(field, [...current, value]);
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('admin.agent.knowledge.update', article.id));
    };

    return (
        <AdminLayout>
            <Head title={`Edit Article — ${article.title}`} />

            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6 max-w-6xl mx-auto">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Modify Intelligence</h1>
                    <div className="flex items-center gap-4">
                        <span className="flex items-center gap-2 text-[10px] font-mono uppercase tracking-[0.2em] text-white/40">
                            <span className="w-2 h-2 rounded-full bg-teal animate-pulse"></span>
                            Ref: KN-{article.id.toString().padStart(4, '0')}
                        </span>
                        <span className="text-white/10 text-xs">|</span>
                        <span className="text-[10px] font-mono uppercase tracking-[0.2em] text-white/40">
                            Neural Sync: Active
                        </span>
                    </div>
                </div>
                
                <Link
                    href={route('admin.agent.knowledge.index')}
                    className="text-[10px] font-mono uppercase tracking-widest text-white/40 hover:text-white transition-colors flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    Abort Sync
                </Link>
            </div>

            <div className="max-w-6xl mx-auto grid grid-cols-1 xl:grid-cols-3 gap-10">
                {/* Tactical Configuration Sidebar */}
                <div className="space-y-6">
                    <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-8 shadow-2xl">
                        <div className="flex items-center gap-3 mb-6">
                            <span className="text-xl">⚖️</span>
                            <h3 className="font-display text-xl text-white">Priority Ranking</h3>
                        </div>
                        <div className="space-y-4">
                            <div className="bg-[#0a0a0b] p-4 rounded-brand-md border border-white/5">
                                <label className="block text-[10px] font-mono uppercase tracking-[0.2em] text-white/20 mb-3 text-center">Rank Magnitude</label>
                                <input
                                    type="number"
                                    value={data.priority}
                                    onChange={(e) => setData('priority', parseInt(e.target.value))}
                                    min={1}
                                    max={999}
                                    className="w-full bg-transparent border-none text-white text-4xl font-mono text-center focus:ring-0"
                                />
                            </div>
                            <div className="text-[10px] font-mono text-white/20 space-y-2 uppercase tracking-widest">
                                <div className="flex justify-between items-center"><span className="text-danger">001-010</span><span>Restriction</span></div>
                                <div className="flex justify-between items-center"><span className="text-teal">011-030</span><span>Core Policy</span></div>
                                <div className="flex justify-between items-center"><span className="text-white/60">031-060</span><span>Procedure</span></div>
                                <div className="flex justify-between items-center"><span className="text-copper">061-999</span><span>General FAQ</span></div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-8">
                        <div className="flex items-center gap-3 mb-6">
                            <span className="text-xl">🤖</span>
                            <h3 className="font-display text-xl text-white">Target Units</h3>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {agents.map((agent) => (
                                <button
                                    key={agent}
                                    type="button"
                                    onClick={() => toggleArray('applies_to', agent)}
                                    className={`px-3 py-1.5 rounded-brand-sm text-[10px] font-mono uppercase tracking-widest transition-all border ${data.applies_to.includes(agent)
                                        ? 'bg-teal text-white border-teal shadow-[0_0_10px_rgba(15,85,86,0.3)]'
                                        : 'bg-white/5 text-white/40 border-white/10 hover:border-white/20'
                                        }`}
                                >
                                    {agent}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-8">
                        <div className="flex items-center gap-3 mb-6">
                            <span className="text-xl">👥</span>
                            <h3 className="font-display text-xl text-white">Access Tiers</h3>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {tiers.map((tier) => (
                                <button
                                    key={tier}
                                    type="button"
                                    onClick={() => toggleArray('visible_to_tiers', tier)}
                                    className={`px-3 py-1.5 rounded-brand-sm text-[10px] font-mono uppercase tracking-widest transition-all border ${data.visible_to_tiers.includes(tier)
                                        ? 'bg-copper text-white border-copper shadow-[0_0_10px_rgba(180,83,9,0.3)]'
                                        : 'bg-white/5 text-white/40 border-white/10 hover:border-white/20'
                                        }`}
                                >
                                    {tier}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Main Content Area */}
                <div className="xl:col-span-2">
                    <div className="bg-[#121214] border border-white/5 rounded-brand-lg overflow-hidden shadow-2xl">
                        <div className="px-8 py-6 border-b border-white/5 bg-white/[0.02] flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="w-8 h-8 rounded bg-[#0a0a0b] border border-white/5 flex items-center justify-center text-sm">📚</div>
                                <h2 className="font-display text-lg">Article Manifest</h2>
                            </div>
                            
                            <label className="inline-flex items-center gap-3 cursor-pointer group">
                                <div className="relative">
                                    <input
                                        type="checkbox"
                                        checked={data.is_active}
                                        onChange={(e) => setData('is_active', e.target.checked)}
                                        className="sr-only"
                                    />
                                    <div className={`block w-10 h-5 rounded-full transition-colors ${data.is_active ? 'bg-teal' : 'bg-white/10'}`}></div>
                                    <div className={`absolute left-1 top-1 bg-white w-3 h-3 rounded-full transition-transform ${data.is_active ? 'translate-x-5' : ''}`}></div>
                                </div>
                                <span className={`text-[10px] font-mono uppercase tracking-widest ${data.is_active ? 'text-teal' : 'text-white/20'}`}>
                                    {data.is_active ? 'Online' : 'Standby'}
                                </span>
                            </label>
                        </div>

                        <form onSubmit={handleSubmit} className="p-8 space-y-8">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div className="space-y-2">
                                    <label className="block text-[10px] font-mono uppercase tracking-[0.2em] text-white/20 ml-1">Knowledge Category</label>
                                    <select
                                        value={data.category}
                                        onChange={(e) => setData('category', e.target.value)}
                                        className="w-full bg-[#0a0a0b] border-white/10 rounded-brand-md focus:border-teal focus:ring focus:ring-teal/20 px-4 py-4 text-white transition-all appearance-none"
                                    >
                                        {categories.map((cat) => (
                                            <option key={cat} value={cat}>
                                                {cat.toUpperCase()}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="space-y-2">
                                    <label className="block text-[10px] font-mono uppercase tracking-[0.2em] text-white/20 ml-1">Article Title</label>
                                    <input
                                        type="text"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        className="w-full bg-[#0a0a0b] border-white/10 rounded-brand-md focus:border-teal focus:ring focus:ring-teal/20 px-4 py-4 text-white transition-all placeholder:text-white/5"
                                        placeholder="Define the article subject..."
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <div className="flex justify-between items-center ml-1">
                                    <label className="block text-[10px] font-mono uppercase tracking-[0.2em] text-white/20">Neural Content Base</label>
                                    <span className="text-[9px] font-mono text-white/10 bg-white/5 px-2 py-0.5 rounded-full uppercase">
                                        {data.content.length} Semantic Units
                                    </span>
                                </div>
                                <textarea
                                    value={data.content}
                                    onChange={(e) => setData('content', e.target.value)}
                                    rows={18}
                                    className="w-full bg-[#0a0a0b] border-white/10 rounded-brand-md focus:border-teal focus:ring focus:ring-teal/20 px-6 py-6 font-mono text-[13px] leading-relaxed text-white/70 transition-all placeholder:text-white/5 shadow-inner"
                                    placeholder="Enter the detailed context for agents to ingest..."
                                />
                                {errors.content && (
                                    <p className="text-danger text-[10px] font-mono uppercase tracking-widest mt-2 ml-1">{errors.content}</p>
                                )}
                            </div>

                            <div className="flex items-center gap-6 pt-8 border-t border-white/5">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-teal text-white px-8 py-4 rounded-brand-md font-mono text-[10px] uppercase tracking-widest font-bold hover:bg-teal-dark disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-[0_0_20px_rgba(15,85,86,0.3)]"
                                >
                                    {processing ? 'Synchronizing...' : 'Update & Sync Repository'}
                                </button>
                                <Link
                                    href={route('admin.agent.knowledge.index')}
                                    className="text-[10px] font-mono uppercase tracking-widest text-white/20 hover:text-white transition-colors"
                                >
                                    Cancel
                                </Link>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}