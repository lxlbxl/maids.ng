/**
 * Toast Notification System
 * Maids.ng - Warm luxury themed notifications
 */
class Toast {
    constructor() {
        this.container = null;
        this.queue = [];
        this.maxVisible = 3;
        this.defaultDuration = 4000;
        this.init();
    }

    init() {
        if (!document.getElementById('toast-container')) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.style.cssText = 'position:fixed;top:16px;right:16px;z-index:10000;display:flex;flex-direction:column;gap:8px;max-width:380px;width:calc(100% - 32px);pointer-events:none;';
            document.body.appendChild(this.container);
        } else {
            this.container = document.getElementById('toast-container');
        }
    }

    show(message, type = 'info', duration = this.defaultDuration) {
        if (!this.container) this.init();

        const toast = document.createElement('div');
        toast.style.cssText = `
            pointer-events:auto;
            display:flex;align-items:flex-start;gap:12px;
            padding:14px 16px;border-radius:16px;
            font-family:'DM Sans',sans-serif;font-size:14px;line-height:1.45;
            box-shadow:0 8px 32px rgba(0,0,0,0.12),0 2px 8px rgba(0,0,0,0.08);
            transform:translateX(120%);opacity:0;
            transition:all 0.4s cubic-bezier(0.22,1,0.36,1);
            ${this.getStyles(type)}
        `;

        toast.innerHTML = `
            <span style="flex-shrink:0;margin-top:1px;">${this.getIcon(type)}</span>
            <p style="flex:1;margin:0;">${message}</p>
            <button style="flex-shrink:0;opacity:0.6;cursor:pointer;background:none;border:none;padding:2px;color:inherit;transition:opacity 0.2s;" onmouseenter="this.style.opacity='1'" onmouseleave="this.style.opacity='0.6'" onclick="this.closest('[data-toast]').dispatchEvent(new Event('dismiss'))">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        `;
        toast.setAttribute('data-toast', 'true');

        toast.addEventListener('dismiss', () => this.dismiss(toast));

        this.container.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                toast.style.transform = 'translateX(0)';
                toast.style.opacity = '1';
            });
        });

        // Progress bar for auto-dismiss
        if (duration > 0) {
            const progress = document.createElement('div');
            progress.style.cssText = `
                position:absolute;bottom:0;left:12px;right:12px;height:3px;
                border-radius:3px;opacity:0.3;
                background:currentColor;
                transform-origin:left;
                transition:transform ${duration}ms linear;
            `;
            toast.style.position = 'relative';
            toast.style.overflow = 'hidden';
            toast.appendChild(progress);

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    progress.style.transform = 'scaleX(0)';
                });
            });

            setTimeout(() => this.dismiss(toast), duration);
        }

        // Manage queue
        const toasts = this.container.querySelectorAll('[data-toast]');
        if (toasts.length > this.maxVisible) {
            this.dismiss(toasts[0]);
        }

        return toast;
    }

    dismiss(toast) {
        if (!toast || !toast.parentElement) return;

        toast.style.transform = 'translateX(120%)';
        toast.style.opacity = '0';
        toast.style.marginTop = `-${toast.offsetHeight + 8}px`;

        setTimeout(() => {
            if (toast.parentElement) toast.remove();
        }, 400);
    }

    getStyles(type) {
        const styles = {
            success: 'background:linear-gradient(135deg,#065f46,#047857);color:#d1fae5;',
            error: 'background:linear-gradient(135deg,#991b1b,#b91c1c);color:#fecaca;',
            warning: 'background:linear-gradient(135deg,#92400e,#b45309);color:#fef3c7;',
            info: 'background:linear-gradient(135deg,#1a1614,#2d2420);color:#fef3c7;border:1px solid rgba(245,158,11,0.2);'
        };
        return styles[type] || styles.info;
    }

    getIcon(type) {
        const icons = {
            success: '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            error: '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            warning: '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
            info: '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
        };
        return icons[type] || icons.info;
    }

    // Convenience methods
    success(message, duration) { return this.show(message, 'success', duration); }
    error(message, duration) { return this.show(message, 'error', duration); }
    warning(message, duration) { return this.show(message, 'warning', duration); }
    info(message, duration) { return this.show(message, 'info', duration); }
}

// Singleton
const toast = new Toast();

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { Toast, toast };
} else {
    window.Toast = Toast;
    window.toast = toast;
}
