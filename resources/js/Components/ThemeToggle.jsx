import { Sun, Moon, Monitor } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { useTheme } from './ThemeProvider';

export default function ThemeToggle({ className = '' }) {
    const { theme, setTheme } = useTheme();
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const handle = (e) => {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handle);
        return () => document.removeEventListener('mousedown', handle);
    }, []);

    const options = [
        { value: 'light', label: 'Light', icon: Sun },
        { value: 'dark', label: 'Dark', icon: Moon },
        { value: 'system', label: 'System', icon: Monitor },
    ];

    const current = options.find((o) => o.value === theme) || options[0];
    const Icon = current.icon;

    return (
        <div className={`relative ${className}`} ref={ref}>
            <button
                onClick={() => setOpen(!open)}
                className="flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 text-espresso dark:text-white hover:bg-gray-50 dark:hover:bg-white/10 transition-colors"
                aria-label="Toggle theme"
                title="Toggle theme"
            >
                <Icon className="w-4 h-4" />
            </button>

            {open && (
                <div className="absolute right-0 mt-2 w-36 bg-white dark:bg-[#1c1c1e] border border-gray-200 dark:border-white/10 rounded-brand-lg shadow-brand-2 py-1 z-50">
                    {options.map((option) => {
                        const OptionIcon = option.icon;
                        return (
                            <button
                                key={option.value}
                                onClick={() => {
                                    setTheme(option.value);
                                    setOpen(false);
                                }}
                                className={`w-full flex items-center gap-2 px-3 py-2 text-sm transition-colors ${
                                    theme === option.value
                                        ? 'text-teal bg-teal/5 dark:bg-teal/10 font-medium'
                                        : 'text-muted hover:bg-gray-50 dark:hover:bg-white/5'
                                }`}
                            >
                                <OptionIcon className="w-4 h-4" />
                                {option.label}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
