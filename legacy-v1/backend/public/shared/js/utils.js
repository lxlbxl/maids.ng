/**
 * Shared Utility Functions
 * Common helpers used across dashboard and admin panel
 */

const Utils = {
    /**
     * Format currency (Nigerian Naira)
     */
    formatCurrency(amount, currency = 'NGN') {
        const num = parseFloat(amount) || 0;
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(num);
    },

    /**
     * Format date to readable string
     */
    formatDate(date, options = {}) {
        if (!date) return '-';
        const d = new Date(date);
        if (isNaN(d.getTime())) return '-';

        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            ...options
        };

        return d.toLocaleDateString('en-NG', defaultOptions);
    },

    /**
     * Format date with time
     */
    formatDateTime(date) {
        return this.formatDate(date, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    /**
     * Get relative time (e.g., "2 hours ago")
     */
    timeAgo(date) {
        if (!date) return '-';
        const d = new Date(date);
        if (isNaN(d.getTime())) return '-';

        const now = new Date();
        const seconds = Math.floor((now - d) / 1000);

        const intervals = [
            { label: 'year', seconds: 31536000 },
            { label: 'month', seconds: 2592000 },
            { label: 'week', seconds: 604800 },
            { label: 'day', seconds: 86400 },
            { label: 'hour', seconds: 3600 },
            { label: 'minute', seconds: 60 }
        ];

        for (const interval of intervals) {
            const count = Math.floor(seconds / interval.seconds);
            if (count >= 1) {
                return `${count} ${interval.label}${count > 1 ? 's' : ''} ago`;
            }
        }

        return 'Just now';
    },

    /**
     * Format phone number for display
     */
    formatPhone(phone) {
        if (!phone) return '-';
        // Format Nigerian numbers: 0803 123 4567
        const cleaned = phone.replace(/\D/g, '');
        if (cleaned.startsWith('234') && cleaned.length === 13) {
            return `0${cleaned.slice(3, 6)} ${cleaned.slice(6, 9)} ${cleaned.slice(9)}`;
        }
        if (cleaned.length === 11 && cleaned.startsWith('0')) {
            return `${cleaned.slice(0, 4)} ${cleaned.slice(4, 7)} ${cleaned.slice(7)}`;
        }
        return phone;
    },

    /**
     * Truncate text with ellipsis
     */
    truncate(text, maxLength = 50) {
        if (!text) return '';
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength - 3) + '...';
    },

    /**
     * Capitalize first letter
     */
    capitalize(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    },

    /**
     * Convert status to badge HTML
     */
    statusBadge(status, type = 'booking') {
        const statusConfig = {
            booking: {
                pending: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'Pending' },
                confirmed: { bg: 'bg-blue-100', text: 'text-blue-800', label: 'Confirmed' },
                in_progress: { bg: 'bg-purple-100', text: 'text-purple-800', label: 'In Progress' },
                completed: { bg: 'bg-green-100', text: 'text-green-800', label: 'Completed' },
                cancelled: { bg: 'bg-red-100', text: 'text-red-800', label: 'Cancelled' }
            },
            payment: {
                pending: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'Pending' },
                success: { bg: 'bg-green-100', text: 'text-green-800', label: 'Success' },
                failed: { bg: 'bg-red-100', text: 'text-red-800', label: 'Failed' },
                refunded: { bg: 'bg-gray-100', text: 'text-gray-800', label: 'Refunded' }
            },
            verification: {
                pending: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'Pending' },
                verified: { bg: 'bg-green-100', text: 'text-green-800', label: 'Verified' },
                failed: { bg: 'bg-red-100', text: 'text-red-800', label: 'Failed' },
                manual_review: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Manual Review' }
            },
            badge: {
                bronze: { bg: 'bg-amber-100', text: 'text-amber-800', label: 'Bronze' },
                silver: { bg: 'bg-gray-100', text: 'text-gray-600', label: 'Silver' },
                gold: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'Gold' }
            }
        };

        const config = statusConfig[type]?.[status] || {
            bg: 'bg-gray-100',
            text: 'text-gray-800',
            label: this.capitalize(status?.replace(/_/g, ' ') || 'Unknown')
        };

        return `<span class="px-2 py-1 text-xs font-medium rounded-full ${config.bg} ${config.text}">${config.label}</span>`;
    },

    /**
     * Generate avatar placeholder
     */
    avatarPlaceholder(name, size = 40) {
        const initials = (name || '?')
            .split(' ')
            .map(n => n[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();

        const colors = [
            'bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-red-500',
            'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-teal-500'
        ];

        const colorIndex = (name || '').charCodeAt(0) % colors.length;
        const bgColor = colors[colorIndex];

        return `<div class="flex items-center justify-center rounded-full ${bgColor} text-white font-semibold"
                     style="width: ${size}px; height: ${size}px; font-size: ${size * 0.4}px;">
                    ${initials}
                </div>`;
    },

    /**
     * Debounce function calls
     */
    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function calls
     */
    throttle(func, limit = 100) {
        let inThrottle;
        return function executedFunction(...args) {
            if (!inThrottle) {
                func(...args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Deep clone an object
     */
    deepClone(obj) {
        if (obj === null || typeof obj !== 'object') return obj;
        if (obj instanceof Date) return new Date(obj);
        if (obj instanceof Array) return obj.map(item => this.deepClone(item));
        if (typeof obj === 'object') {
            const cloned = {};
            for (const key in obj) {
                if (obj.hasOwnProperty(key)) {
                    cloned[key] = this.deepClone(obj[key]);
                }
            }
            return cloned;
        }
    },

    /**
     * Build query string from object
     */
    buildQueryString(params) {
        const query = Object.entries(params)
            .filter(([_, value]) => value !== null && value !== undefined && value !== '')
            .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
            .join('&');
        return query ? `?${query}` : '';
    },

    /**
     * Parse query string to object
     */
    parseQueryString(queryString) {
        if (!queryString || queryString === '?') return {};
        const query = queryString.startsWith('?') ? queryString.slice(1) : queryString;
        return query.split('&').reduce((acc, pair) => {
            const [key, value] = pair.split('=').map(decodeURIComponent);
            acc[key] = value;
            return acc;
        }, {});
    },

    /**
     * Simple template engine
     * Usage: Utils.template('Hello {{name}}!', { name: 'World' })
     */
    template(str, data) {
        return str.replace(/\{\{(\w+)\}\}/g, (match, key) => {
            return data.hasOwnProperty(key) ? data[key] : match;
        });
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    /**
     * Generate unique ID
     */
    generateId(prefix = 'id') {
        return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    },

    /**
     * Check if element is in viewport
     */
    isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    },

    /**
     * Smooth scroll to element
     */
    scrollTo(element, offset = 0) {
        const target = typeof element === 'string' ? document.querySelector(element) : element;
        if (target) {
            const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    },

    /**
     * Copy text to clipboard
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                return true;
            } catch (e) {
                return false;
            } finally {
                document.body.removeChild(textarea);
            }
        }
    },

    /**
     * Download data as file
     */
    downloadFile(data, filename, type = 'application/json') {
        const blob = new Blob([typeof data === 'string' ? data : JSON.stringify(data, null, 2)], { type });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    },

    /**
     * Format file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    /**
     * Validate email format
     */
    isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    /**
     * Validate Nigerian phone number
     */
    isValidNigerianPhone(phone) {
        const cleaned = phone.replace(/\D/g, '');
        // 11 digits starting with 0, or 13 digits starting with 234
        return (cleaned.length === 11 && cleaned.startsWith('0')) ||
               (cleaned.length === 13 && cleaned.startsWith('234'));
    },

    /**
     * Get contrast color (black or white) for a given background color
     */
    getContrastColor(hexColor) {
        const r = parseInt(hexColor.substr(1, 2), 16);
        const g = parseInt(hexColor.substr(3, 2), 16);
        const b = parseInt(hexColor.substr(5, 2), 16);
        const yiq = (r * 299 + g * 587 + b * 114) / 1000;
        return yiq >= 128 ? '#000000' : '#FFFFFF';
    },

    /**
     * Local storage wrapper with JSON support
     */
    storage: {
        get(key, defaultValue = null) {
            try {
                const item = localStorage.getItem(key);
                return item ? JSON.parse(item) : defaultValue;
            } catch (e) {
                return defaultValue;
            }
        },
        set(key, value) {
            try {
                localStorage.setItem(key, JSON.stringify(value));
                return true;
            } catch (e) {
                return false;
            }
        },
        remove(key) {
            localStorage.removeItem(key);
        },
        clear() {
            localStorage.clear();
        }
    }
};

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Utils;
} else {
    window.Utils = Utils;
}
