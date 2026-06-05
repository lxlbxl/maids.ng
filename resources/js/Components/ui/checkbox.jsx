import React, { useState } from 'react';

export function Checkbox({ id, checked, onCheckedChange, className = '', disabled = false, ...props }) {
    return (
        <button
            type="button"
            id={id}
            role="checkbox"
            aria-checked={checked}
            disabled={disabled}
            onClick={() => onCheckedChange && onCheckedChange(!checked)}
            className={`peer h-4 w-4 shrink-0 rounded-sm border border-gray-300 ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-950 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-colors
                ${checked ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white'}
                ${className}`}
            {...props}
        >
            {checked && (
                <svg
                    className="h-3 w-3 text-white mx-auto"
                    viewBox="0 0 12 12"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                >
                    <polyline points="2,6 5,9 10,3" />
                </svg>
            )}
        </button>
    );
}
