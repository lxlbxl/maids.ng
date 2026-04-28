/**
 * Maids.ng Dashboard Application
 * Main entry point for the user dashboard SPA
 */

// Initialize store and API
const store = new DashboardStore();
const api = new ApiService('/api');

// DOM Elements cache
const elements = {
    loginPage: null,
    mainApp: null,
    loginForm: null,
    loginError: null,
    greeting: null,
    userName: null,
    statMaids: null,
    statSalary: null,
    statRating: null,
    maidsList: null,
    activityList: null,
    bookingsList: null,
    rateList: null,
    settingsPhone: null,
    ratingModal: null,
    ratingContent: null
};

/**
 * Initialize application
 */
async function initApp() {
    cacheElements();

    api.onUnauthorized = () => {
        store.setUser(null);
        showLogin();
    };

    setupStoreSubscriptions();
    setupEventListeners();
    updateGreeting();
    await checkAuth();
}

/**
 * Cache frequently accessed DOM elements
 */
function cacheElements() {
    elements.loginPage = document.getElementById('login-page');
    elements.mainApp = document.getElementById('main-app');
    elements.loginForm = document.getElementById('login-form');
    elements.loginError = document.getElementById('login-error');
    elements.greeting = document.getElementById('greeting');
    elements.userName = document.getElementById('user-name');
    elements.statMaids = document.getElementById('stat-maids');
    elements.statSalary = document.getElementById('stat-salary');
    elements.statRating = document.getElementById('stat-rating');
    elements.maidsList = document.getElementById('maids-list');
    elements.activityList = document.getElementById('activity-list');
    elements.bookingsList = document.getElementById('bookings-list');
    elements.rateList = document.getElementById('rate-list');
    elements.settingsPhone = document.getElementById('settings-phone');
    elements.ratingModal = document.getElementById('rating-modal');
    elements.ratingContent = document.getElementById('rating-content');
}

/**
 * Set up store subscriptions for reactive updates
 */
function setupStoreSubscriptions() {
    store.subscribe('user', (user) => {
        if (user) {
            elements.userName.textContent = user.name || 'User';
            elements.settingsPhone.textContent = Utils.formatPhone(user.phone) || '-';
        }
    });

    store.subscribe('stats', (stats) => {
        if (stats) {
            elements.statMaids.textContent = stats.activeMaids || 0;
            elements.statSalary.textContent = Utils.formatCurrency(stats.totalSalary || 0);
            elements.statRating.textContent = (stats.avgRating || 0).toFixed(1);
        }
    });

    store.subscribe('bookings', (bookings) => {
        renderBookings(bookings);
    });

    store.subscribe('currentSection', (section) => {
        updateNavigation(section);
        loadSectionData(section);
    });

    store.subscribe('loading', (loading) => {
        document.body.classList.toggle('loading', loading);
    });
}

/**
 * Set up event listeners
 */
function setupEventListeners() {
    elements.loginForm?.addEventListener('submit', handleLogin);
    document.getElementById('change-pin-form')?.addEventListener('submit', handleChangePin);

    elements.ratingModal?.addEventListener('click', (e) => {
        if (e.target.id === 'rating-modal') hideRatingModal();
    });

    document.addEventListener('click', (e) => {
        const navBtn = e.target.closest('[data-page]');
        if (navBtn) {
            showPage(navBtn.dataset.page);
        }
    });
}

/**
 * Check authentication status
 */
async function checkAuth() {
    store.setLoading(true);
    try {
        const data = await api.get('/auth/me');
        if (data.success && data.user) {
            store.setUser(data.user, data.user.type, data.profile);
            showMainApp();
            await loadDashboard();
        } else {
            showLogin();
        }
    } catch (e) {
        console.error('Auth check failed:', e);
        showLogin();
    } finally {
        store.setLoading(false);
    }
}

/**
 * Handle login form submission
 */
