import React from 'react';

export function Button({ children, variant = 'default', size = 'default', className = '', disabled = false, type = 'button', onClick, asChild = false, ...props }) {
    const base = 'inline-flex items-center justify-center rounded-md font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';

    const variants = {
        default: 'bg-gray-900 text-white hover:bg-gray-800 focus-visible:ring-gray-900',
        destructive: 'bg-red-500 text-white hover:bg-red-600 focus-visible:ring-red-600',
        outline: 'border border-gray-200 bg-white hover:bg-gray-100 hover:text-gray-900',
        secondary: 'bg-gray-100 text-gray-900 hover:bg-gray-200',
        ghost: 'hover:bg-gray-100 hover:text-gray-900',
        link: 'text-gray-900 underline-offset-4 hover:underline',
    };

    const sizes = {
        default: 'h-10 px-4 py-2 text-sm',
        sm: 'h-8 rounded-md px-3 text-xs',
        lg: 'h-11 rounded-md px-8 text-base',
        icon: 'h-9 w-9',
    };

    const classes = `${base} ${variants[variant] || variants.default} ${sizes[size] || sizes.default} ${className}`;

    return (
        <button type={type} className={classes} disabled={disabled} onClick={onClick} {...props}>
            {children}
        </button>
    );
}
