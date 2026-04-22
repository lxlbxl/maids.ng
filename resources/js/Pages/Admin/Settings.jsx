import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

export default function Settings({ auth, settings, aiManifest }) {
    const [activeTab, setActiveTab] = useState('ai');
    
    // Flatten settings for useForm
    const initialData = {};
    Object.values(settings).flat().forEach(s => {
        initialData[s.key] = {
            value: s.value || '',
            group: s.group,
            is_encrypted: s.is_encrypted
        };
    });

    // Ensure core keys exist to prevent UI crashes
    const coreKeys = ['ai_active_provider', 'openai_model', 'openrouter_model', 'openai_key', 'openrouter_key', 'platform_name', 'service_fee_percentage'];
    coreKeys.forEach(key => {
        if (!initialData[key]) {
            initialData[key] = { value: '', group: 'general', is_encrypted: false };
        }
    });

    const { data, setData, post, processing, errors } = useForm({
        settings: initialData
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.settings.update'));
    };

    const handleSettingChange = (key, value) => {
        setData('settings', {
            ...data.settings,
            [key]: { ...data.settings[key], value }
        });
    };

    return (
        <AdminLayout>
            <Head title="System Settings | Mission Control" />
            
            <div className="mb-10">
                <h1 className="font-display text-4xl font-light tracking-tight text-white mb-2">System Configuration</h1>
                <p className="text-white/40 text-sm">Fine-tune the platform's autonomous brain and operational parameters.</p>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-4 gap-8">
                {/* Sidebar Navigation */}
                <div className="space-y-2">
                    {[
                        { id: 'ai', name: 'Intelligence (AI)', icon: '🧠' },
                        { id: 'general', name: 'General Platform', icon: '⚙️' },
                        { id: 'finance', name: 'Financial Logic', icon: '💰' },
                    ].map(tab => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`w-full flex items-center gap-4 px-6 py-4 rounded-brand-lg border transition-all ${activeTab === tab.id ? 'bg-teal/10 border-teal text-teal shadow-[0_0_20px_rgba(45,164,142,0.1)]' : 'bg-white/5 border-white/5 text-white/40 hover:bg-white/[0.08]'}`}
                        >
                            <span className="text-xl">{tab.icon}</span>
                            <span className="font-mono text-[10px] uppercase tracking-widest font-bold">{tab.name}</span>
                        </button>
                    ))}
                </div>

                {/* Settings Panels */}
                <div className="xl:col-span-3">
                    <form onSubmit={handleSubmit} className="bg-[#121214] border border-white/5 rounded-brand-xl p-10 shadow-2xl space-y-12">
                        
                        {activeTab === 'ai' && (
                            <div className="space-y-10 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div>
                                    <h2 className="text-2xl font-display mb-2 text-teal">Neural Configuration</h2>
                                    <p className="text-white/40 text-xs italic">Select your primary LLM provider and define model access.</p>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    {/* Provider Selection */}
                                    <div className="space-y-4">
                                        <label className="block font-mono text-[10px] uppercase tracking-widest text-white/30">Active Intelligence Provider</label>
                                        <select 
                                            value={data.settings.ai_active_provider.value}
                                            onChange={(e) => handleSettingChange('ai_active_provider', e.target.value)}
                                            className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none appearance-none"
                                        >
                                            <option value="openai">OpenAI (Direct Native)</option>
                                            <option value="openrouter">OpenRouter (Unified API)</option>
                                        </select>
                                    </div>

                                    {/* Model Selection based on Provider */}
                                    <div className="space-y-4">
                                        <label className="block font-mono text-[10px] uppercase tracking-widest text-white/30">Active Processing Model</label>
                                        <select 
                                            value={data.settings[`${data.settings.ai_active_provider.value}_model`].value}
                                            onChange={(e) => handleSettingChange(`${data.settings.ai_active_provider.value}_model`, e.target.value)}
                                            className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none appearance-none"
                                        >
                                            {Object.entries(aiManifest[data.settings.ai_active_provider.value].models).map(([val, label]) => (
                                                <option key={val} value={val}>{label}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                <div className="pt-8 border-t border-white/5 space-y-8">
                                    {/* API Keys */}
                                    <div className="space-y-6">
                                        <div className="flex items-center justify-between">
                                            <h3 className="font-display text-lg">Identity & Access Keys</h3>
                                            <span className="text-[10px] font-mono text-copper uppercase bg-copper/10 px-2 py-0.5 rounded tracking-widest">Encrypted Storage Active</span>
                                        </div>

                                        <div className="grid grid-cols-1 gap-6">
                                            <div className="space-y-2">
                                                <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">OpenAI API Key</p>
                                                <input 
                                                    type="password"
                                                    value={data.settings.openai_key.value}
                                                    onChange={(e) => handleSettingChange('openai_key', e.target.value)}
                                                    placeholder="sk-..."
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">OpenRouter API Key</p>
                                                <input 
                                                    type="password"
                                                    value={data.settings.openrouter_key.value}
                                                    onChange={(e) => handleSettingChange('openrouter_key', e.target.value)}
                                                    placeholder="sk-or-v1-..."
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'general' && (
                            <div className="space-y-10 animate-in fade-in slide-in-from-right-4 duration-500">
                                <h2 className="text-2xl font-display mb-2">Platform Identity</h2>
                                <div className="grid grid-cols-1 gap-8">
                                    <div className="space-y-2">
                                        <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Marketplace Name</p>
                                        <input 
                                            type="text"
                                            value={data.settings.platform_name.value}
                                            onChange={(e) => handleSettingChange('platform_name', e.target.value)}
                                            className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'finance' && (
                            <div className="space-y-10 animate-in fade-in slide-in-from-right-4 duration-500">
                                <h2 className="text-2xl font-display mb-2">Economic Guardrails</h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div className="space-y-2">
                                        <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Service Fee (%)</p>
                                        <div className="relative">
                                            <input 
                                                type="number"
                                                value={data.settings.service_fee_percentage.value}
                                                onChange={(e) => handleSettingChange('service_fee_percentage', e.target.value)}
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-10 pt-4 text-white focus:border-teal/50 outline-none"
                                            />
                                            <span className="absolute right-6 top-1/2 -translate-y-1/2 text-white/20 font-mono">%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="pt-10 border-t border-white/5 flex justify-end">
                            <button 
                                type="submit"
                                disabled={processing}
                                className="bg-teal text-espresso px-12 py-4 rounded-brand-lg text-xs font-bold uppercase tracking-widest hover:brightness-110 disabled:opacity-50 transition-all shadow-[0_0_30px_rgba(45,164,142,0.2)]"
                            >
                                {processing ? 'Syncing...' : 'Save Configuration'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
