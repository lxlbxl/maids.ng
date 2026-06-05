import React, { useEffect, useRef } from 'react';

export function Dialog({ open, onOpenChange, children }) {
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.key === 'Escape' && open) {
                onOpenChange && onOpenChange(false);
            }
        };
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [open, onOpenChange]);

    return <>{children}</>;
}

export function DialogTrigger({ asChild, children, onClick }) {
    if (asChild && React.isValidElement(children)) {
        return React.cloneElement(children, {
            onClick: (e) => {
                children.props.onClick && children.props.onClick(e);
                onClick && onClick(e);
            },
        });
    }
    return <button onClick={onClick}>{children}</button>;
}

export function DialogPortal({ children, open }) {
    if (!open) return null;
    return <>{children}</>;
}

export function DialogOverlay({ className = '', onClick, ...props }) {
    return (
        <div
            className={`fixed inset-0 z-50 bg-black/80 backdrop-blur-sm ${className}`}
            onClick={onClick}
            {...props}
        />
    );
}

export function DialogContent({ className = '', children, open, onOpenChange, ...props }) {
    const isOpen = open !== undefined ? open : true;

    if (!isOpen) return null;

    return (
        <>
            <DialogOverlay onClick={() => onOpenChange && onOpenChange(false)} />
            <div
                className={`fixed left-[50%] top-[50%] z-50 translate-x-[-50%] translate-y-[-50%] w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-lg border border-gray-200 bg-white p-6 shadow-lg ${className}`}
                {...props}
            >
                {children}
            </div>
        </>
    );
}

export function DialogHeader({ className = '', children, ...props }) {
    return (
        <div className={`flex flex-col space-y-1.5 text-center sm:text-left ${className}`} {...props}>
            {children}
        </div>
    );
}

export function DialogFooter({ className = '', children, ...props }) {
    return (
        <div className={`flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2 ${className}`} {...props}>
            {children}
        </div>
    );
}

export function DialogTitle({ className = '', children, ...props }) {
    return (
        <h2 className={`text-lg font-semibold leading-none tracking-tight ${className}`} {...props}>
            {children}
        </h2>
    );
}

export function DialogDescription({ className = '', children, ...props }) {
    return (
        <p className={`text-sm text-gray-500 ${className}`} {...props}>
            {children}
        </p>
    );
}

// Higher-level Dialog wrapper that manages open state internally via context
import { createContext, useContext, useState as useDialogState } from 'react';

const DialogContext = createContext({});

export function DialogRoot({ open: controlledOpen, onOpenChange, children }) {
    const [uncontrolledOpen, setUncontrolledOpen] = useDialogState(false);
    const isControlled = controlledOpen !== undefined;
    const open = isControlled ? controlledOpen : uncontrolledOpen;
    const setOpen = isControlled ? onOpenChange : setUncontrolledOpen;

    return (
        <DialogContext.Provider value={{ open, setOpen }}>
            {children}
        </DialogContext.Provider>
    );
}

export function useDialog() {
    return useContext(DialogContext);
}
