import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';

export default function Toast() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState(false);
    const [message, setMessage] = useState(null);
    const [type, setType] = useState('success');

    useEffect(() => {
        if (flash.success || flash.error || flash.message || flash.warning) {
            setMessage(flash.success || flash.error || flash.message || flash.warning);
            setType(flash.error ? 'error' : (flash.warning ? 'warning' : 'success'));
            setVisible(true);

            const timer = setTimeout(() => {
                setVisible(false);
            }, 5000);

            return () => clearTimeout(timer);
        }
    }, [flash]);

    if (!visible || !message) return null;

    const styles = {
        success: 'bg-teal text-white shadow-[0_0_20px_rgba(45,164,142,0.5)]',
        error: 'bg-danger text-white shadow-[0_0_20px_rgba(235,87,87,0.5)]',
        warning: 'bg-copper text-white shadow-[0_0_20px_rgba(242,153,74,0.5)]',
    };

    return (
        <div className="fixed top-6 right-6 z-[100] animate-in fade-in slide-in-from-top-4 duration-300">
            <div className={`px-6 py-4 rounded-brand-lg flex items-center gap-4 border border-white/10 ${styles[type]}`}>
                <span className="text-xl">
                    {type === 'success' ? '✓' : type === 'error' ? '✕' : '⚠️'}
                </span>
                <div className="flex flex-col">
                    <p className="text-[10px] font-mono uppercase tracking-[0.2em] opacity-60 font-bold">System Pulse</p>
                    <p className="text-sm font-medium">{message}</p>
                </div>
                <button 
                    onClick={() => setVisible(false)}
                    className="ml-4 opacity-40 hover:opacity-100 transition-opacity"
                >
                    ✕
                </button>
            </div>
        </div>
    );
}
