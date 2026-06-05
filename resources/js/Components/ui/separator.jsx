import React from 'react';

export function Separator({ className = '', orientation = 'horizontal', decorative = true, ...props }) {
    return (
        <div
            role={decorative ? 'none' : 'separator'}
            aria-orientation={orientation === 'horizontal' ? 'horizontal' : 'vertical'}
            className={`shrink-0 bg-gray-200 ${orientation === 'horizontal' ? 'h-[1px] w-full' : 'h-full w-[1px]'} ${className}`}
            {...props}
        />
    );
}
