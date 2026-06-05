import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function PromptsIndex({ templates, agents, tiers }) {
    return (
        <AdminLayout>
            <Head title="Agent Prompt Templates" />

            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Agent Prompt Control</h1>
                    <div className="flex items-center gap-4">
                        <span className="flex items-center gap-2 text-[10px] font-mono uppercase tracking-[0.2em] text-white/40">
                            <span className="w-2 h-2 rounded-full bg-teal animate-pulse"></span>
                            Active Logic Override
                        </span>
                        <span className="text-white/10 text-xs">|</span>
                        <span className="text-[10px] font-mono uppercase tracking-[0.2em] text-white/40">
                            Central Intelligence Management
                        </span>
                    </div>
                </div>

                <Link
                    href={route('admin.agent.prompts.create')}
                    className="bg-teal text-white px-6 py-3 rounded-brand-md flex items-center gap-2 group hover:bg-teal-dark transition-all shadow-[0_0_15px_rgba(15,85,86,0.3)] hover:scale-105"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    <span className="font-mono text-[10px] uppercase tracking-widest font-bold">Deploy New Template</span>
                </Link>
            </div>

            {/* Agent groups */}
            <div className="space-y-10">
                {Object.entries(templates).map(([agentName, agentTemplates]) => (
                    <div key={agentName} className="bg-[#121214] border border-white/5 rounded-brand-lg overflow-hidden shadow-2xl">
                        <div className="px-8 py-6 border-b border-white/5 flex items-center justify-between bg-white/[0.02]">
                            <div className="flex items-center gap-4">
                                <div className="w-10 h-10 rounded shadow-inner bg-[#0a0a0b] border border-white/5 flex items-center justify-center text-xl">
                                    {agentName === 'ambassador' ? '🤝' : 
                                     agentName === 'scout' ? '🕵️' : 
                                     agentName === 'sentinel' ? '👮' : 
                                     agentName === 'referee' ? '⚖️' : 
                                     agentName === 'treasurer' ? '💰' : '🤖'}
                                </div>
                                <div>
                                    <h2 className="font-display text-xl text-white capitalize">{agentName} Agent</h2>
                                    <p className="text-[10px] font-mono uppercase tracking-widest text-white/20">System Directives & Logic</p>
                                </div>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm text-left">
                                <thead className="text-[10px] font-mono uppercase tracking-widest text-white/30 border-b border-white/5">
                                    <tr>
                                        <th className="px-8 py-5 font-medium">User Tier</th>
                                        <th className="px-8 py-5 font-medium">Version Label</th>
                                        <th className="px-8 py-5 font-medium">Build</th>
                                        <th className="px-8 py-5 font-medium">Operational Status</th>
                                        <th className="px-8 py-5 font-medium">Last Modified</th>
                                        <th className="px-8 py-5 font-medium text-right">Access</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-white/5">
                                    {agentTemplates.map((template) => (
                                        <tr key={template.id} className="hover:bg-white/[0.02] transition-colors group">
                                            <td className="px-8 py-5">
                                                <span className="px-3 py-1 rounded-full text-[10px] font-mono uppercase tracking-wider font-bold bg-teal/10 text-teal border border-teal/20 shadow-[0_0_10px_rgba(15,85,86,0.1)]">
                                                    {template.tier}
                                                </span>
                                            </td>
                                            <td className="px-8 py-5">
                                                <span className="text-white font-medium block">{template.label}</span>
                                            </td>
                                            <td className="px-8 py-5">
                                                <span className="font-mono text-[10px] text-white/40">v{template.version}.0</span>
                                            </td>
                                            <td className="px-8 py-5">
                                                <span
                                                    className={`inline-flex items-center gap-2 text-[10px] font-mono uppercase tracking-widest ${template.is_active
                                                            ? 'text-teal'
                                                            : 'text-white/20'
                                                        }`}
                                                >
                                                    <span className={`w-1.5 h-1.5 rounded-full ${template.is_active ? 'bg-teal animate-pulse shadow-[0_0_5px_rgba(15,85,86,0.8)]' : 'bg-white/10'}`}></span>
                                                    {template.is_active ? 'Online' : 'Standby'}
                                                </span>
                                            </td>
                                            <td className="px-8 py-5">
                                                <div className="flex flex-col">
                                                    <span className="text-white/60 text-xs">{template.editor?.name ?? 'System Core'}</span>
                                                    <span className="text-[9px] font-mono text-white/20 uppercase mt-1">{new Date(template.updated_at).toLocaleDateString()}</span>
                                                </div>
                                            </td>
                                            <td className="px-8 py-5 text-right space-x-4">
                                                <Link
                                                    href={route('admin.agent.prompts.edit', template.id)}
                                                    className="text-xs font-mono uppercase tracking-widest text-teal hover:text-white transition-colors"
                                                >
                                                    Modify
                                                </Link>
                                                {template.previous_prompt && (
                                                    <button
                                                        onClick={() => {
                                                            if (confirm('Initiate logic rollback to previous build?')) {
                                                                router.post(route('admin.agent.prompts.rollback', template.id));
                                                            }
                                                        }}
                                                        className="text-xs font-mono uppercase tracking-widest text-copper hover:text-white transition-colors"
                                                    >
                                                        Rollback
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}