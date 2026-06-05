import React, { useState, useRef, useEffect, createContext, useContext } from 'react';

const SelectContext = createContext({});

export function Select({ value, onValueChange, children, disabled = false }) {
    const [open, setOpen] = useState(false);
    return (
        <SelectContext.Provider value={{ value, onValueChange, open, setOpen, disabled }}>
            <div className="relative">
                {children}
            </div>
        </SelectContext.Provider>
    );
}

export function SelectTrigger({ className = '', children, ...props }) {
    const { value, open, setOpen, disabled } = useContext(SelectContext);
    return (
        <button
            type="button"
            onClick={() => !disabled && setOpen(prev => !prev)}
            disabled={disabled}
            className={`flex h-10 w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-sm ring-offset-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-950 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
            {...props}
        >
            {children}
            <svg className="h-4 w-4 opacity-50 ml-auto shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
    );
}

export function SelectValue({ placeholder }) {
    const { value } = useContext(SelectContext);
    return <span className={value ? '' : 'text-gray-400'}>{value || placeholder}</span>;
}

export function SelectContent({ className = '', children, ...props }) {
    const { open, setOpen } = useContext(SelectContext);
    const ref = useRef(null);

    useEffect(() => {
        const handler = (e) => {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
            }
        };
        if (open) document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [open, setOpen]);

    if (!open) return null;

    return (
        <div
            ref={ref}
            className={`absolute z-50 min-w-[8rem] w-full mt-1 overflow-hidden rounded-md border border-gray-200 bg-white text-gray-950 shadow-md ${className}`}
            {...props}
        >
            <div className="p-1">
                {children}
            </div>
        </div>
    );
}

export function SelectItem({ value: itemValue, className = '', children, disabled = false, ...props }) {
    const { onValueChange, setOpen, value: selectedValue } = useContext(SelectContext);
    const isSelected = selectedValue === itemValue;

    return (
        <div
            onClick={() => {
                if (!disabled) {
                    onValueChange && onValueChange(itemValue);
                    setOpen(false);
                }
            }}
            className={`relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none
                ${isSelected ? 'bg-gray-100 font-medium' : 'hover:bg-gray-100'}
                ${disabled ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
                ${className}`}
            {...props}
        >
            {isSelected && (
                <span className="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
            )}
            {children}
        </div>
    );
}

export function SelectSeparator({ className = '', ...props }) {
    return <div className={`-mx-1 my-1 h-px bg-gray-100 ${className}`} {...props} />;
}

export function SelectLabel({ className = '', children, ...props }) {
    return (
        <div className={`py-1.5 pl-8 pr-2 text-xs font-semibold text-gray-500 ${className}`} {...props}>
            {children}
        </div>
    );
}
