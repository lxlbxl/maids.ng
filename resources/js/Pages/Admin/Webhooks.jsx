import React, { useState, useEffect, useRef } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/table';
import { toast } from 'sonner';
import { Plus, Trash2, Power, Edit, X, ChevronDown, Search, Check, Loader2 } from 'lucide-react';

const defaultFormState = {
    name: '',
    url: '',
    secret: '',
    events: [],
    timeout: 30,
    max_retries: 3,
    is_active: true,
    verify_ssl: true,
};

export default function Webhooks({ auth, webhooks = [], stats = {} }) {
    const [modalOpen, setModalOpen]     = useState(false);
    const [editingWebhook, setEditing]  = useState(null);
    const [availableEvents, setAvailableEvents] = useState([]);
    const [loadingEvents, setLoadingEvents] = useState(true);
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const dropdownRef = useRef(null);

    const { data, setData, post, put, processing, errors, reset } = useForm(defaultFormState);

    useEffect(() => {
        let isMounted = true;
        fetch('/admin/webhooks/events')
            .then(res => {
                if (!res.ok) throw new Error('Failed to fetch events');
                return res.json();
            })
            .then(data => {
                if (isMounted) {
                    setAvailableEvents(data);
                    setLoadingEvents(false);
                }
            })
            .catch(err => {
                console.error("Error fetching webhook events:", err);
                if (isMounted) {
                    setLoadingEvents(false);
                }
            });
        return () => { isMounted = false; };
    }, []);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setDropdownOpen(false);
            }
        };
        if (dropdownOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [dropdownOpen]);

    const openCreate = () => {
        setEditing(null);
        reset();
        setModalOpen(true);
    };

    const openEdit = (wh) => {
        setEditing(wh);
        setData({
            name:       wh.name,
            url:        wh.url,
            secret:     wh.secret || '',
            events:     wh.events || [],
            timeout:    wh.timeout,
            max_retries: wh.max_retries,
            is_active:  wh.is_active,
            verify_ssl: wh.verify_ssl,
        });
        setModalOpen(true);
    };

    const closeModal = () => { 
        setModalOpen(false); 
        setSearchQuery('');
        reset(); 
    };

    const toggleEvent = (id) =>
        setData('events', data.events.includes(id)
            ? data.events.filter(e => e !== id)
            : [...data.events, id]);

    const filteredEvents = availableEvents.filter(ev =>
        ev.label.toLowerCase().includes(searchQuery.toLowerCase()) ||
        ev.id.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (ev.description && ev.description.toLowerCase().includes(searchQuery.toLowerCase()))
    );

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!data.events || data.events.length === 0) {
            toast.error('Validation failed — select at least one event');
            return;
        }
        const opts = {
            onSuccess: () => { toast.success(editingWebhook ? 'Webhook updated' : 'Webhook created'); closeModal(); },
            onError:   () => toast.error('Validation failed — check the form'),
        };
        editingWebhook
            ? put(route('admin.webhooks.update', editingWebhook.id), opts)
            : post(route('admin.webhooks.store'), opts);
    };

    const handleToggle = (wh) =>
        router.post(route('admin.webhooks.toggle', wh.id), {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Status updated'),
        });

    const handleDelete = (wh) => {
        if (!confirm('Delete this webhook?')) return;
        router.delete(route('admin.webhooks.destroy', wh.id), {
            preserveScroll: true,
            onSuccess: () => toast.success('Webhook deleted'),
        });
    };

    return (
        <AdminLayout auth={auth}>
            <Head title="Webhooks" />

            <div className="p-6 space-y-6">

                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Webhooks</h1>
                        <p className="text-gray-400 mt-1 text-sm">
                            Manage outgoing webhook integrations for real-time event notifications.
                        </p>
                    </div>
                    <Button
                        onClick={openCreate}
                        className="bg-teal-600 hover:bg-teal-700 text-white"
                    >
                        <Plus className="w-4 h-4 mr-2" /> NEW WEBHOOK
                    </Button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card className="bg-[#0f1115] border-gray-800 text-white">
                        <CardContent className="p-6">
                            <p className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">TOTAL DELIVERIES</p>
                            <p className="text-3xl font-light">{stats?.total_deliveries ?? 0}</p>
                        </CardContent>
                    </Card>
                    <Card className="bg-[#0f1115] border-gray-800 text-white">
                        <CardContent className="p-6">
                            <p className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">SUCCESS RATE</p>
                            <p className="text-3xl font-light text-yellow-500">{stats?.success_rate ?? '0%'}</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Table */}
                <Card className="bg-[#0f1115] border-gray-800 text-white">
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="border-gray-800 hover:bg-transparent">
                                    <TableHead className="text-gray-400">NAME & URL</TableHead>
                                    <TableHead className="text-gray-400">EVENTS</TableHead>
                                    <TableHead className="text-gray-400">STATUS</TableHead>
                                    <TableHead className="text-gray-400 text-right">ACTIONS</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {webhooks.length === 0 ? (
                                    <TableRow className="border-gray-800 hover:bg-transparent">
                                        <TableCell colSpan={4} className="text-center py-8 text-gray-500">
                                            No webhooks configured yet. Click <strong>NEW WEBHOOK</strong> to add one.
                                        </TableCell>
                                    </TableRow>
                                ) : webhooks.map(wh => (
                                    <TableRow key={wh.id} className="border-gray-800 hover:bg-gray-900/50">
                                        <TableCell>
                                            <div className="font-medium text-gray-200">{wh.name}</div>
                                            <div className="text-xs text-gray-500 mt-1 truncate max-w-xs">{wh.url}</div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {(wh.events || []).map(ev => (
                                                    <Badge key={ev} variant="outline" className="text-[10px] bg-gray-800 border-gray-700 text-gray-300">
                                                        {ev}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge className={wh.is_active
                                                ? 'bg-teal-500/10 text-teal-400 border border-teal-500/20'
                                                : 'bg-gray-500/10 text-gray-400 border border-gray-500/20'}>
                                                {wh.is_active ? 'Active' : 'Disabled'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => handleToggle(wh)}
                                                    className={wh.is_active ? 'text-yellow-500 hover:text-yellow-400 hover:bg-yellow-500/10' : 'text-teal-500 hover:text-teal-400 hover:bg-teal-500/10'}
                                                    title={wh.is_active ? 'Disable' : 'Enable'}>
                                                    <Power className="w-4 h-4" />
                                                </Button>
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(wh)}
                                                    className="text-blue-400 hover:text-blue-300 hover:bg-blue-500/10">
                                                    <Edit className="w-4 h-4" />
                                                </Button>
                                                <Button variant="ghost" size="sm" onClick={() => handleDelete(wh)}
                                                    className="text-red-400 hover:text-red-300 hover:bg-red-500/10">
                                                    <Trash2 className="w-4 h-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {/* Modal */}
            {modalOpen && (
                <>
                    {/* Backdrop */}
                    <div
                        className="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm"
                        onClick={closeModal}
                    />
                    {/* Panel */}
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div className="bg-[#0f1115] border border-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto text-white">
                            {/* Modal Header */}
                            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                                <h2 className="text-lg font-semibold">
                                    {editingWebhook ? 'Edit Webhook' : 'Create Webhook'}
                                </h2>
                                <button onClick={closeModal} className="text-gray-400 hover:text-white transition-colors">
                                    <X className="w-5 h-5" />
                                </button>
                            </div>

                            {/* Modal Body */}
                            <form onSubmit={handleSubmit} className="px-6 py-5 space-y-5">

                                {/* Name */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold text-gray-400 uppercase tracking-wider">Name</Label>
                                    <Input
                                        value={data.name}
                                        onChange={e => setData('name', e.target.value)}
                                        placeholder="e.g. n8n Integration"
                                        className="bg-[#1a1d24] border-gray-700 text-white placeholder-gray-600 focus-visible:ring-teal-500"
                                    />
                                    {errors.name && <p className="text-red-400 text-xs">{errors.name}</p>}
                                </div>

                                {/* URL */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold text-gray-400 uppercase tracking-wider">URL</Label>
                                    <Input
                                        type="url"
                                        value={data.url}
                                        onChange={e => setData('url', e.target.value)}
                                        placeholder="https://your-service.com/webhook"
                                        className="bg-[#1a1d24] border-gray-700 text-white placeholder-gray-600 focus-visible:ring-teal-500"
                                    />
                                    {errors.url && <p className="text-red-400 text-xs">{errors.url}</p>}
                                </div>

                                {/* Secret */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold text-gray-400 uppercase tracking-wider">
                                        Secret <span className="text-gray-600 normal-case font-normal">(optional — used for HMAC-SHA256 signature)</span>
                                    </Label>
                                    <Input
                                        value={data.secret}
                                        onChange={e => setData('secret', e.target.value)}
                                        placeholder="Leave empty to skip signature verification"
                                        className="bg-[#1a1d24] border-gray-700 text-white placeholder-gray-600 focus-visible:ring-teal-500"
                                    />
                                </div>

                                {/* Events */}
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold text-gray-400 uppercase tracking-wider">
                                        Events <span className="text-gray-600 normal-case font-normal">(select which events to subscribe to)</span>
                                    </Label>
                                    
                                    <div className="relative" ref={dropdownRef}>
                                        {/* Multi-Select Input Trigger */}
                                        <div
                                            onClick={() => setDropdownOpen(!dropdownOpen)}
                                            className={`flex min-h-[44px] w-full items-center justify-between rounded-md border bg-[#1a1d24] px-3 py-2 text-sm cursor-pointer select-none transition-all duration-200
                                                ${dropdownOpen ? 'border-teal-500 ring-2 ring-teal-500/20' : 'border-gray-700 hover:border-gray-600'}
                                            `}
                                        >
                                            {data.events.length === 0 ? (
                                                <span className="text-gray-500">Select events to subscribe to</span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1.5 pr-6">
                                                    {data.events.map(eventId => {
                                                        const ev = availableEvents.find(e => e.id === eventId);
                                                        const label = ev ? ev.label : eventId;
                                                        return (
                                                            <Badge
                                                                key={eventId}
                                                                className="bg-teal-500/10 text-teal-400 hover:bg-teal-500/20 border border-teal-500/20 px-2 py-0.5 rounded flex items-center gap-1 text-xs"
                                                            >
                                                                {label}
                                                                <button
                                                                    type="button"
                                                                    onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        toggleEvent(eventId);
                                                                    }}
                                                                    className="hover:text-teal-300 rounded-full p-0.5"
                                                                >
                                                                    <X className="w-3 h-3" />
                                                                </button>
                                                            </Badge>
                                                        );
                                                    })}
                                                </div>
                                            )}
                                            <div className="flex items-center gap-2 text-gray-500 absolute right-3 top-1/2 -translate-y-1/2">
                                                {loadingEvents && <Loader2 className="w-4 h-4 animate-spin text-teal-500" />}
                                                <ChevronDown className={`w-4 h-4 transition-transform duration-200 ${dropdownOpen ? 'rotate-180 text-teal-500' : ''}`} />
                                            </div>
                                        </div>

                                        {/* Dropdown Menu */}
                                        {dropdownOpen && (
                                            <div className="absolute z-50 w-full mt-1 bg-[#161922] border border-gray-800 rounded-md shadow-2xl overflow-hidden animate-in fade-in slide-in-from-top-1 duration-200">
                                                {/* Search Box */}
                                                <div className="relative border-b border-gray-800 p-2">
                                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" />
                                                    <input
                                                        type="text"
                                                        value={searchQuery}
                                                        onChange={e => setSearchQuery(e.target.value)}
                                                        placeholder="Search events..."
                                                        className="w-full bg-[#1e222b] border border-gray-750 rounded-md pl-9 pr-3 py-1.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/50"
                                                        onClick={e => e.stopPropagation()} // Prevent closing dropdown
                                                    />
                                                </div>

                                                {/* Options List */}
                                                <div className="max-h-60 overflow-y-auto p-1 divide-y divide-gray-800/30">
                                                    {loadingEvents ? (
                                                        <div className="flex items-center justify-center py-6 text-sm text-gray-500 gap-2">
                                                            <Loader2 className="w-4 h-4 animate-spin text-teal-500" />
                                                            Fetching events...
                                                        </div>
                                                    ) : filteredEvents.length === 0 ? (
                                                        <div className="py-6 text-center text-sm text-gray-500">
                                                            {availableEvents.length === 0 ? 'No events available' : 'No matching events'}
                                                        </div>
                                                    ) : (
                                                        filteredEvents.map(ev => {
                                                            const isSelected = data.events.includes(ev.id);
                                                            return (
                                                                <div
                                                                    key={ev.id}
                                                                    onClick={() => toggleEvent(ev.id)}
                                                                    className={`flex items-start gap-3 px-3 py-2 text-sm rounded cursor-pointer transition-colors select-none
                                                                        ${isSelected ? 'bg-teal-500/5 text-white' : 'hover:bg-gray-800/40 text-gray-350 hover:text-white'}
                                                                    `}
                                                                >
                                                                    <div className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors
                                                                        ${isSelected ? 'bg-teal-500 border-teal-500 text-white' : 'border-gray-600'}
                                                                    `}>
                                                                        {isSelected && <Check className="w-3 h-3" />}
                                                                    </div>
                                                                    <div className="space-y-0.5">
                                                                        <div className="font-medium text-xs md:text-sm">{ev.label}</div>
                                                                        <div className="text-[11px] text-gray-500 font-mono">{ev.id}</div>
                                                                        {ev.description && (
                                                                            <div className="text-[11px] text-gray-400 mt-0.5 font-light leading-snug">
                                                                                {ev.description}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            );
                                                        })
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                    {errors.events && <p className="text-red-400 text-xs">{errors.events}</p>}
                                </div>

                                {/* Timeout & Retries */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold text-gray-400 uppercase tracking-wider">Timeout (seconds)</Label>
                                        <Input
                                            type="number" min={1} max={120}
                                            value={data.timeout}
                                            onChange={e => setData('timeout', parseInt(e.target.value))}
                                            className="bg-[#1a1d24] border-gray-700 text-white focus-visible:ring-teal-500"
                                        />
                                        {errors.timeout && <p className="text-red-400 text-xs">{errors.timeout}</p>}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold text-gray-400 uppercase tracking-wider">Max Retries</Label>
                                        <Input
                                            type="number" min={0} max={10}
                                            value={data.max_retries}
                                            onChange={e => setData('max_retries', parseInt(e.target.value))}
                                            className="bg-[#1a1d24] border-gray-700 text-white focus-visible:ring-teal-500"
                                        />
                                        {errors.max_retries && <p className="text-red-400 text-xs">{errors.max_retries}</p>}
                                    </div>
                                </div>

                                {/* Toggles */}
                                <div className="flex items-center gap-8 pt-1">
                                    <div className="flex items-center gap-2.5">
                                        <Checkbox
                                            id="is_active"
                                            checked={data.is_active}
                                            onCheckedChange={v => setData('is_active', v)}
                                            className={data.is_active ? 'bg-teal-500 border-teal-500' : 'border-gray-600'}
                                        />
                                        <label htmlFor="is_active" className="text-sm text-gray-300 cursor-pointer select-none"
                                            onClick={() => setData('is_active', !data.is_active)}>
                                            Active
                                        </label>
                                    </div>
                                    <div className="flex items-center gap-2.5">
                                        <Checkbox
                                            id="verify_ssl"
                                            checked={data.verify_ssl}
                                            onCheckedChange={v => setData('verify_ssl', v)}
                                            className={data.verify_ssl ? 'bg-teal-500 border-teal-500' : 'border-gray-600'}
                                        />
                                        <label htmlFor="verify_ssl" className="text-sm text-gray-300 cursor-pointer select-none"
                                            onClick={() => setData('verify_ssl', !data.verify_ssl)}>
                                            Verify SSL
                                        </label>
                                    </div>
                                </div>

                                {/* Footer */}
                                <div className="flex justify-end gap-3 pt-4 border-t border-gray-800">
                                    <Button type="button" variant="ghost" onClick={closeModal}
                                        className="text-gray-400 hover:text-white hover:bg-gray-800">
                                        CANCEL
                                    </Button>
                                    <Button type="submit" disabled={processing}
                                        className="bg-teal-600 hover:bg-teal-700 text-white min-w-[100px]">
                                        {processing ? 'Saving…' : (editingWebhook ? 'UPDATE' : 'CREATE')}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </>
            )}
        </AdminLayout>
    );
}
