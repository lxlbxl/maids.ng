import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                display: ['"Cormorant Garamond"', 'Georgia', 'serif'],
                body: ['"DM Sans"', 'system-ui', 'sans-serif'],
                mono: ['"DM Mono"', 'monospace'],
                sans: ['"DM Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Brand Teal
                teal: {
                    DEFAULT: '#0F5556',
                    dark:    '#083A3B',
                    deep:    '#052829',
                    mid:     '#1A7374',
                    light:   '#2A9799',
                    pale:    '#D0EAEA',
                    ghost:   '#EAF4F4',
                },
                // Warm Accent – Heritage Copper
                copper: {
                    DEFAULT: '#B45309',
                    light:   '#D4813A',
                    pale:    '#FEF3E6',
                },
                // Neutral Surfaces
                ivory:   '#FAF7F2',
                linen:   '#F1EAD7',
                espresso:'#1C1812',
                muted:   '#6B7280',
                // Functional
                success: '#0F7566',
                warning: '#E8A020',
                danger:  '#C8372D',
                info:    '#1A6B8A',
            },
            boxShadow: {
                'brand-1': '0 1px 3px rgba(15,85,86,0.08), 0 1px 2px rgba(15,85,86,0.06)',
                'brand-2': '0 4px 16px rgba(15,85,86,0.10), 0 2px 6px rgba(15,85,86,0.08)',
                'brand-3': '0 12px 40px rgba(15,85,86,0.14), 0 4px 12px rgba(15,85,86,0.10)',
            },
            borderRadius: {
                'brand-sm': '6px',
                'brand-md': '12px',
                'brand-lg': '16px',
                'brand-xl': '24px',
            },
            transitionTimingFunction: {
                'brand':   'cubic-bezier(0.4, 0, 0.2, 1)',
                'spring':  'cubic-bezier(0.34, 1.56, 0.64, 1)',
                'enter':   'cubic-bezier(0, 0, 0.2, 1)',
            },
        },
    },

    plugins: [forms],
};
