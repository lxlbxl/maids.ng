import { createContext, useContext, useEffect, useState } from 'react';

const ThemeContext = createContext({
    theme: 'system',
    setTheme: () => {},
    resolvedTheme: 'light',
});

export function ThemeProvider({ children }) {
    const [theme, setThemeState] = useState(() => {
        if (typeof window === 'undefined') return 'system';
        return localStorage.getItem('maids-theme') || 'system';
    });

    const [resolvedTheme, setResolvedTheme] = useState('light');

    useEffect(() => {
        const root = window.document.documentElement;
        const systemQuery = window.matchMedia('(prefers-color-scheme: dark)');

        const apply = (t) => {
            const resolved = t === 'system' ? (systemQuery.matches ? 'dark' : 'light') : t;
            setResolvedTheme(resolved);

            if (resolved === 'dark') {
                root.classList.add('dark');
            } else {
                root.classList.remove('dark');
            }
        };

        apply(theme);

        const listener = (e) => {
            if (theme === 'system') {
                apply('system');
            }
        };

        systemQuery.addEventListener('change', listener);
        return () => systemQuery.removeEventListener('change', listener);
    }, [theme]);

    const setTheme = (t) => {
        localStorage.setItem('maids-theme', t);
        setThemeState(t);
    };

    return (
        <ThemeContext.Provider value={{ theme, setTheme, resolvedTheme }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (!context) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }
    return context;
}