async function handleLogin(e) {
    e.preventDefault();

    const phone = document.getElementById('login-phone').value.trim();
    const pin = document.getElementById('login-pin').value;

    if (!phone || phone.length < 10) {
        showLoginError('Please enter a valid phone number');
        return;
    }
    if (!pin || pin.length < 4) {
        showLoginError('Please enter your PIN');
        return;
    }

    hideLoginError();
    store.setLoading(true);

    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.textContent = 'Signing in...';
    btn.disabled = true;

    try {
        const data = await api.post('/auth/login', { phone, pin });

        if (data.success) {
            if (data.tokens) {
                api.setTokens(data.tokens.access_token, data.tokens.refresh_token);
            }
            store.setUser(data.user, data.user?.type, data.profile);
            showMainApp();
            await loadDashboard();
            toast.success('Welcome back!');
        } else {
            showLoginError(data.error || 'Login failed. Please try again.');
        }
    } catch (e) {
        console.error('Login error:', e);
        showLoginError('Connection error. Please check your internet.');
    } finally {
        store.setLoading(false);
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

/**
 * Handle PIN change
 */
async function handleChangePin(e) {
    e.preventDefault();

    const newPin = document.getElementById('new-pin').value;

    if (!newPin || newPin.length < 4) {
        toast.error('PIN must be at least 4 digits');
        return;
    }

    try {
        const data = await api.post('/auth/change-pin', { new_pin: newPin });
        if (data.success) {
            toast.success('PIN updated successfully!');
            document.getElementById('new-pin').value = '';
        } else {
            toast.error(data.error || 'Failed to update PIN');
        }
    } catch (e) {
        toast.error('Failed to update PIN');
    }
}

/**
 * Show/hide login and main app
 */
function showLogin() {
    elements.loginPage?.classList.add('active');
    elements.mainApp?.classList.add('hidden');
}

function showMainApp() {
    elements.loginPage?.classList.remove('active');
    elements.mainApp?.classList.remove('hidden');
}

function showLoginError(message) {
    if (elements.loginError) {
        elements.loginError.textContent = message;
        elements.loginError.classList.remove('hidden');
    }
}

function hideLoginError() {
    elements.loginError?.classList.add('hidden');
}

/**
 * Update greeting based on time of day
 */
function updateGreeting() {
    const hour = new Date().getHours();
    let greeting = 'Good evening';
    if (hour < 12) greeting = 'Good morning';
    else if (hour < 17) greeting = 'Good afternoon';

    if (elements.greeting) {
        elements.greeting.textContent = greeting;
    }
}

/**
 * Navigate to a page/section
 */
function showPage(pageName) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const targetPage = document.getElementById(`${pageName}-page`);
    if (targetPage) {
        targetPage.classList.add('active');
    }

    store.setSection(pageName);
}

/**
 * Update navigation active state
 */
function updateNavigation(section) {
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const activeNav = document.querySelector(`[data-page="${section}"]`);
    if (activeNav) {
        activeNav.classList.add('active');
    }
}

/**
 * Load data for current section
 */
function loadSectionData(section) {
    switch (section) {
        case 'home': loadDashboard(); break;
        case 'history': loadBookings(); break;
        case 'rate': loadRateList(); break;
        case 'settings': loadSettings(); break;
    }
}

/**
 * Load dashboard data
 */
async function loadDashboard() {
    store.setLoading(true);
    try {
        const data = await api.get('/dashboard');

        if (data.success) {
            const userName = data.user_name || store.get('user')?.name || 'User';
            elements.userName.textContent = userName;

            const maids = data.active_maids || [];
            store.setStats({
                activeMaids: maids.length,
                totalSalary: data.total_salary || 0,
                avgRating: data.average_rating || 0
            });

            renderMaids(maids);
            renderActivity(data.activities || []);

            const phone = data.phone || store.get('user')?.phone;
            if (elements.settingsPhone) {
                elements.settingsPhone.textContent = Utils.formatPhone(phone) || '-';
            }
        }
    } catch (e) {
        console.error('Dashboard load error:', e);
        toast.error('Failed to load dashboard');
    } finally {
        store.setLoading(false);
    }
}

window.refreshDashboard = function () {
    loadDashboard();
    toast.info('Refreshing...');
};

/**
 * Render maids list
 */
function renderMaids(maids) {
    if (!elements.maidsList) return;

    if (!maids || !maids.length) {
        elements.maidsList.innerHTML = `
            <div class="text-center py-10">
                <div class="w-16 h-16 bg-cream-200 rounded-2xl mx-auto mb-3 flex items-center justify-center">
                    <svg class="w-7 h-7 text-dark-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <p class="text-dark-300 text-sm">No maids hired yet</p>
                <a href="/" class="inline-block mt-4 px-6 py-3 btn-primary text-white rounded-xl font-semibold text-sm shadow-lg shadow-gold-500/20">Find a Maid</a>
            </div>`;
        return;
    }

    elements.maidsList.innerHTML = maids.map(m => `
        <div class="card-hover glass-card rounded-2xl p-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <img src="${m.photo || `https://ui-avatars.com/api/?name=${encodeURIComponent(m.name)}&background=f59e0b&color=fff&bold=true`}"
                     class="w-14 h-14 rounded-2xl object-cover border-2 border-gold-200/50"
                     alt="${Utils.escapeHtml(m.name)}"
                     onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(m.name)}&background=f59e0b&color=fff&bold=true'">
                <div>
                    <p class="font-semibold text-dark-500 text-sm">${Utils.escapeHtml(m.name)}</p>
                    <p class="text-xs text-dark-300">${Utils.escapeHtml(m.work_type)}</p>
                    <div class="flex items-center space-x-2 mt-1">
                        <span class="text-[11px] text-gold-600 font-medium">&starf; ${m.rating_avg || '0'}</span>
                        ${Utils.statusBadge(m.status || 'active', 'booking')}
                    </div>
                </div>
            </div>
            <div class="text-right">
                <p class="font-bold text-gold-600">${Utils.formatCurrency(m.rate)}</p>
                <p class="text-[11px] text-dark-300">/month</p>
            </div>
        </div>
    `).join('');
}

/**
 * Render activity list
 */
function renderActivity(activities) {
    if (!elements.activityList) return;

    if (!activities || !activities.length) {
        elements.activityList.innerHTML = '<p class="text-dark-300 text-sm text-center py-8">No recent activity</p>';
        return;
    }

    elements.activityList.innerHTML = activities.map(a => `
        <div class="p-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center
                    ${a.type === 'booking' ? 'bg-blue-50 text-blue-600' :
            a.type === 'payment' ? 'bg-green-50 text-green-600' : 'bg-cream-200 text-dark-400'}">
                    ${a.type === 'booking' ?
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/></svg>' :
                    a.type === 'payment' ?
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>' :
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'}
                </div>
                <div>
                    <p class="font-medium text-dark-500 text-sm">${Utils.escapeHtml(a.maid_name || a.reference || 'Activity')}</p>
                    <p class="text-[11px] text-dark-300">${Utils.timeAgo(a.date)}</p>
                </div>
            </div>
            ${a.amount ? `<p class="font-semibold text-gold-600 text-sm">${Utils.formatCurrency(a.amount)}</p>` : ''}
        </div>
    `).join('');
}

/**
 * Load bookings
 */
async function loadBookings() {
    store.setLoading(true);
    try {
        const data = await api.get('/bookings');

        if (!data.success || !data.bookings?.length) {
            if (elements.bookingsList) {
                elements.bookingsList.innerHTML = `
                    <div class="text-center py-10">
                        <div class="w-14 h-14 bg-cream-200 rounded-2xl mx-auto mb-3 flex items-center justify-center">
                            <svg class="w-6 h-6 text-dark-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/></svg>
                        </div>
                        <p class="text-dark-300 text-sm">No bookings yet</p>
                    </div>`;
            }
            store.setBookings([]);
            return;
        }

        store.setBookings(data.bookings);
    } catch (e) {
        console.error('Bookings load error:', e);
        toast.error('Failed to load bookings');
    } finally {
        store.setLoading(false);
    }
}

/**
 * Render bookings list
 */
function renderBookings(bookings) {
    if (!elements.bookingsList) return;

    if (!bookings || !bookings.length) {
        elements.bookingsList.innerHTML = '<p class="text-dark-300 text-sm text-center py-8">No bookings yet</p>';
        return;
    }

    elements.bookingsList.innerHTML = bookings.map(b => `
        <div class="glass-card rounded-2xl p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-mono text-dark-300">${Utils.escapeHtml(b.reference)}</span>
                ${Utils.statusBadge(b.status, 'booking')}
            </div>
            <div class="flex items-center space-x-3">
                <img src="${b.helper_photo || `https://ui-avatars.com/api/?name=${encodeURIComponent(b.helper_name)}&background=f59e0b&color=fff&bold=true`}"
                     class="w-12 h-12 rounded-xl object-cover"
                     alt="${Utils.escapeHtml(b.helper_name)}"
                     onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(b.helper_name)}&background=f59e0b&color=fff&bold=true'">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-dark-500 text-sm">${Utils.escapeHtml(b.helper_name)}</p>
                    <p class="text-xs text-dark-300">${Utils.escapeHtml(b.work_type || '')}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="font-bold text-gold-600 text-sm">${Utils.formatCurrency(b.monthly_rate || 0)}</p>
                    <p class="text-[11px] text-dark-300">${Utils.formatDate(b.created_at)}</p>
                </div>
            </div>
            ${b.status === 'pending' ? `
                <div class="mt-3 pt-3 border-t border-dark-100/50">
                    <button onclick="cancelBooking(${b.id})" class="w-full py-2.5 bg-red-50 text-red-500 rounded-xl text-sm font-semibold hover:bg-red-100 transition">
                        Cancel Booking
                    </button>
                </div>
            ` : ''}
        </div>
    `).join('');
}

/**
 * Cancel a booking
 */
window.cancelBooking = async function (bookingId) {
    if (!confirm('Are you sure you want to cancel this booking?')) return;

    try {
        const data = await api.post(`/bookings/${bookingId}/cancel`);
        if (data.success) {
            toast.success('Booking cancelled');
            loadBookings();
        } else {
            toast.error(data.error || 'Failed to cancel booking');
        }
    } catch (e) {
        toast.error('Failed to cancel booking');
    }
};

/**
 * Load rate list
 */
async function loadRateList() {
    store.setLoading(true);
    try {
        const data = await api.get('/bookings');

        const rateableBookings = (data.bookings || []).filter(b =>
            ['confirmed', 'completed', 'in_progress'].includes(b.status)
        );

        if (!rateableBookings.length) {
            if (elements.rateList) {
                elements.rateList.innerHTML = `
                    <div class="text-center py-10">
                        <div class="w-14 h-14 bg-cream-200 rounded-2xl mx-auto mb-3 flex items-center justify-center">
                            <svg class="w-6 h-6 text-dark-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        </div>
                        <p class="text-dark-300 text-sm">No maids to rate</p>
                    </div>`;
            }
            return;
        }

        if (elements.rateList) {
            elements.rateList.innerHTML = rateableBookings.map(b => `
                <div class="glass-card rounded-2xl p-4 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <img src="${b.helper_photo || `https://ui-avatars.com/api/?name=${encodeURIComponent(b.helper_name)}&background=f59e0b&color=fff&bold=true`}"
                             class="w-12 h-12 rounded-xl object-cover"
                             alt="${Utils.escapeHtml(b.helper_name)}">
                        <div>
                            <p class="font-semibold text-dark-500 text-sm">${Utils.escapeHtml(b.helper_name)}</p>
                            <p class="text-xs text-dark-300">${Utils.escapeHtml(b.work_type || '')}</p>
                        </div>
                    </div>
                    <button onclick="showRatingModal(${b.helper_id}, '${Utils.escapeHtml(b.helper_name).replace(/'/g, "\\'")}', ${b.id})"
                            class="px-4 py-2.5 btn-primary text-white rounded-xl text-sm font-semibold shadow-sm shadow-gold-500/20">
                        Rate
                    </button>
                </div>
            `).join('');
        }
    } catch (e) {
        console.error('Rate list load error:', e);
        toast.error('Failed to load maids');
    } finally {
        store.setLoading(false);
    }
}

/**
 * Show rating modal
 */
window.showRatingModal = function (helperId, helperName, bookingId) {
    if (!elements.ratingContent) return;

    elements.ratingContent.innerHTML = `
        <p class="text-dark-300 text-sm mb-5">How would you rate <strong class="text-dark-500">${Utils.escapeHtml(helperName)}</strong>?</p>
        <div class="flex justify-center space-x-3 mb-5" id="star-rating">
            ${[1, 2, 3, 4, 5].map(i => `
                <button onclick="setRating(${i})" class="star text-4xl text-dark-200 hover:text-gold-500 transition-colors" data-rating="${i}">&starf;</button>
            `).join('')}
        </div>
        <textarea id="rating-review" class="w-full bg-cream-100 border-0 rounded-xl p-4 mb-4 focus:ring-2 focus:ring-gold-500 focus:bg-white transition text-sm" rows="3" placeholder="Write a review (optional)"></textarea>
        <input type="hidden" id="rating-helper-id" value="${helperId}">
        <input type="hidden" id="rating-booking-id" value="${bookingId}">
        <input type="hidden" id="rating-value" value="0">
        <div class="flex space-x-2">
            <button onclick="submitRating()" class="flex-1 py-3 btn-primary text-white rounded-xl font-semibold text-sm">Submit</button>
            <button onclick="hideRatingModal()" class="flex-1 py-3 bg-cream-200 text-dark-500 rounded-xl font-semibold text-sm hover:bg-dark-100 transition">Cancel</button>
        </div>
    `;
    elements.ratingModal?.classList.add('active');
};

window.setRating = function (value) {
    document.getElementById('rating-value').value = value;
    document.querySelectorAll('.star').forEach((star, i) => {
        star.classList.toggle('text-gold-500', i < value);
        star.classList.toggle('text-dark-200', i >= value);
    });
};

window.submitRating = async function () {
    const helperId = document.getElementById('rating-helper-id')?.value;
    const bookingId = document.getElementById('rating-booking-id')?.value;
    const rating = document.getElementById('rating-value')?.value;
    const review = document.getElementById('rating-review')?.value || '';

    if (!rating || parseInt(rating) < 1) {
        toast.warning('Please select a rating');
        return;
    }

    try {
        const data = await api.post('/ratings', {
            helper_id: parseInt(helperId),
            booking_id: parseInt(bookingId),
            rating: parseInt(rating),
            review
        });

        if (data.success) {
            hideRatingModal();
            toast.success('Thank you for your rating!');
            loadRateList();
        } else {
            toast.error(data.error || 'Failed to submit rating');
        }
    } catch (e) {
        toast.error('Failed to submit rating');
    }
};

window.hideRatingModal = function () {
    elements.ratingModal?.classList.remove('active');
};

/**
 * Load settings
 */
function loadSettings() {
    const user = store.get('user');
    if (elements.settingsPhone) {
        elements.settingsPhone.textContent = Utils.formatPhone(user?.phone) || '-';
    }
}

/**
 * Logout
 */
window.logout = async function () {
    try {
        await api.post('/auth/logout');
    } catch (e) {
        // Ignore logout errors
    }

    api.clearTokens();
    store.reset();
    location.reload();
};

// Expose showPage globally
window.showPage = showPage;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initApp);
