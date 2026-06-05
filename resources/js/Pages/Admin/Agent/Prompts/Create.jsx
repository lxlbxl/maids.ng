import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function PromptsCreate({ agents, tiers }) {
    const { data, setData, post, processing, errors } = useForm({
        agent_name: '',
        tier: '',
        label: '',
        system_prompt: '',
    });

    const placeholderGuide = [
        '{{AGENT_NAME}}     — Replaced with "Maids.ng AI Assistant"',
        '{{BUSINESS_NAME}}  — Replaced with "Maids.ng"',
        '{{MATCHING_FEE}}   — Replaced with live matching fee from settings',
        '{{COMMISSION_RATE}}— Replaced with live commission rate',
        '{{GUARANTEE_DAYS}} — Replaced with guarantee period',
        '{{CURRENT_DATE}}   — Replaced with today\'s date',
    ];

    return (
        <AdminLayout>
            <Head title="Create Prompt Template" />

            <div className="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6 max-w-6xl mx-auto">
                <div>
                    <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">Initialize Directive</h1>
                    <div className="flex items-center gap-4">
                        <span className="flex items-center gap-2 text-[10px] font-mono uppercase tracking-[0.2em] text-white/40">
                            <span className="w-2 h-2 rounded-full bg-teal animate-pulse"></span>
                            Status: System Awaiting Neural Path
                        </span>
                    </div>
                </div>
                
                <Link
                    href={route('admin.agent.prompts.index')}
                    className="text-[10px] font-mono uppercase tracking-widest text-white/40 hover:text-white transition-colors flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    Abort Initialization
                </Link>
            </div>

            <div className="max-w-6xl mx-auto grid grid-cols-1 xl:grid-cols-3 gap-10">
                {/* Tactical Sidebar Guide */}
                <div className="space-y-6">
                    <div className="bg-[#121214] border border-white/5 rounded-brand-lg p-8 shadow-2xl">
                        <div className="flex items-center gap-3 mb-6">
                            <span className="text-xl">📡</span>
                            <h3 className="font-display text-xl text-white">Neural Variables</h3>
                        </div>
                        <p className="text-xs text-white/40 leading-relaxed mb-6 italic">
                            Use these injection markers to provide live system data to the agent's context.
                        </p>
                        <div className="space-y-4">
                            {placeholderGuide.map((p, i) => {
                                const [variable, desc] = p.split('—');
                                return (
                                    <div key={i} className="group cursor-help">
                                        <div className="flex justify-between items-center mb-1">
                                            <span className="font-mono text-[10px] text-teal font-bold tracking-wider group-hover:text-white transition-colors">{variable.trim()}</span>
                                            <span className="text-[9px] font-mono text-white/10 uppercase">Marker</span>
                                        </div>
                                        <p className="text-[11px] text-white/30 group-hover:text-white/50 transition-colors leading-snug">{desc.trim()}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="bg-copper/5 border border-copper/10 rounded-brand-lg p-6">
                        <div className="flex items-center gap-3 mb-4">
                            <span className="text-lg">⚖️</span>
                            <h3 className="font-display text-lg text-copper">Protocol Warning</h3>
                        </div>
                        <p className="text-[11px] text-white/40 leading-relaxed italic">
                            Each agent/tier combination must have exactly one active directive. Initializing a new one will archive the current version if it exists.
                        </p>
                    </div>
                </div>

                {/* Main Logic Console */}
                <div className="xl:col-span-2">
                    <div className="bg-[#121214] border border-white/5 rounded-brand-lg overflow-hidden shadow-2xl">
                        <div className="px-8 py-6 border-b border-white/5 bg-white/[0.02] flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="w-8 h-8 rounded bg-[#0a0a0b] border border-white/5 flex items-center justify-center text-sm">📝</div>
                                <h2 className="font-display text-lg">Directive Configuration</h2>
                            </div>
                        </div>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                post(route('admin.agent.prompts.store'));
                            }}
                            className="p-8 space-y-8"
                        >
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div className="space-y-2">
                                    <label className="block text-[10px] font-mono uppercase tracking-[0.2em] text-white/20 ml-1">Target Agent</label>
                                    <select
                                        value={data.agent_name}
                                        onChange={(e) => setData('agent_name', e.target.value)}
                                        className="w-full bg-[#0a0a0b] border-white/10 rounded-brand-md focus:border-teal focus:ring focus:ring-teal/20 px-4 py-4 text-white transition-all"
                                    >
                                        <option value="">Select Agent...</option>
                                        {agents.map((agent) => (
                                            <option key={agent} value={agent}>
                                                {agent.toUpperCase()} UNIT
                                            </option>
                                        ))}
                                    </select>
                                    {errors.agent_name && (
                                        <p className="text-danger text-[10px] font-mono uppercase tracking-widest mt-2 ml-1">{errors.agent_name}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <label className="block text-[10px] font-mono uppercase tracking-[0.2em] text-white/20 ml-1">User Classification</label>
                                    <select
                                        value={data.tier}
                                        onChange={(e) => setData('tier', e.target.value)}
                                        className="w-full bg-[#0a0a0b] border-white/10 rounded-brand-md focus:border-teal focus:ring focus:ring-teal/20 px-4 py-4 text-white transition-all"
                                    >
                                        <option value="">Select Tier...</option>
                                        {tiers.map((tier) => (
                                            <option key={tier} value={tier}>
                                                {tier.toUpperCase()} TIER
                                            </option>
                                        ))}
                                    </select>
                                    {errors.tier && (
                                        <p className="text-danger text-[10px] font-mono uppercase tracking-widest mt-2 ml-1">{errors.tier}</p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label className="block text-[10px] font-mono uppercase tracking-[0.2em] text-white/20 ml-1">Build Label</label>
                                <input
                                    type="text"
                                    value={data.label}
                                    onChange={(e) => setData('label', e.target.value)}
                                    className="w-full bg-[#0a0a0b] border-white/10 rounded-brand-md focus:border-teal focus:ring focus:ring-teal/20 px-4 py-4 text-white transition-all placeholder:text-white/5"
                                    placeholder="e.g. Guest Onboarding Neural Path v1.0"
                                />
                                {errors.label && (
                                    <p className="text-danger text-[10px] font-mono uppercase tracking-widest mt-2 ml-1">{errors.label}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <div className="flex justify-between items-center ml-1">
                                    <label className="block text-[10px] font-mono uppercase tracking-[0.2em] text-white/20">System Logic Manifest</label>
                                    <span className="text-[9px] font-mono text-white/10 bg-white/5 px-2 py-0.5 rounded-full uppercase">
                                        {data.system_prompt.length} Tokens Approx.
                                    </span>
                                </div>
                                <textarea
                                    value={data.system_prompt}
                                    onChange={(e) => setData('system_prompt', e.target.value)}
                                    rows={25}
                                    className="w-full bg-[#0a0a0b] border-white/10 rounded-brand-md focus:border-teal focus:ring focus:ring-teal/20 px-6 py-6 font-mono text-[13px] leading-relaxed text-white/70 transition-all placeholder:text-white/5 shadow-inner"
                                    placeholder="Define the agent's core reasoning path..."
                                />
                                {errors.system_prompt && (
                                    <p className="text-danger text-[10px] font-mono uppercase tracking-widest mt-2 ml-1">{errors.system_prompt}</p>
                                )}
                            </div>

                            <div className="flex items-center gap-6 pt-8 border-t border-white/5">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-teal text-white px-8 py-4 rounded-brand-md font-mono text-[10px] uppercase tracking-widest font-bold hover:bg-teal-dark disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-[0_0_20px_rgba(15,85,86,0.3)]"
                                >
                                    {processing ? 'Generating Build...' : 'Deploy Initial Directive'}
                                </button>
                                <Link
                                    href={route('admin.agent.prompts.index')}
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