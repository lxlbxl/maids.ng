import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function KnowledgeIndex({ articles, categories, filters }) {
    return (
        <AdminLayout>
            <Head title="Knowledge Base" />

            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Knowledge Repository</h1>
                    <div className="flex items-center gap-4">
                        <span className="flex items-center gap-2 text-[10px] font-mono uppercase tracking-[0.2em] text-white/40">
                            <span className="w-2 h-2 rounded-full bg-teal animate-pulse"></span>
                            Live Neural Context
                        </span>
                        <span className="text-white/10 text-xs">|</span>
                        <span className="text-[10px] font-mono uppercase tracking-[0.2em] text-white/40">
                            Agent Augmentation Data
                        </span>
                    </div>
                </div>

                <Link
                    href={route('admin.agent.knowledge.create')}
                    className="bg-teal text-white px-6 py-3 rounded-brand-md flex items-center gap-2 group hover:bg-teal-dark transition-all shadow-[0_0_15px_rgba(15,85,86,0.3)] hover:scale-105"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    <span className="font-mono text-[10px] uppercase tracking-widest font-bold">Inject New Article</span>
                </Link>
            </div>

            {/* Tactical Filters */}
            <div className="flex flex-col sm:flex-row gap-4 mb-10 bg-[#121214] p-6 rounded-brand-lg border border-white/5 shadow-2xl">
                <div className="flex-1">
                    <label className="block text-[9px] font-mono uppercase tracking-[0.2em] text-white/20 mb-2 ml-1">Classification Filter</label>
                    <select
                        className="w-full bg-[#0a0a0b] border-white/10 rounded-brand-md focus:border-teal focus:ring focus:ring-teal/20 px-4 py-3 text-sm text-white transition-all"
                        onChange={(e) => {
                            router.get(
                                route('admin.agent.knowledge.index', {
                                    category: e.target.value || undefined,
                                    search: filters.search,
                                })
                            );
                        }}
                        value={filters.category || ''}
                    >
                        <option value="">All Knowledge Tiers</option>
                        {categories.map((cat) => (
                            <option key={cat} value={cat}>
                                {cat.charAt(0).toUpperCase() + cat.slice(1)}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="flex-[2] relative">
                    <label className="block text-[9px] font-mono uppercase tracking-[0.2em] text-white/20 mb-2 ml-1">Semantic Search</label>
                    <div className="relative">
                        <div className="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-white/20">
                            <svg className="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <input
                            type="text"
                            placeholder="Query the database..."
                            defaultValue={filters.search}
                            className="w-full bg-[#0a0a0b] border-white/10 rounded-brand-md focus:border-teal focus:ring focus:ring-teal/20 pl-12 pr-4 py-3 text-sm text-white transition-all placeholder:text-white/10"
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    router.get(
                                        route('admin.agent.knowledge.index', {
                                            category: filters.category,
                                            search: e.target.value || undefined,
                                        })
                                    );
                                }
                            }}
                        />
                    </div>
                </div>
            </div>

            {/* Articles Inventory */}
            <div className="bg-[#121214] border border-white/5 rounded-brand-lg overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm text-left">
                        <thead className="text-[10px] font-mono uppercase tracking-widest text-white/30 border-b border-white/5 bg-white/[0.01]">
                            <tr>
                                <th className="px-8 py-5 font-medium">Class</th>
                                <th className="px-8 py-5 font-medium">Article Title</th>
                                <th className="px-8 py-5 font-medium">Rank</th>
                                <th className="px-8 py-5 font-medium">Assigned Agents</th>
                                <th className="px-8 py-5 font-medium">User Access</th>
                                <th className="px-8 py-5 font-medium">Status</th>
                                <th className="px-8 py-5 font-medium text-right">Ops</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {articles.data.map((article) => (
                                <tr key={article.id} className="hover:bg-white/[0.02] transition-colors group">
                                    <td className="px-8 py-5">
                                        <span
                                            className={`px-3 py-1 rounded-full text-[10px] font-mono uppercase tracking-wider font-bold border ${article.category === 'restriction'
                                                    ? 'bg-danger/10 text-danger border-danger/20 shadow-[0_0_10px_rgba(200,55,45,0.1)]'
                                                    : article.category === 'policy'
                                                        ? 'bg-teal/10 text-teal border-teal/20 shadow-[0_0_10px_rgba(15,85,86,0.1)]'
                                                        : article.category === 'procedure'
                                                            ? 'bg-white/10 text-white/60 border-white/20'
                                                            : article.category === 'faq'
                                                                ? 'bg-copper/10 text-copper border-copper/20 shadow-[0_0_10px_rgba(180,83,9,0.1)]'
                                                                : 'bg-white/5 text-white/40 border-white/10'
                                                }`}
                                        >
                                            {article.category}
                                        </span>
                                    </td>
                                    <td className="px-8 py-5">
                                        <span className="text-white font-medium block">{article.title}</span>
                                        <span className="text-[10px] text-white/20 font-mono mt-1">ID: KN-{article.id.toString().padStart(4, '0')}</span>
                                    </td>
                                    <td className="px-8 py-5 font-mono text-xs text-white/40">
                                        <div className="flex items-center gap-2">
                                            <div className="w-12 h-1 bg-white/5 rounded-full overflow-hidden">
                                                <div className="h-full bg-teal" style={{ width: `${(article.priority / 10) * 100}%` }}></div>
                                            </div>
                                            <span>{article.priority}</span>
                                        </div>
                                    </td>
                                    <td className="px-8 py-5">
                                        <div className="flex flex-wrap gap-1">
                                            {article.applies_to.map(agent => (
                                                <span key={agent} className="text-[9px] font-mono uppercase bg-white/5 px-1.5 py-0.5 rounded text-white/40 border border-white/10">{agent}</span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-8 py-5">
                                        <div className="flex flex-wrap gap-1">
                                            {article.visible_to_tiers.map(tier => (
                                                <span key={tier} className="text-[9px] font-mono uppercase bg-white/5 px-1.5 py-0.5 rounded text-white/40 border border-white/10">{tier}</span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-8 py-5">
                                        <span
                                            className={`inline-flex items-center gap-2 text-[10px] font-mono uppercase tracking-widest ${article.is_active
                                                    ? 'text-teal'
                                                    : 'text-white/20'
                                                }`}
                                        >
                                            <span className={`w-1.5 h-1.5 rounded-full ${article.is_active ? 'bg-teal animate-pulse shadow-[0_0_5px_rgba(15,85,86,0.8)]' : 'bg-white/10'}`}></span>
                                            {article.is_active ? 'Online' : 'Standby'}
                                        </span>
                                    </td>
                                    <td className="px-8 py-5 text-right space-x-4">
                                        <Link
                                            href={route('admin.agent.knowledge.edit', article.id)}
                                            className="text-xs font-mono uppercase tracking-widest text-teal hover:text-white transition-colors"
                                        >
                                            Modify
                                        </Link>
                                        <button
                                            onClick={() => {
                                                if (confirm('Deactivate this article? Agent semantic retrieval will stop immediately.')) {
                                                    router.delete(route('admin.agent.knowledge.destroy', article.id));
                                                }
                                            }}
                                            className="text-xs font-mono uppercase tracking-widest text-danger hover:text-white transition-colors"
                                        >
                                            Purge
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Data Stream Pagination */}
            {articles.last_page > 1 && (
                <div className="flex justify-center mt-10 gap-3">
                    {Array.from({ length: articles.last_page }, (_, i) => i + 1).map((page) => (
                        <button
                            key={page}
                            onClick={() => {
                                router.get(
                                    route('admin.agent.knowledge.index', {
                                        page,
                                        category: filters.category,
                                        search: filters.search,
                                    })
                                );
                            }}
                            className={`px-4 py-2 rounded-brand-md text-[10px] font-mono uppercase tracking-widest transition-all ${page === articles.current_page
                                    ? 'bg-teal text-white shadow-[0_0_15px_rgba(15,85,86,0.3)]'
                                    : 'bg-[#121214] border border-white/10 text-white/40 hover:text-white hover:border-white/20'
                                }`}
                        >
                            Node {page}
                        </button>
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}