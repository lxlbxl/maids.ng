import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState, useEffect, useCallback, useRef } from 'react';
import axios from 'axios';

export default function Settings({ auth, settings, aiManifest, mcpServers: initialMcpServers = [] }) {
    const [activeTab, setActiveTab] = useState('ai');
    const [fetchedModels, setFetchedModels] = useState({});
    const [modelSearch, setModelSearch] = useState('');
    const [isFetchingModels, setIsFetchingModels] = useState(false);
    const [fetchError, setFetchError] = useState(null);
    const [fetchSuccess, setFetchSuccess] = useState(null);
    const [isDropdownOpen, setIsDropdownOpen] = useState(false);
    const [testResults, setTestResults] = useState({});
    const [generatedToken, setGeneratedToken] = useState(null);
    const dropdownRef = useRef(null);
    const initialFetchDone = useRef({});

    // MCP Manager state
    const [mcpServers, setMcpServers] = useState(initialMcpServers);
    const [mcpForm, setMcpForm] = useState({ name: '', base_url: '', auth_token: '' });
    const [mcpEditId, setMcpEditId] = useState(null);
    const [mcpLoading, setMcpLoading] = useState(false);
    const [mcpMsg, setMcpMsg] = useState(null);
    const [mcpTestResult, setMcpTestResult] = useState({});
    const [mcpSnippet, setMcpSnippet] = useState({});

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsDropdownOpen(false);
                // If they click outside, reset the search so it shows their actual selected value
                setModelSearch('');
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

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
    const coreKeys = [
        // AI Settings
        'ai_active_provider', 'openai_model', 'openrouter_model', 'openai_key', 'openrouter_key', 'ai_temperature', 'ai_max_tokens', 'ai_system_prompt',
        // General Settings
        'platform_name', 'app_url', 'app_timezone', 'app_debug',
        // Financial Settings
        'service_fee_percentage', 'matching_fee_amount', 'nin_verification_fee', 'standalone_verification_fee',
        'commission_type', 'commission_percent', 'commission_fixed_amount',
        'min_salary', 'max_salary',
        // Payment Gateway Settings
        'paystack_public_key', 'paystack_secret_key', 'paystack_base_url',
        'flutterwave_public_key', 'flutterwave_secret_key', 'flutterwave_encryption_key', 'flutterwave_base_url',
        'default_payment_gateway',
        // Verification Settings
        'qoreid_client_id', 'qoreid_client_secret', 'qoreid_base_url',
        // SMS Settings
        'termii_api_key', 'termii_sender_id', 'termii_url',
        // Email Settings
        'mail_mailer', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption',
        'mail_from_address', 'mail_from_name',
        // Script & Pixel Injection — Frontend
        'script_google_head_frontend', 'script_google_body_frontend', 'script_google_footer_frontend',
        'script_meta_head_frontend', 'script_meta_body_frontend', 'script_meta_footer_frontend',
        'script_custom_head_frontend', 'script_custom_body_frontend', 'script_custom_footer_frontend',
        // Script & Pixel Injection — Member Area
        'script_google_head_member', 'script_google_body_member', 'script_google_footer_member',
        'script_meta_head_member', 'script_meta_body_member', 'script_meta_footer_member',
        'script_custom_head_member', 'script_custom_body_member', 'script_custom_footer_member',
    ];
    coreKeys.forEach(key => {
        if (!initialData[key]) {
            initialData[key] = { value: '', group: 'scripts', is_encrypted: false };
        }
    });

    const { data, setData, post, processing, errors } = useForm({
        settings: initialData
    });

    // Fetch models from API
    const fetchModels = useCallback(async (provider, search = '', forceRefresh = false) => {
        if (!provider) return;

        setIsFetchingModels(true);
        setFetchError(null);
        setFetchSuccess(null);

        try {
            const params = new URLSearchParams();
            if (search) params.set('search', search);
            if (forceRefresh) params.set('refresh', '1');

            const url = `/admin/ai/models/${provider}${params.toString() ? '?' + params.toString() : ''}`;

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            // Handle non-JSON responses (e.g. login redirects)
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                setFetchError('Session expired. Please reload the page and try again.');
                return;
            }

            const result = await response.json();

            if (result.success && result.data) {
                setFetchedModels(prev => ({
                    ...prev,
                    [provider]: result.data.models
                }));
                if (forceRefresh) {
                    setFetchSuccess(`Loaded ${result.data.total_available} models from ${provider}`);
                    setTimeout(() => setFetchSuccess(null), 3000);
                }
            } else {
                setFetchError(result.message || result.error || 'Failed to fetch models');
            }
        } catch (error) {
            console.error('Model fetch error:', error);
            setFetchError('Network error while fetching models. Check your connection.');
        } finally {
            setIsFetchingModels(false);
        }
    }, []);

    // Fetch models when provider changes (initial load only)
    useEffect(() => {
        const provider = data.settings.ai_active_provider?.value;
        if (provider && !initialFetchDone.current[provider]) {
            initialFetchDone.current[provider] = true;
            fetchModels(provider);
        }
    }, [data.settings.ai_active_provider?.value, fetchModels]);

    // Debounced search is NO LONGER needed since we fetch all models at once
    // and filter them instantly on the frontend.

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

    const handleRefreshModels = () => {
        const provider = data.settings.ai_active_provider?.value || 'openai';
        // Reset cached data for this provider
        setFetchedModels(prev => {
            const next = { ...prev };
            delete next[provider];
            return next;
        });
        initialFetchDone.current[provider] = true;
        // Force refresh all models from backend
        fetchModels(provider, '', true);
    };

    const testConnection = async (provider) => {
        setTestResults(prev => ({ ...prev, [provider]: { loading: true } }));
        try {
            const endpoints = {
                openai: route('admin.settings.test.openai'),
                openrouter: route('admin.settings.test.openrouter'),
                qoreid: route('admin.settings.test.qoreid'),
            };
            const endpoint = endpoints[provider];

            if (!endpoint) {
                setTestResults(prev => ({
                    ...prev,
                    [provider]: { loading: false, success: false, message: 'Unknown provider' }
                }));
                return;
            }

            const res = await axios.post(endpoint);
            setTestResults(prev => ({ ...prev, [provider]: { loading: false, ...res.data } }));
        } catch (e) {
            setTestResults(prev => ({
                ...prev,
                [provider]: {
                    loading: false,
                    success: false,
                    message: e.response?.data?.message || e.message,
                }
            }));
        }
    };

    const currentProvider = data.settings.ai_active_provider?.value || 'openai';
    const allModels = fetchedModels[currentProvider] || aiManifest?.[currentProvider]?.models || {};
    const modelKey = `${currentProvider}_model`;

    // Filter models based on search query INSTANTLY
    const filteredModels = Object.entries(allModels).filter(([id, name]) => {
        if (!modelSearch) return true;
        const searchLower = modelSearch.toLowerCase();
        return id.toLowerCase().includes(searchLower) || (typeof name === 'string' && name.toLowerCase().includes(searchLower));
    });

    // Show up to 100 to prevent browser lag, but let them search to narrow it down
    const currentModels = Object.fromEntries(filteredModels.slice(0, 100));

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
                        { id: 'payment', name: 'Payment Gateways', icon: '💳' },
                        { id: 'verification', name: 'Verification', icon: '✓' },
                        { id: 'sms', name: 'SMS Gateway', icon: '💬' },
                        { id: 'email', name: 'Email Settings', icon: '✉️' },
                        { id: 'scripts', name: 'Scripts & Pixels', icon: '🌐' },
                        { id: 'api', name: 'API & Security', icon: '🔑' },
                        { id: 'mcp', name: 'MCP Manager', icon: '🔌' },
                        { id: 'deployment', name: 'Deployment & Cron', icon: '🚀' },
                    ].map(tab => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`w-full flex items-center gap-3 px-4 py-3 rounded-brand-lg transition-all text-left ${activeTab === tab.id
                                ? 'bg-teal/10 border border-teal/30 text-teal'
                                : 'bg-white/5 border border-transparent text-white/60 hover:bg-white/10 hover:text-white'
                                }`}
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
                                            value={data.settings.ai_active_provider?.value || 'openai'}
                                            onChange={(e) => {
                                                handleSettingChange('ai_active_provider', e.target.value);
                                                setModelSearch(''); // Clear search when provider changes
                                                setFetchError(null);
                                                setFetchSuccess(null);
                                            }}
                                            className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none appearance-none"
                                        >
                                            <option value="openai">OpenAI (Direct Native)</option>
                                            <option value="openrouter">OpenRouter (Unified API)</option>
                                        </select>
                                    </div>

                                    {/* Model Selection with Combobox */}
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between">
                                            <label className="block font-mono text-[10px] uppercase tracking-widest text-white/30">Active Processing Model</label>
                                            <button
                                                type="button"
                                                onClick={handleRefreshModels}
                                                disabled={isFetchingModels}
                                                className="text-[10px] font-mono uppercase tracking-wider text-teal/70 hover:text-teal transition-colors disabled:opacity-40 flex items-center gap-1"
                                            >
                                                <span className={isFetchingModels ? 'animate-spin inline-block' : ''}>↻</span>
                                                {isFetchingModels ? 'Loading...' : 'Refresh Models'}
                                            </button>
                                        </div>

                                        {/* Custom Combobox */}
                                        <div className="relative" ref={dropdownRef}>
                                            <input
                                                type="text"
                                                value={isDropdownOpen ? modelSearch : (data.settings[modelKey]?.value || '')}
                                                onChange={(e) => {
                                                    setModelSearch(e.target.value);
                                                    if (!isDropdownOpen) setIsDropdownOpen(true);
                                                }}
                                                onFocus={() => {
                                                    setModelSearch(''); // Clear search on focus to show default list
                                                    setIsDropdownOpen(true);
                                                }}
                                                placeholder={data.settings[modelKey]?.value || "Search or select a model..."}
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none text-sm cursor-text"
                                            />

                                            {isFetchingModels ? (
                                                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-teal/60 text-[10px] uppercase tracking-widest animate-pulse">Fetching</span>
                                            ) : (
                                                <div
                                                    className="absolute right-4 top-1/2 -translate-y-1/2 cursor-pointer p-2 text-white/30 hover:text-white/60"
                                                    onClick={() => setIsDropdownOpen(!isDropdownOpen)}
                                                >
                                                    <svg className={`w-4 h-4 transition-transform duration-200 ${isDropdownOpen ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                </div>
                                            )}

                                            {/* Dropdown Menu */}
                                            {isDropdownOpen && (
                                                <div className="absolute z-50 w-full mt-2 bg-[#121214] border border-white/10 rounded-brand-lg shadow-2xl max-h-72 overflow-y-auto py-2">
                                                    {Object.keys(currentModels).length === 0 ? (
                                                        <div className="px-6 py-4 text-white/40 text-sm">No models found matching "{modelSearch}".</div>
                                                    ) : (
                                                        Object.entries(currentModels).map(([val, label]) => {
                                                            const isSelected = data.settings[modelKey]?.value === val;
                                                            return (
                                                                <div
                                                                    key={val}
                                                                    onClick={() => {
                                                                        handleSettingChange(modelKey, val);
                                                                        setModelSearch('');
                                                                        setIsDropdownOpen(false);
                                                                    }}
                                                                    className={`px-6 py-3 cursor-pointer text-sm transition-colors flex flex-col gap-0.5 ${isSelected ? 'text-teal bg-teal/5 border-l-2 border-teal' : 'text-white/80 hover:bg-white/5 hover:text-white border-l-2 border-transparent'
                                                                        }`}
                                                                >
                                                                    <span className="font-medium truncate">{label}</span>
                                                                    {val !== label && (
                                                                        <span className="text-white/30 text-[10px] font-mono truncate">{val}</span>
                                                                    )}
                                                                </div>
                                                            );
                                                        })
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {/* Status messages */}
                                        <div className="space-y-1">
                                            <p className="text-white/30 text-[10px] font-mono tracking-widest uppercase mt-2">
                                                {modelSearch
                                                    ? `${Object.keys(currentModels).length} matches`
                                                    : Object.keys(allModels).length > 10
                                                        ? `Showing 10 of ${Object.keys(allModels).length} (Search for more)`
                                                        : `${Object.keys(allModels).length} models available`
                                                }
                                            </p>

                                            {fetchError && (
                                                <p className="text-red-400 text-xs bg-red-400/5 border border-red-400/10 rounded px-3 py-2 mt-2">⚠ {fetchError}</p>
                                            )}

                                            {fetchSuccess && (
                                                <p className="text-teal text-xs bg-teal/5 border border-teal/10 rounded px-3 py-2 mt-2">✓ {fetchSuccess}</p>
                                            )}
                                        </div>
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
                                                <div className="flex items-center justify-between">
                                                    <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">OpenAI API Key</p>
                                                    <button
                                                        type="button"
                                                        onClick={() => testConnection('openai')}
                                                        disabled={testResults.openai?.loading}
                                                        className="text-[10px] font-mono uppercase tracking-wider text-teal/70 hover:text-teal transition-colors disabled:opacity-40"
                                                    >
                                                        {testResults.openai?.loading ? 'Testing...' : 'Test Connection'}
                                                    </button>
                                                </div>
                                                <input
                                                    type="password"
                                                    value={data.settings.openai_key?.value || ''}
                                                    onChange={(e) => handleSettingChange('openai_key', e.target.value)}
                                                    placeholder="sk-..."
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                                {testResults.openai && !testResults.openai.loading && (
                                                    <p className={`text-xs ${testResults.openai.success ? 'text-green-400' : 'text-red-400'}`}>
                                                        {testResults.openai.success ? '✓' : '✗'} {testResults.openai.message}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <div className="flex items-center justify-between">
                                                    <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">OpenRouter API Key</p>
                                                    <button
                                                        type="button"
                                                        onClick={() => testConnection('openrouter')}
                                                        disabled={testResults.openrouter?.loading}
                                                        className="text-[10px] font-mono uppercase tracking-wider text-teal/70 hover:text-teal transition-colors disabled:opacity-40"
                                                    >
                                                        {testResults.openrouter?.loading ? 'Testing...' : 'Test Connection'}
                                                    </button>
                                                </div>
                                                <input
                                                    type="password"
                                                    value={data.settings.openrouter_key?.value || ''}
                                                    onChange={(e) => handleSettingChange('openrouter_key', e.target.value)}
                                                    placeholder="sk-or-v1-..."
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                                {testResults.openrouter && !testResults.openrouter.loading && (
                                                    <p className={`text-xs ${testResults.openrouter.success ? 'text-green-400' : 'text-red-400'}`}>
                                                        {testResults.openrouter.success ? '✓' : '✗'} {testResults.openrouter.message}
                                                    </p>
                                                )}
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
                                            value={data.settings.platform_name?.value || ''}
                                            onChange={(e) => handleSettingChange('platform_name', e.target.value)}
                                            className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'finance' && (
                            <div className="space-y-10 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div>
                                    <h2 className="text-2xl font-display mb-2 text-teal">Economic Guardrails</h2>
                                    <p className="text-white/40 text-xs italic">Configure fees, commissions, and salary ranges.</p>
                                </div>

                                {/* Fee Structure */}
                                <div className="space-y-6">
                                    <h3 className="font-display text-lg text-white/80">Fee Structure</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Service Fee (%)</p>
                                            <div className="relative">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    value={data.settings.service_fee_percentage?.value || ''}
                                                    onChange={(e) => handleSettingChange('service_fee_percentage', e.target.value)}
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                                <span className="absolute right-6 top-1/2 -translate-y-1/2 text-white/20 font-mono">%</span>
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Matching Fee (₦)</p>
                                            <div className="relative">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={data.settings.matching_fee_amount?.value || ''}
                                                    onChange={(e) => handleSettingChange('matching_fee_amount', e.target.value)}
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                                <span className="absolute right-6 top-1/2 -translate-y-1/2 text-white/20 font-mono">₦</span>
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">NIN Verification Fee (₦)</p>
                                            <div className="relative">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={data.settings.nin_verification_fee?.value || ''}
                                                    onChange={(e) => handleSettingChange('nin_verification_fee', e.target.value)}
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                                <span className="absolute right-6 top-1/2 -translate-y-1/2 text-white/20 font-mono">₦</span>
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Standalone Verification Fee (₦)</p>
                                            <div className="relative">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={data.settings.standalone_verification_fee?.value || ''}
                                                    onChange={(e) => handleSettingChange('standalone_verification_fee', e.target.value)}
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                                <span className="absolute right-6 top-1/2 -translate-y-1/2 text-white/20 font-mono">₦</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Commission Settings */}
                                <div className="pt-8 border-t border-white/5 space-y-6">
                                    <h3 className="font-display text-lg text-white/80">Commission Settings</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Commission Type</p>
                                            <select
                                                value={data.settings.commission_type?.value || 'percentage'}
                                                onChange={(e) => handleSettingChange('commission_type', e.target.value)}
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none appearance-none"
                                            >
                                                <option value="percentage">Percentage</option>
                                                <option value="fixed">Fixed Amount</option>
                                                <option value="hybrid">Hybrid (Both)</option>
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Commission Percentage (%)</p>
                                            <div className="relative">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    value={data.settings.commission_percent?.value || ''}
                                                    onChange={(e) => handleSettingChange('commission_percent', e.target.value)}
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                                <span className="absolute right-6 top-1/2 -translate-y-1/2 text-white/20 font-mono">%</span>
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Fixed Commission (₦)</p>
                                            <div className="relative">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={data.settings.commission_fixed_amount?.value || ''}
                                                    onChange={(e) => handleSettingChange('commission_fixed_amount', e.target.value)}
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                                <span className="absolute right-6 top-1/2 -translate-y-1/2 text-white/20 font-mono">₦</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Salary Range */}
                                <div className="pt-8 border-t border-white/5 space-y-6">
                                    <h3 className="font-display text-lg text-white/80">Salary Range Limits</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Minimum Salary (₦)</p>
                                            <div className="relative">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={data.settings.min_salary?.value || ''}
                                                    onChange={(e) => handleSettingChange('min_salary', e.target.value)}
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                                <span className="absolute right-6 top-1/2 -translate-y-1/2 text-white/20 font-mono">₦</span>
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Maximum Salary (₦)</p>
                                            <div className="relative">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={data.settings.max_salary?.value || ''}
                                                    onChange={(e) => handleSettingChange('max_salary', e.target.value)}
                                                    className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                                />
                                                <span className="absolute right-6 top-1/2 -translate-y-1/2 text-white/20 font-mono">₦</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'payment' && (
                            <div className="space-y-10 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div>
                                    <h2 className="text-2xl font-display mb-2 text-teal">Payment Gateways</h2>
                                    <p className="text-white/40 text-xs italic">Configure payment processor credentials and defaults.</p>
                                </div>

                                {/* Default Gateway */}
                                <div className="space-y-4">
                                    <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Default Payment Gateway</p>
                                    <select
                                        value={data.settings.default_payment_gateway?.value || 'paystack'}
                                        onChange={(e) => handleSettingChange('default_payment_gateway', e.target.value)}
                                        className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none appearance-none"
                                    >
                                        <option value="paystack">Paystack</option>
                                        <option value="flutterwave">Flutterwave</option>
                                    </select>
                                </div>

                                {/* Paystack Settings */}
                                <div className="pt-8 border-t border-white/5 space-y-6">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-display text-lg text-white/80">Paystack Configuration</h3>
                                        <span className="text-[10px] font-mono text-copper uppercase bg-copper/10 px-2 py-0.5 rounded tracking-widest">Encrypted</span>
                                    </div>
                                    <div className="grid grid-cols-1 gap-6">
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Public Key</p>
                                            <input
                                                type="password"
                                                value={data.settings.paystack_public_key?.value || ''}
                                                onChange={(e) => handleSettingChange('paystack_public_key', e.target.value)}
                                                placeholder="pk_test_..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Secret Key</p>
                                            <input
                                                type="password"
                                                value={data.settings.paystack_secret_key?.value || ''}
                                                onChange={(e) => handleSettingChange('paystack_secret_key', e.target.value)}
                                                placeholder="sk_test_..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Base URL</p>
                                            <input
                                                type="url"
                                                value={data.settings.paystack_base_url?.value || ''}
                                                onChange={(e) => handleSettingChange('paystack_base_url', e.target.value)}
                                                placeholder="https://api.paystack.co"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Flutterwave Settings */}
                                <div className="pt-8 border-t border-white/5 space-y-6">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-display text-lg text-white/80">Flutterwave Configuration</h3>
                                        <span className="text-[10px] font-mono text-copper uppercase bg-copper/10 px-2 py-0.5 rounded tracking-widest">Encrypted</span>
                                    </div>
                                    <div className="grid grid-cols-1 gap-6">
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Public Key</p>
                                            <input
                                                type="password"
                                                value={data.settings.flutterwave_public_key?.value || ''}
                                                onChange={(e) => handleSettingChange('flutterwave_public_key', e.target.value)}
                                                placeholder="FLWPUBK_TEST-..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Secret Key</p>
                                            <input
                                                type="password"
                                                value={data.settings.flutterwave_secret_key?.value || ''}
                                                onChange={(e) => handleSettingChange('flutterwave_secret_key', e.target.value)}
                                                placeholder="FLWSECK_TEST-..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Encryption Key</p>
                                            <input
                                                type="password"
                                                value={data.settings.flutterwave_encryption_key?.value || ''}
                                                onChange={(e) => handleSettingChange('flutterwave_encryption_key', e.target.value)}
                                                placeholder="..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Base URL</p>
                                            <input
                                                type="url"
                                                value={data.settings.flutterwave_base_url?.value || ''}
                                                onChange={(e) => handleSettingChange('flutterwave_base_url', e.target.value)}
                                                placeholder="https://api.flutterwave.com/v3"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'verification' && (
                            <div className="space-y-10 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div>
                                    <h2 className="text-2xl font-display mb-2 text-teal">Identity Verification</h2>
                                    <p className="text-white/40 text-xs italic">Configure QoreID for NIN and background verification.</p>
                                </div>

                                <div className="space-y-6">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-display text-lg text-white/80">QoreID Configuration</h3>
                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => testConnection('qoreid')}
                                                disabled={testResults.qoreid?.loading}
                                                className="text-[10px] font-mono uppercase tracking-wider text-teal/70 hover:text-teal transition-colors disabled:opacity-40"
                                            >
                                                {testResults.qoreid?.loading ? 'Testing...' : 'Test Connection'}
                                            </button>
                                            <span className="text-[10px] font-mono text-copper uppercase bg-copper/10 px-2 py-0.5 rounded tracking-widest">Encrypted</span>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-1 gap-6">
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Client ID</p>
                                            <input
                                                type="password"
                                                value={data.settings.qoreid_client_id?.value || ''}
                                                onChange={(e) => handleSettingChange('qoreid_client_id', e.target.value)}
                                                placeholder="QoreID Client ID..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Client Secret</p>
                                            <input
                                                type="password"
                                                value={data.settings.qoreid_client_secret?.value || ''}
                                                onChange={(e) => handleSettingChange('qoreid_client_secret', e.target.value)}
                                                placeholder="QoreID Client Secret..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Base URL</p>
                                            <input
                                                type="url"
                                                value={data.settings.qoreid_base_url?.value || ''}
                                                onChange={(e) => handleSettingChange('qoreid_base_url', e.target.value)}
                                                placeholder="https://api.qoreid.com"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        {testResults.qoreid && !testResults.qoreid.loading && (
                                            <p className={`text-xs ${testResults.qoreid.success ? 'text-green-400' : 'text-red-400'}`}>
                                                {testResults.qoreid.success ? '✓' : '✗'} {testResults.qoreid.message}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'sms' && (
                            <div className="space-y-10 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div>
                                    <h2 className="text-2xl font-display mb-2 text-teal">SMS Gateway</h2>
                                    <p className="text-white/40 text-xs italic">Configure Termii for SMS notifications and OTP.</p>
                                </div>

                                <div className="space-y-6">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-display text-lg text-white/80">Termii Configuration</h3>
                                        <span className="text-[10px] font-mono text-copper uppercase bg-copper/10 px-2 py-0.5 rounded tracking-widest">Encrypted</span>
                                    </div>
                                    <div className="grid grid-cols-1 gap-6">
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">API Key</p>
                                            <input
                                                type="password"
                                                value={data.settings.termii_api_key?.value || ''}
                                                onChange={(e) => handleSettingChange('termii_api_key', e.target.value)}
                                                placeholder="TL..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Sender ID</p>
                                            <input
                                                type="text"
                                                value={data.settings.termii_sender_id?.value || ''}
                                                onChange={(e) => handleSettingChange('termii_sender_id', e.target.value)}
                                                placeholder="MaidsNG"
                                                maxLength="11"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                            <p className="text-white/30 text-[10px]">Maximum 11 characters</p>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">API Base URL</p>
                                            <input
                                                type="url"
                                                value={data.settings.termii_url?.value || ''}
                                                onChange={(e) => handleSettingChange('termii_url', e.target.value)}
                                                placeholder="https://api.ng.termii.com/api"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'api' && (
                            <div className="space-y-10 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div>
                                    <h2 className="text-2xl font-display mb-2 text-teal">API & Developer Access</h2>
                                    <p className="text-white/40 text-xs italic">Generate personal access tokens and view system documentation.</p>
                                </div>

                                <div className="p-8 bg-teal/5 border border-teal/10 rounded-brand-xl flex items-center justify-between">
                                    <div className="space-y-1">
                                        <h3 className="font-display text-lg text-white/90">API Documentation</h3>
                                        <p className="text-white/40 text-xs">View all available endpoints, parameters, and request examples.</p>
                                    </div>
                                    <a 
                                        href={route('admin.api_docs')} 
                                        className="px-6 py-3 bg-teal text-black font-mono text-[10px] uppercase tracking-widest font-bold rounded-brand-lg hover:bg-teal-light transition-all shadow-lg shadow-teal/20"
                                    >
                                        Open API Docs
                                    </a>
                                </div>

                                <div className="pt-8 border-t border-white/5 space-y-6">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-display text-lg text-white/80">Bearer Token Generation</h3>
                                        <span className="text-[10px] font-mono text-amber-400 uppercase bg-amber-400/10 px-2 py-0.5 rounded tracking-widest">Master Key</span>
                                    </div>
                                    
                                    <p className="text-white/40 text-sm leading-relaxed">
                                        Generate a permanent API token for integration with external agents or custom tools. 
                                        <span className="text-amber-400/80 font-bold"> Warning:</span> Keep these keys secure. They grant full administrative access to the platform's standardized API endpoints.
                                    </p>

                                    <div className="flex items-end gap-4">
                                        <div className="flex-1 space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Token Label (e.g., 'Agent Scout')</p>
                                            <input 
                                                type="text"
                                                id="token-name"
                                                placeholder="Enter a descriptive name..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <button 
                                            type="button"
                                            onClick={async () => {
                                                const name = document.getElementById('token-name').value;
                                                if (!name) return alert('Please enter a label for the token.');
                                                
                                                try {
                                                    const res = await axios.post(route('admin.settings.api-token'), { name });
                                                    if (res.data.success) {
                                                        const token = res.data.token;
                                                        setGeneratedToken(token);
                                                        document.getElementById('token-name').value = '';
                                                    }
                                                } catch (err) {
                                                    alert('Failed to generate token. ' + (err.response?.data?.message || err.message));
                                                }
                                            }}
                                            className="h-[58px] px-8 bg-white/5 border border-white/10 rounded-brand-lg text-white font-mono text-[10px] uppercase tracking-widest hover:bg-white/10 transition-all"
                                        >
                                            Generate Key
                                        </button>
                                    </div>
                                    
                                    {generatedToken && (
                                        <div className="mt-4 p-6 bg-teal/10 border border-teal/30 rounded-brand-lg relative">
                                            <div className="absolute top-0 right-0 p-2">
                                                <button 
                                                    onClick={() => {
                                                        navigator.clipboard.writeText(generatedToken);
                                                        alert('Token copied to clipboard!');
                                                    }}
                                                    className="text-[10px] uppercase font-mono tracking-widest text-teal hover:text-white transition-colors"
                                                >
                                                    Copy Token
                                                </button>
                                            </div>
                                            <p className="text-teal font-bold mb-2">API Token Generated Successfully</p>
                                            <p className="text-white/60 text-sm mb-4">Please copy this token now. You will not be able to see it again.</p>
                                            <div className="bg-[#0a0a0b] p-4 rounded border border-white/10 font-mono text-sm text-amber-400 break-all select-all">
                                                {generatedToken}
                                            </div>
                                        </div>
                                    )}

                                    <div className="pt-4">
                                        <button 
                                            type="button"
                                            onClick={async () => {
                                                if (!confirm('Are you sure? This will immediately invalidate ALL your existing API tokens.')) return;
                                                try {
                                                    const res = await axios.post(route('admin.settings.revoke-tokens'));
                                                    if (res.data.success) alert(res.data.message);
                                                } catch (err) {
                                                    alert('Failed to revoke tokens.');
                                                }
                                            }}
                                            className="text-[10px] font-mono uppercase tracking-widest text-red-400/60 hover:text-red-400 transition-colors"
                                        >
                                            Revoke All My Active Tokens
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}
                        {activeTab === 'mcp' && (
                            <div className="space-y-10 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div>
                                    <h2 className="text-2xl font-display mb-2 text-teal">MCP Server Manager</h2>
                                    <p className="text-white/40 text-xs italic">Register external MCP servers, monitor their status, and get connection snippets.</p>
                                </div>

                                {/* Add / Edit Form */}
                                <div className="bg-white/5 border border-white/10 rounded-brand-xl p-8 space-y-6">
                                    <h3 className="font-display text-lg text-white/80">{mcpEditId ? 'Edit Server' : 'Add New MCP Server'}</h3>
                                    {mcpMsg && (
                                        <p className={`text-xs px-4 py-2 rounded border ${mcpMsg.type === 'success' ? 'text-teal border-teal/20 bg-teal/5' : 'text-red-400 border-red-400/20 bg-red-400/5'}`}>
                                            {mcpMsg.text}
                                        </p>
                                    )}
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Server Name</p>
                                            <input
                                                type="text"
                                                value={mcpForm.name}
                                                onChange={e => setMcpForm(f => ({ ...f, name: e.target.value }))}
                                                placeholder="e.g. Maids Primary"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-white focus:border-teal/50 outline-none text-sm"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Base URL</p>
                                            <input
                                                type="url"
                                                value={mcpForm.base_url}
                                                onChange={e => setMcpForm(f => ({ ...f, base_url: e.target.value }))}
                                                placeholder="https://api.maids.ng"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-white focus:border-teal/50 outline-none text-sm"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Auth Token (optional)</p>
                                            <input
                                                type="password"
                                                value={mcpForm.auth_token}
                                                onChange={e => setMcpForm(f => ({ ...f, auth_token: e.target.value }))}
                                                placeholder="Bearer token..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-white focus:border-teal/50 outline-none text-sm"
                                            />
                                        </div>
                                    </div>
                                    <div className="flex gap-3">
                                        <button
                                            type="button"
                                            disabled={mcpLoading}
                                            onClick={async () => {
                                                if (!mcpForm.name || !mcpForm.base_url) {
                                                    setMcpMsg({ type: 'error', text: 'Name and Base URL are required.' });
                                                    return;
                                                }
                                                setMcpLoading(true);
                                                setMcpMsg(null);
                                                try {
                                                    const url = mcpEditId
                                                        ? route('admin.mcp.update', mcpEditId)
                                                        : route('admin.mcp.store');
                                                    const method = mcpEditId ? 'put' : 'post';
                                                    const res = await axios[method](url, mcpForm);
                                                    setMcpMsg({ type: 'success', text: mcpEditId ? 'Server updated.' : 'Server added.' });
                                                    // Refresh server list from server response or reload
                                                    const listRes = await axios.get(route('admin.mcp.index'), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                                                    if (listRes.data?.servers) setMcpServers(listRes.data.servers);
                                                    setMcpForm({ name: '', base_url: '', auth_token: '' });
                                                    setMcpEditId(null);
                                                } catch (e) {
                                                    setMcpMsg({ type: 'error', text: e.response?.data?.message || e.message });
                                                } finally {
                                                    setMcpLoading(false);
                                                }
                                            }}
                                            className="px-8 py-3 bg-teal text-black font-mono text-[10px] uppercase tracking-widest font-bold rounded-brand-lg hover:brightness-110 transition-all disabled:opacity-50"
                                        >
                                            {mcpLoading ? 'Saving...' : mcpEditId ? 'Update Server' : 'Add Server'}
                                        </button>
                                        {mcpEditId && (
                                            <button
                                                type="button"
                                                onClick={() => { setMcpEditId(null); setMcpForm({ name: '', base_url: '', auth_token: '' }); setMcpMsg(null); }}
                                                className="px-6 py-3 bg-white/5 border border-white/10 text-white/60 font-mono text-[10px] uppercase tracking-widest rounded-brand-lg hover:bg-white/10 transition-all"
                                            >
                                                Cancel
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {/* Server List */}
                                <div className="space-y-4">
                                    <h3 className="font-display text-lg text-white/80">Registered Servers ({mcpServers.length})</h3>
                                    {mcpServers.length === 0 && (
                                        <div className="text-center py-12 text-white/20 text-sm font-mono">No MCP servers registered yet.</div>
                                    )}
                                    {mcpServers.map(srv => (
                                        <div key={srv.id} className="bg-[#0e0e10] border border-white/5 rounded-brand-xl p-6 space-y-4">
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-3">
                                                        <span className="font-display text-white text-base">{srv.name}</span>
                                                        <span className={`text-[10px] font-mono uppercase tracking-widest px-2 py-0.5 rounded ${
                                                            srv.status === 'online' ? 'bg-teal/10 text-teal border border-teal/20'
                                                            : srv.status === 'offline' ? 'bg-red-400/10 text-red-400 border border-red-400/20'
                                                            : 'bg-white/5 text-white/30 border border-white/10'
                                                        }`}>
                                                            {srv.status || 'unknown'}
                                                        </span>
                                                    </div>
                                                    <p className="text-white/40 text-xs font-mono">{srv.base_url}</p>
                                                    <p className="text-white/20 text-[10px] font-mono">
                                                        Last ping: {srv.last_ping_at ? new Date(srv.last_ping_at).toLocaleString() : '—'}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2 flex-shrink-0">
                                                    <button
                                                        type="button"
                                                        onClick={async () => {
                                                            setMcpTestResult(prev => ({ ...prev, [srv.id]: { loading: true } }));
                                                            try {
                                                                const res = await axios.post(route('admin.mcp.test', srv.id));
                                                                setMcpTestResult(prev => ({ ...prev, [srv.id]: res.data }));
                                                                if (res.data.usage) setMcpSnippet(prev => ({ ...prev, [srv.id]: res.data.usage }));
                                                                // Update status in list
                                                                setMcpServers(prev => prev.map(s => s.id === srv.id ? { ...s, status: res.data.success ? 'online' : 'offline', last_ping_at: new Date().toISOString() } : s));
                                                            } catch(e) {
                                                                setMcpTestResult(prev => ({ ...prev, [srv.id]: { success: false, message: e.message } }));
                                                            }
                                                        }}
                                                        className="text-[10px] font-mono uppercase tracking-widest px-4 py-2 bg-teal/5 border border-teal/20 text-teal rounded-brand-lg hover:bg-teal/10 transition-all"
                                                    >
                                                        {mcpTestResult[srv.id]?.loading ? 'Pinging...' : 'Ping & Snippet'}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => { setMcpEditId(srv.id); setMcpForm({ name: srv.name, base_url: srv.base_url, auth_token: '' }); setMcpMsg(null); setActiveTab('mcp'); window.scrollTo({ top: 0, behavior: 'smooth' }); }}
                                                        className="text-[10px] font-mono uppercase tracking-widest px-4 py-2 bg-white/5 border border-white/10 text-white/50 rounded-brand-lg hover:bg-white/10 hover:text-white transition-all"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={async () => {
                                                            if (!confirm(`Remove "${srv.name}"?`)) return;
                                                            await axios.delete(route('admin.mcp.destroy', srv.id));
                                                            setMcpServers(prev => prev.filter(s => s.id !== srv.id));
                                                        }}
                                                        className="text-[10px] font-mono uppercase tracking-widest px-4 py-2 bg-red-400/5 border border-red-400/10 text-red-400/60 rounded-brand-lg hover:text-red-400 hover:bg-red-400/10 transition-all"
                                                    >
                                                        Remove
                                                    </button>
                                                </div>
                                            </div>

                                            {/* Test result */}
                                            {mcpTestResult[srv.id] && !mcpTestResult[srv.id].loading && (
                                                <div className={`text-xs px-4 py-2 rounded border ${
                                                    mcpTestResult[srv.id].success ? 'text-teal bg-teal/5 border-teal/10' : 'text-red-400 bg-red-400/5 border-red-400/10'
                                                }`}>
                                                    {mcpTestResult[srv.id].success ? '✓ Online' : `✗ ${mcpTestResult[srv.id].message || 'Connection failed'}`}
                                                </div>
                                            )}

                                            {/* Usage snippet */}
                                            {mcpSnippet[srv.id] && (
                                                <div className="space-y-2">
                                                    <div className="flex items-center justify-between">
                                                        <p className="text-[10px] font-mono uppercase tracking-widest text-white/30">Connection Snippet (PHP)</p>
                                                        <button
                                                            type="button"
                                                            onClick={() => navigator.clipboard.writeText(mcpSnippet[srv.id])}
                                                            className="text-[10px] font-mono uppercase tracking-widest text-teal/60 hover:text-teal transition-colors"
                                                        >
                                                            Copy
                                                        </button>
                                                    </div>
                                                    <pre className="bg-black/40 border border-white/5 rounded-brand-lg p-4 text-teal/80 font-mono text-xs overflow-x-auto whitespace-pre-wrap break-all">{mcpSnippet[srv.id]}</pre>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>

                                {/* Usage Guide */}
                                <div className="bg-white/5 border border-white/10 rounded-brand-xl p-8 space-y-4">
                                    <h3 className="font-display text-lg text-white/80">Usage Guide</h3>
                                    <div className="space-y-3 text-white/40 text-xs leading-relaxed">
                                        <p>• <span className="text-white/60">Register a server</span> by entering its name, base URL, and optional Bearer token above.</p>
                                        <p>• Use <span className="text-teal/80">Ping & Snippet</span> to test connectivity. A successful ping sets the server status to <span className="text-teal">Online</span> and updates the Last Ping timestamp.</p>
                                        <p>• The <span className="text-white/60">Connection Snippet</span> is a ready-to-use PHP example you can drop into any custom integration.</p>
                                        <p>• <span className="text-white/60">Last ping</span> is automatically updated every time the MCP service makes a successful tool call to that server.</p>
                                        <p>• MCP servers with <span className="text-red-400">Offline</span> status indicate the server could not be reached — check credentials and network connectivity.</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'email' && (
                            <div className="space-y-10 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div>
                                    <h2 className="text-2xl font-display mb-2 text-teal">Email Configuration</h2>
                                    <p className="text-white/40 text-xs italic">Configure SMTP settings for transactional emails.</p>
                                </div>

                                {/* Mailer Settings */}
                                <div className="space-y-6">
                                    <h3 className="font-display text-lg text-white/80">SMTP Configuration</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Mail Driver</p>
                                            <select
                                                value={data.settings.mail_mailer?.value || 'smtp'}
                                                onChange={(e) => handleSettingChange('mail_mailer', e.target.value)}
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none appearance-none"
                                            >
                                                <option value="smtp">SMTP</option>
                                                <option value="sendmail">Sendmail</option>
                                                <option value="mailgun">Mailgun</option>
                                                <option value="postmark">Postmark</option>
                                                <option value="ses">Amazon SES</option>
                                                <option value="log">Log (Testing)</option>
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Mail Host</p>
                                            <input
                                                type="text"
                                                value={data.settings.mail_host?.value || ''}
                                                onChange={(e) => handleSettingChange('mail_host', e.target.value)}
                                                placeholder="smtp.mailgun.org"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Mail Port</p>
                                            <input
                                                type="number"
                                                value={data.settings.mail_port?.value || ''}
                                                onChange={(e) => handleSettingChange('mail_port', e.target.value)}
                                                placeholder="587"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Mail Username</p>
                                            <input
                                                type="text"
                                                value={data.settings.mail_username?.value || ''}
                                                onChange={(e) => handleSettingChange('mail_username', e.target.value)}
                                                placeholder="postmaster@..."
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2 md:col-span-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Mail Password</p>
                                            <input
                                                type="password"
                                                value={data.settings.mail_password?.value || ''}
                                                onChange={(e) => handleSettingChange('mail_password', e.target.value)}
                                                placeholder="••••••••"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">Encryption</p>
                                            <select
                                                value={data.settings.mail_encryption?.value || 'tls'}
                                                onChange={(e) => handleSettingChange('mail_encryption', e.target.value)}
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none appearance-none"
                                            >
                                                <option value="tls">TLS</option>
                                                <option value="ssl">SSL</option>
                                                <option value="">None</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {/* From Address */}
                                <div className="pt-8 border-t border-white/5 space-y-6">
                                    <h3 className="font-display text-lg text-white/80">Sender Information</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">From Address</p>
                                            <input
                                                type="email"
                                                value={data.settings.mail_from_address?.value || ''}
                                                onChange={(e) => handleSettingChange('mail_from_address', e.target.value)}
                                                placeholder="noreply@maids.ng"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/40">From Name</p>
                                            <input
                                                type="text"
                                                value={data.settings.mail_from_name?.value || ''}
                                                onChange={(e) => handleSettingChange('mail_from_name', e.target.value)}
                                                placeholder="Maids.ng"
                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-6 py-4 text-white focus:border-teal/50 outline-none"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                        {activeTab === 'deployment' && (
                            <div className="space-y-10 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div>
                                    <h2 className="text-2xl font-display mb-2 text-teal">Background Tasks & Cron Jobs</h2>
                                    <p className="text-white/40 text-xs italic">Essential configuration for automated matching and notifications.</p>
                                </div>

                                <div className="bg-amber-400/5 border border-amber-400/20 rounded-brand-xl p-6 space-y-4">
                                    <div className="flex items-center gap-3 text-amber-400">
                                        <span className="text-2xl">⚠️</span>
                                        <h3 className="font-display text-lg uppercase tracking-wider">Critical Setup Required</h3>
                                    </div>
                                    <p className="text-white/60 text-sm leading-relaxed">
                                        For the platform to function (AI matching, salary reminders, SMS notifications), you must configure **Cron Jobs** in your hosting control panel.
                                    </p>
                                </div>

                                <div className="space-y-8">
                                    <div className="space-y-4">
                                        <h3 className="font-display text-lg text-white/80">1. Task Scheduler</h3>
                                        <p className="text-white/40 text-xs">Run this command every minute to trigger scheduled AI analysis and reminders.</p>
                                        <div className="relative group">
                                            <code className="block bg-black/40 border border-white/5 rounded-brand-lg p-6 font-mono text-sm text-teal/90 break-all">
                                                {'* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1'}
                                            </code>
                                            <button
                                                type="button"
                                                onClick={() => navigator.clipboard.writeText('* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1')}
                                                className="absolute top-4 right-4 text-[10px] font-mono uppercase tracking-widest text-white/20 hover:text-teal transition-colors"
                                            >
                                                Copy Command
                                            </button>
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        <h3 className="font-display text-lg text-white/80">2. Queue Worker (Option A)</h3>
                                        <p className="text-white/40 text-xs">Run this every minute to process pending emails and matching calculations on shared hosting.</p>
                                        <div className="relative group">
                                            <code className="block bg-black/40 border border-white/5 rounded-brand-lg p-6 font-mono text-sm text-teal/90 break-all">
                                                {'* * * * * cd /path/to/project && php artisan queue:work --stop-when-empty >> /dev/null 2>&1'}
                                            </code>
                                            <button
                                                type="button"
                                                onClick={() => navigator.clipboard.writeText('* * * * * cd /path/to/project && php artisan queue:work --stop-when-empty >> /dev/null 2>&1')}
                                                className="absolute top-4 right-4 text-[10px] font-mono uppercase tracking-widest text-white/20 hover:text-teal transition-colors"
                                            >
                                                Copy Command
                                            </button>
                                        </div>
                                    </div>

                                    <div className="pt-8 border-t border-white/5">
                                        <h3 className="font-display text-lg text-white/80 mb-4">Environment Status</h3>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div className="bg-white/5 rounded-brand-lg p-4 flex items-center justify-between">
                                                <span className="text-white/40 text-xs uppercase font-mono">Queue Connection</span>
                                                <span className="text-teal font-mono text-sm">Database</span>
                                            </div>
                                            <div className="bg-white/5 rounded-brand-lg p-4 flex items-center justify-between">
                                                <span className="text-white/40 text-xs uppercase font-mono">Scheduler Status</span>
                                                <span className="text-teal font-mono text-sm">Active</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'scripts' && (
                            <div className="space-y-12 animate-in fade-in slide-in-from-right-4 duration-500">
                                {/* Header */}
                                <div className="space-y-2">
                                    <h2 className="text-2xl font-display mb-2 text-teal">Scripts & Pixels</h2>
                                    <p className="text-white/40 text-xs leading-relaxed">
                                        Paste tracking snippets for Google Tag Manager, GA4, Meta Pixel, or any custom third-party scripts.
                                        Changes apply instantly after saving — no code deployment needed.
                                    </p>
                                    <div className="flex flex-wrap gap-2 pt-1">
                                        {[
                                            { label: 'Head', desc: 'Loads first — ideal for GTM, GA4, Meta Pixel initialisation' },
                                            { label: 'Body (Opening)', desc: 'Immediately after <body> — required for GTM & Meta noscript fallbacks' },
                                            { label: 'Footer', desc: 'Before </body> — ideal for live chat widgets, deferred scripts' },
                                        ].map(b => (
                                            <span key={b.label} title={b.desc} className="text-[10px] font-mono uppercase tracking-widest px-2 py-1 bg-white/5 border border-white/10 rounded text-white/40 cursor-help">
                                                {b.label}
                                            </span>
                                        ))}
                                    </div>
                                </div>

                                {/* ── Public Frontend ── */}
                                {[{ suffix: 'frontend', label: '🌍 Public Frontend', desc: 'Injected on all public pages (homepage, maid search, onboarding, etc.)' },
                                  { suffix: 'member', label: '👤 Member Area', desc: 'Injected on employer & maid dashboard pages only.' }
                                ].map(area => (
                                    <div key={area.suffix} className="space-y-8">
                                        <div className="flex items-center gap-4">
                                            <h3 className="font-display text-xl text-white">{area.label}</h3>
                                            <span className="text-[10px] font-mono text-white/30 bg-white/5 border border-white/10 px-2 py-1 rounded tracking-widest uppercase flex-shrink-0">{area.suffix}</span>
                                        </div>
                                        <p className="text-white/30 text-xs -mt-4">{area.desc}</p>

                                        {/* Google */}
                                        <div className="space-y-6 pt-4 border-t border-white/5">
                                            <div className="flex items-center gap-3">
                                                <div className="w-6 h-6 rounded-full bg-gradient-to-br from-blue-500 to-green-400 flex items-center justify-center text-[10px] font-bold text-white">G</div>
                                                <h4 className="font-display text-base text-white/90">Google — GTM / GA4</h4>
                                                <span className="text-[10px] font-mono text-blue-400 bg-blue-400/10 px-2 py-0.5 rounded tracking-widest uppercase">Google Tag Manager · Analytics 4</span>
                                            </div>
                                            <div className="grid grid-cols-1 gap-4">
                                                {[
                                                    { pos: 'head', label: 'Head Script', placeholder: `<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){...})(window,document,'script','dataLayer','GTM-XXXXXXX');</script>\n<!-- End Google Tag Manager -->\n\n<!-- OR for GA4 directly: -->\n<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>\n<script>\n  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag('js', new Date());\n  gtag('config', 'G-XXXXXXXXXX');\n</script>` },
                                                    { pos: 'body', label: 'Body (Opening) — GTM noscript', placeholder: `<!-- Google Tag Manager (noscript) -->\n<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXXX" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>\n<!-- End Google Tag Manager (noscript) -->` },
                                                    { pos: 'footer', label: 'Footer Script', placeholder: '<!-- Footer Google scripts (usually not needed for GTM/GA4) -->' },
                                                ].map(({ pos, label, placeholder }) => {
                                                    const key = `script_google_${pos}_${area.suffix}`;
                                                    return (
                                                        <div key={key} className="space-y-2">
                                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/30">{label}</p>
                                                            <textarea
                                                                value={data.settings[key]?.value || ''}
                                                                onChange={e => handleSettingChange(key, e.target.value)}
                                                                placeholder={placeholder}
                                                                rows={pos === 'head' ? 8 : 4}
                                                                spellCheck={false}
                                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-green-300/90 focus:border-blue-500/40 outline-none font-mono text-xs resize-y leading-relaxed placeholder-white/10"
                                                            />
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        {/* Meta */}
                                        <div className="space-y-6 pt-6 border-t border-white/5">
                                            <div className="flex items-center gap-3">
                                                <div className="w-6 h-6 rounded-full bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center text-[10px] font-bold text-white">f</div>
                                                <h4 className="font-display text-base text-white/90">Meta — Facebook Pixel</h4>
                                                <span className="text-[10px] font-mono text-blue-500 bg-blue-500/10 px-2 py-0.5 rounded tracking-widest uppercase">Pixel · Conversions API</span>
                                            </div>
                                            <div className="grid grid-cols-1 gap-4">
                                                {[
                                                    { pos: 'head', label: 'Head Script', placeholder: `<!-- Meta Pixel Code -->\n<script>\n!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){...};\nf._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';...}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');\nfbq('init', 'YOUR_PIXEL_ID');\nfbq('track', 'PageView');\n</script>\n<!-- End Meta Pixel Code -->` },
                                                    { pos: 'body', label: 'Body (Opening) — noscript fallback', placeholder: `<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=YOUR_PIXEL_ID&ev=PageView&noscript=1"/></noscript>` },
                                                    { pos: 'footer', label: 'Footer Script', placeholder: '<!-- Footer Meta scripts (e.g. event helpers) -->' },
                                                ].map(({ pos, label, placeholder }) => {
                                                    const key = `script_meta_${pos}_${area.suffix}`;
                                                    return (
                                                        <div key={key} className="space-y-2">
                                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/30">{label}</p>
                                                            <textarea
                                                                value={data.settings[key]?.value || ''}
                                                                onChange={e => handleSettingChange(key, e.target.value)}
                                                                placeholder={placeholder}
                                                                rows={pos === 'head' ? 8 : 4}
                                                                spellCheck={false}
                                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-blue-300/90 focus:border-blue-500/40 outline-none font-mono text-xs resize-y leading-relaxed placeholder-white/10"
                                                            />
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        {/* Custom */}
                                        <div className="space-y-6 pt-6 border-t border-white/5">
                                            <div className="flex items-center gap-3">
                                                <div className="w-6 h-6 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-[10px] font-bold text-white">★</div>
                                                <h4 className="font-display text-base text-white/90">Custom Third-Party</h4>
                                                <span className="text-[10px] font-mono text-purple-400 bg-purple-400/10 px-2 py-0.5 rounded tracking-widest uppercase">Hotjar · Intercom · Crisp · etc.</span>
                                            </div>
                                            <div className="grid grid-cols-1 gap-4">
                                                {[
                                                    { pos: 'head', label: 'Head Script (CSS, fonts, custom tags)', placeholder: '<!-- e.g. custom CSS, Hotjar initialisation, Clarity, etc. -->' },
                                                    { pos: 'body', label: 'Body (Opening)', placeholder: '<!-- e.g. noscript fallbacks for custom tools -->' },
                                                    { pos: 'footer', label: 'Footer Script (Live chat widgets, deferred loaders)', placeholder: `<!-- e.g. Intercom, Crisp, Tawk.to, custom analytics -->\n<script>\n  window.intercomSettings = { app_id: "YOUR_APP_ID" };\n</script>\n<script>(function(){var w=window;var ic=w.Intercom;...})()</script>` },
                                                ].map(({ pos, label, placeholder }) => {
                                                    const key = `script_custom_${pos}_${area.suffix}`;
                                                    return (
                                                        <div key={key} className="space-y-2">
                                                            <p className="text-[10px] font-mono uppercase tracking-widest text-white/30">{label}</p>
                                                            <textarea
                                                                value={data.settings[key]?.value || ''}
                                                                onChange={e => handleSettingChange(key, e.target.value)}
                                                                placeholder={placeholder}
                                                                rows={pos === 'footer' ? 8 : 4}
                                                                spellCheck={false}
                                                                className="w-full bg-[#0a0a0b] border border-white/10 rounded-brand-lg px-4 py-3 text-purple-300/90 focus:border-purple-500/40 outline-none font-mono text-xs resize-y leading-relaxed placeholder-white/10"
                                                            />
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    </div>
                                ))}

                                {/* Info callout */}
                                <div className="bg-teal/5 border border-teal/20 rounded-brand-xl p-6 space-y-3">
                                    <div className="flex items-center gap-2 text-teal">
                                        <span className="text-lg">💡</span>
                                        <h4 className="font-mono text-[10px] uppercase tracking-widest font-bold">How Injection Works</h4>
                                    </div>
                                    <ul className="text-white/40 text-xs space-y-1.5 leading-relaxed list-none">
                                        <li>• <span className="text-white/60">Head:</span> Rendered inside <code className="text-teal/80">&lt;head&gt;</code> — best for analytics initialisation.</li>
                                        <li>• <span className="text-white/60">Body (Opening):</span> Immediately after <code className="text-teal/80">&lt;body&gt;</code> opens — required for GTM &amp; Meta Pixel <code className="text-teal/80">&lt;noscript&gt;</code> fallbacks.</li>
                                        <li>• <span className="text-white/60">Footer:</span> Before <code className="text-teal/80">&lt;/body&gt;</code> closes — ideal for deferred widgets like live chat.</li>
                                        <li>• <span className="text-white/60">Frontend vs. Member:</span> Public routes (/, /maids, /onboarding…) use <strong>Frontend</strong> scripts. Employer/Maid dashboards use <strong>Member</strong> scripts.</li>
                                    </ul>
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
