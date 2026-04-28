/**
 * Simple State Management Store
 * Lightweight reactive state management for vanilla JS applications
 */
class Store {
    constructor(initialState = {}) {
        this._state = initialState;
        this._listeners = new Map();
        this._globalListeners = [];
    }

    /**
     * Get current state or a specific key
     */
    get(key = null) {
        if (key === null) {
            return { ...this._state };
        }
        return this._state[key];
    }

    /**
     * Set state (merges with existing state)
     */
    set(keyOrState, value = undefined) {
        const prevState = { ...this._state };
        let changedKeys = [];

        if (typeof keyOrState === 'object') {
            // Merge object into state
            Object.keys(keyOrState).forEach(key => {
                if (this._state[key] !== keyOrState[key]) {
                    changedKeys.push(key);
                }
            });
            this._state = { ...this._state, ...keyOrState };
        } else {
            // Set single key
            if (this._state[keyOrState] !== value) {
                changedKeys.push(keyOrState);
            }
            this._state[keyOrState] = value;
        }

        // Notify listeners
        if (changedKeys.length > 0) {
            this._notifyListeners(changedKeys, prevState);
        }
    }

    /**
     * Subscribe to state changes
     * @param {string|function} keyOrCallback - Key to watch or callback for all changes
     * @param {function} callback - Callback if key is provided
     * @returns {function} Unsubscribe function
     */
    subscribe(keyOrCallback, callback = null) {
        if (typeof keyOrCallback === 'function') {
            // Global listener
            this._globalListeners.push(keyOrCallback);
            return () => {
                this._globalListeners = this._globalListeners.filter(cb => cb !== keyOrCallback);
            };
        } else {
            // Key-specific listener
            const key = keyOrCallback;
            if (!this._listeners.has(key)) {
                this._listeners.set(key, []);
            }
            this._listeners.get(key).push(callback);
            return () => {
                const listeners = this._listeners.get(key);
                if (listeners) {
                    this._listeners.set(key, listeners.filter(cb => cb !== callback));
                }
            };
        }
    }

    /**
     * Reset state to initial or provided state
     */
    reset(newState = {}) {
        const prevState = { ...this._state };
        this._state = newState;
        this._notifyListeners(Object.keys({ ...prevState, ...newState }), prevState);
    }

    /**
     * Notify all relevant listeners of state changes
     */
    _notifyListeners(changedKeys, prevState) {
        // Notify key-specific listeners
        changedKeys.forEach(key => {
            const listeners = this._listeners.get(key);
            if (listeners) {
                listeners.forEach(callback => {
                    try {
                        callback(this._state[key], prevState[key], key);
                    } catch (e) {
                        console.error('Store listener error:', e);
                    }
                });
            }
        });

        // Notify global listeners
        this._globalListeners.forEach(callback => {
            try {
                callback(this._state, prevState, changedKeys);
            } catch (e) {
                console.error('Store global listener error:', e);
            }
        });
    }
}

/**
 * Dashboard-specific store with pre-defined state shape
 */
class DashboardStore extends Store {
    constructor() {
        super({
            // User state
            user: null,
            userType: null, // 'helper' or 'employer'
            profile: null,

            // UI state
            currentSection: 'overview',
            loading: false,
            error: null,

            // Data
            bookings: [],
            payments: [],
            notifications: [],
            stats: null,

            // Filters
            bookingFilter: 'all',
            dateRange: {
                start: null,
                end: null
            }
        });
    }

    // Convenience methods
    setLoading(loading) {
        this.set('loading', loading);
    }

    setError(error) {
        this.set('error', error);
    }

    setUser(user, userType = null, profile = null) {
        this.set({
            user,
            userType: userType || user?.type,
            profile
        });
    }

    setSection(section) {
        this.set('currentSection', section);
    }

    setBookings(bookings) {
        this.set('bookings', bookings);
    }

    setPayments(payments) {
        this.set('payments', payments);
    }

    setStats(stats) {
        this.set('stats', stats);
    }

    // Computed getters
    getActiveBookings() {
        return this.get('bookings').filter(b =>
            ['pending', 'confirmed', 'in_progress'].includes(b.status)
        );
    }

    getCompletedBookings() {
        return this.get('bookings').filter(b => b.status === 'completed');
    }

    getPendingPayments() {
        return this.get('payments').filter(p => p.status === 'pending');
    }
}

/**
 * Admin-specific store with pre-defined state shape
 */
class AdminStore extends Store {
    constructor() {
        super({
            // Admin user
            admin: null,
            permissions: [],

            // UI state
            currentModule: 'dashboard',
            loading: false,
            error: null,

            // Dashboard data
            kpis: null,
            recentActivity: [],
            charts: {
                bookings: [],
                revenue: [],
                users: []
            },

            // List data with pagination
            helpers: { data: [], total: 0, page: 1, perPage: 20 },
            employers: { data: [], total: 0, page: 1, perPage: 20 },
            bookings: { data: [], total: 0, page: 1, perPage: 20 },
            payments: { data: [], total: 0, page: 1, perPage: 20 },
            verifications: { data: [], total: 0, page: 1, perPage: 20 },
            leads: { data: [], total: 0, page: 1, perPage: 20 },

            // Filters
            filters: {
                helpers: {},
                bookings: {},
                payments: {},
                verifications: {}
            },

            // Selected items for bulk actions
            selected: {
                helpers: [],
                bookings: [],
                payments: []
            }
        });
    }

    // Convenience methods
    setLoading(loading) {
        this.set('loading', loading);
    }

    setError(error) {
        this.set('error', error);
    }

    setAdmin(admin, permissions = []) {
        this.set({ admin, permissions });
    }

    setModule(module) {
        this.set('currentModule', module);
    }

    setKpis(kpis) {
        this.set('kpis', kpis);
    }

    // Permission check
    hasPermission(permission) {
        const permissions = this.get('permissions');
        return permissions.includes('*') || permissions.includes(permission);
    }

    // Update paginated data
    setListData(listName, data, total, page) {
        const current = this.get(listName);
        this.set(listName, { ...current, data, total, page });
    }

    // Update filters
    setFilter(listName, filters) {
        const current = this.get('filters');
        this.set('filters', { ...current, [listName]: filters });
    }

    // Selection management
    toggleSelection(listName, id) {
        const selected = this.get('selected');
        const current = selected[listName] || [];
        const newSelection = current.includes(id)
            ? current.filter(i => i !== id)
            : [...current, id];
        this.set('selected', { ...selected, [listName]: newSelection });
    }

    clearSelection(listName) {
        const selected = this.get('selected');
        this.set('selected', { ...selected, [listName]: [] });
    }
}

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { Store, DashboardStore, AdminStore };
} else {
    window.Store = Store;
    window.DashboardStore = DashboardStore;
    window.AdminStore = AdminStore;
}
