import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

export default function ApiDocs({ endpoints, baseUrl }) {
    const [copied, setCopied] = useState(null);

    const handleCopy = (text, id) => {
        navigator.clipboard.writeText(text);
        setCopied(id);
        setTimeout(() => setCopied(null), 2000);
    };

    return (
        <AdminLayout>
            <Head title="API Documentation | Mission Control" />

            <div className="mb-10">
                <div className="flex items-center justify-between mb-2">
                    <h1 className="font-display text-4xl font-light tracking-tight text-white">API Documentation</h1>
                    <div className="flex items-center gap-2 px-3 py-1 bg-teal/10 border border-teal/20 rounded-full">
                        <span className="w-2 h-2 bg-teal rounded-full animate-pulse"></span>
                        <span className="text-[10px] font-mono text-teal uppercase tracking-widest font-bold">Standardized v1.0</span>
                    </div>
                </div>
                <p className="text-white/40 text-sm max-w-2xl">
                    Comprehensive reference for the Maids.ng REST API. Designed for high-performance agentic integrations 
                    and mobile consumers with consistent JSON envelopes and robust error handling.
                </p>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-4 gap-8">
                {/* Navigation Sidebar */}
                <div className="space-y-1 xl:sticky xl:top-8 self-start">
                    <p className="text-[10px] font-mono uppercase tracking-widest text-white/30 px-4 mb-4">Endpoints</p>
                    {endpoints.map((group, idx) => (
                        <a 
                            key={idx}
                            href={`#group-${idx}`}
                            className="block px-4 py-2 text-sm text-white/60 hover:text-white hover:bg-white/5 rounded-brand-lg transition-all"
                        >
                            {group.group}
                        </a>
                    ))}
                    <div className="mt-10 p-6 bg-teal/5 border border-teal/10 rounded-brand-xl">
                        <p className="text-teal text-xs font-bold mb-2">Authentication</p>
                        <p className="text-white/40 text-[10px] leading-relaxed">
                            All protected endpoints require a <code className="text-teal/80">Bearer Token</code> in the 
                            <code className="text-teal/80">Authorization</code> header.
                        </p>
                    </div>
                </div>

                {/* Content */}
                <div className="xl:col-span-3 space-y-16">
                    {endpoints.map((group, groupIdx) => (
                        <section key={groupIdx} id={`group-${groupIdx}`} className="animate-in fade-in slide-in-from-bottom-4 duration-500">
                            <div className="mb-8">
                                <h2 className="text-2xl font-display text-white mb-2">{group.group}</h2>
                                <p className="text-white/40 text-sm">{group.description}</p>
                            </div>

                            <div className="space-y-8">
                                {group.routes.map((route, routeIdx) => {
                                    const routeId = `${groupIdx}-${routeIdx}`;
                                    return (
                                        <div key={routeIdx} className="bg-[#121214] border border-white/5 rounded-brand-xl overflow-hidden shadow-xl">
                                            {/* Header */}
                                            <div className="px-6 py-4 bg-white/[0.02] border-b border-white/5 flex items-center justify-between">
                                                <div className="flex items-center gap-3">
                                                    <span className={`px-2 py-0.5 rounded text-[10px] font-bold font-mono uppercase tracking-widest ${
                                                        route.method === 'POST' ? 'bg-teal/20 text-teal' : 
                                                        route.method === 'GET' ? 'bg-blue-400/20 text-blue-400' : 
                                                        'bg-amber-400/20 text-amber-400'
                                                    }`}>
                                                        {route.method}
                                                    </span>
                                                    <h3 className="text-sm font-medium text-white/90">{route.name}</h3>
                                                    <code className="text-[11px] text-white/40 font-mono">{route.path}</code>
                                                </div>
                                                {route.auth && (
                                                    <span className="flex items-center gap-1.5 px-2 py-0.5 bg-white/5 rounded border border-white/10 text-[9px] font-mono text-white/40 uppercase tracking-tighter">
                                                        <svg className="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                        </svg>
                                                        Requires Auth
                                                    </span>
                                                )}
                                            </div>

                                            <div className="p-6 space-y-6">
                                                <p className="text-sm text-white/50">{route.description}</p>

                                                {route.params && (
                                                    <div className="space-y-3">
                                                        <p className="text-[10px] font-mono uppercase tracking-widest text-white/30">Parameters</p>
                                                        <div className="bg-black/20 rounded-brand-lg border border-white/5 divide-y divide-white/5">
                                                            {Object.entries(route.params).map(([name, rules]) => (
                                                                <div key={name} className="px-4 py-2 flex items-center justify-between">
                                                                    <code className="text-teal/80 text-[11px] font-mono font-bold">{name}</code>
                                                                    <span className="text-[10px] text-white/20 italic font-mono">{rules}</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                <div className="space-y-3">
                                                    <div className="flex items-center justify-between">
                                                        <p className="text-[10px] font-mono uppercase tracking-widest text-white/30">Request Example (cURL)</p>
                                                        <button 
                                                            onClick={() => handleCopy(route.curl, routeId)}
                                                            className="text-[10px] font-mono uppercase text-teal hover:text-teal-light transition-colors"
                                                        >
                                                            {copied === routeId ? 'Copied!' : 'Copy Command'}
                                                        </button>
                                                    </div>
                                                    <pre className="bg-[#0a0a0b] p-4 rounded-brand-lg border border-white/5 text-[11px] font-mono text-teal/90 overflow-x-auto leading-relaxed">
                                                        {route.curl}
                                                    </pre>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
