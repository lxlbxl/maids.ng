/**
 * Maids.ng Admin Panel Application
 * Main entry point for the admin dashboard SPA
 */

// Initialize store and API
const store = new AdminStore();
const api = new ApiService('/admin/api');

// Chart instances
let revenueChart = null;
let bookingsChart = null;

// Pagination state
let currentPage = 1;
let searchTimeout = null;

// DOM Elements cache
const elements = {
    loginPage: null,
    mainApp: null,
    loginForm: null,
    loginError: null,
    adminName: null,
    sidebarAdminName: null,
    sidebarAvatar: null,
    modalContainer: null,
    modalContent: null
};

/**
 * Initialize application
 */
async function initApp() {
    cacheElements();
    setupEventListeners();
    setupStoreSubscriptions();
    updateDashboardDate();
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
    elements.adminName = document.getElementById('admin-name');
    elements.sidebarAdminName = document.getElementById('sidebar-admin-name');
    elements.sidebarAvatar = document.getElementById('sidebar-avatar');
    elements.modalContainer = document.getElementById('modal-container');
    elements.modalContent = document.getElementById('modal-content');
}

/**
 * Set up event listeners
 */
function setupEventListeners() {
    // Login form
    elements.loginForm?.addEventListener('submit', handleLogin);

    // Modal background click
    elements.modalContainer?.addEventListener('click', (e) => {
        if (e.target.id === 'modal-container') hideModal();
    });

    // Navigation delegation
    document.addEventListener('click', (e) => {
        const navBtn = e.target.closest('[data-page]');
        if (navBtn) {
            showPage(navBtn.dataset.page);
        }
    });
}

/**
 * Set up store subscriptions
 */
function setupStoreSubscriptions() {
    // React to admin changes
    store.subscribe('admin', (admin) => {
        if (admin) {
            const name = admin.name || 'Admin';
            if (elements.adminName) elements.adminName.textContent = name;
            if (elements.sidebarAdminName) elements.sidebarAdminName.textContent = name;
            if (elements.sidebarAvatar) elements.sidebarAvatar.textContent = name.charAt(0).toUpperCase();
        }
    });

    // React to KPIs changes
    store.subscribe('kpis', (kpis) => {
        if (kpis) updateDashboardStats(kpis);
    });

    // React to loading state
    store.subscribe('loading', (loading) => {
        document.body.classList.toggle('loading', loading);
    });
}

/**
 * Update dashboard date display
 */
function updateDashboardDate() {
    const dateEl = document.getElementById('dashboard-date');
    if (dateEl) {
        dateEl.textContent = new Date().toLocaleDateString('en-NG', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
    }
}

/**
 * Check authentication status
 */
async function checkAuth() {
    store.setLoading(true);
    try {
        const data = await api.get('/auth/me');
        if (data.success && data.admin) {
            store.setAdmin(data.admin, data.permissions || []);
            showMainApp();
            loadDashboard();
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

    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value;

    if (!email || !password) {
        showLoginError('Please enter email and password');
        return;
    }

    hideLoginError();
    store.setLoading(true);

    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.textContent = 'Signing in...';
    btn.disabled = true;

    try {
        const data = await api.post('/auth/login', { email, password });

        if (data.success) {
            if (data.tokens) {
                api.setTokens(data.tokens.access_token, data.tokens.refresh_token);
            }
            store.setAdmin(data.admin, data.permissions || []);
            showMainApp();
            loadDashboard();
            toast.success('Welcome back!');
        } else {
            showLoginError(data.error || 'Login failed');
        }
    } catch (e) {
        console.error('Login error:', e);
        showLoginError('Connection error. Please try again.');
    } finally {
        store.setLoading(false);
        btn.textContent = originalText;
        btn.disabled = false;
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
 * Navigate to a page
 */
function showPage(pageName) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const targetPage = document.getElementById(`${pageName}-page`);
    if (targetPage) {
        targetPage.classList.add('active');
    }

    // Update mobile bottom nav
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.querySelectorAll(`[data-page="${pageName}"]`).forEach(el => {
        if (el.classList.contains('nav-item')) el.classList.add('active');
    });

    // Update desktop sidebar
    document.querySelectorAll('.sidebar-link').forEach(n => {
        n.classList.remove('active');
        n.classList.add('text-dark-400');
    });
    const activeSidebar = document.querySelector(`[data-sidebar="${pageName}"]`);
    if (activeSidebar) {
        activeSidebar.classList.add('active');
        activeSidebar.classList.remove('text-dark-400');
    }

    store.setModule(pageName);

    // Load page data
    switch (pageName) {
        case 'dashboard': loadDashboard(); break;
        case 'helpers': loadHelpers(); break;
        case 'agencies': loadAgencies(); break;
        case 'bookings': loadBookings(); break;
        case 'payments': loadPayments(); break;
        case 'settings': loadSettings(); break;
    }
}

/**
 * Refresh current page
 */
window.refreshCurrentPage = function () {
    const activePage = document.querySelector('.page.active')?.id.replace('-page', '');
    if (activePage) showPage(activePage);
    toast.info('Refreshing...');
};

// ==================== DASHBOARD ====================

async function loadDashboard() {
    store.setLoading(true);
    try {
        const data = await api.get('/dashboard');

        if (data.success) {
            store.setKpis(data.stats);
            renderCharts(data.chart_data);
            renderActivity(data.recent_activity);
        }
    } catch (e) {
        console.error('Dashboard load error:', e);
        toast.error('Failed to load dashboard');
    } finally {
        store.setLoading(false);
    }
}

function updateDashboardStats(stats) {
    document.getElementById('stat-helpers').textContent = stats.total_helpers || 0;
    document.getElementById('stat-verified').textContent = stats.verified_helpers || 0;
    document.getElementById('stat-bookings').textContent = stats.total_bookings || 0;
    document.getElementById('stat-revenue').textContent = Utils.formatCurrency(stats.total_revenue || 0);
}

function renderCharts(chartData) {
    const labels = getLast7Days();

    // Warm theme chart defaults
    const chartDefaults = {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1a1614',
                titleColor: '#fef3c7',
                bodyColor: '#fff',
                cornerRadius: 12,
                padding: 12,
                bodyFont: { family: 'DM Sans' },
                titleFont: { family: 'DM Sans', weight: '600' }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: '#c9bba6', font: { family: 'DM Sans', size: 11 } }
            },
            y: {
                grid: { color: '#f0ece5', drawBorder: false },
                ticks: { color: '#c9bba6', font: { family: 'DM Sans', size: 11 } },
                beginAtZero: true
            }
        }
    };

    // Revenue chart
    const revenueData = labels.map(d => chartData?.revenue_7days?.[d] || 0);
    if (revenueChart) revenueChart.destroy();

    const revenueCanvas = document.getElementById('revenue-chart');
    if (revenueCanvas) {
        const ctx = revenueCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(245, 158, 11, 0.15)');
        gradient.addColorStop(1, 'rgba(245, 158, 11, 0)');

        revenueChart = new Chart(revenueCanvas, {
            type: 'line',
            data: {
                labels: labels.map(d => d.split('-')[2]),
                datasets: [{
                    label: 'Revenue',
                    data: revenueData,
                    borderColor: '#f59e0b',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#f59e0b',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                ...chartDefaults,
                scales: {
                    ...chartDefaults.scales,
                    y: {
                        ...chartDefaults.scales.y,
                        ticks: {
                            ...chartDefaults.scales.y.ticks,
                            callback: value => Utils.formatCurrency(value)
                        }
                    }
                }
            }
        });
    }

    // Bookings chart
    const bookingsData = labels.map(d => chartData?.bookings_7days?.[d] || 0);
    if (bookingsChart) bookingsChart.destroy();

    const bookingsCanvas = document.getElementById('bookings-chart');
    if (bookingsCanvas) {
        bookingsChart = new Chart(bookingsCanvas, {
            type: 'bar',
            data: {
                labels: labels.map(d => d.split('-')[2]),
                datasets: [{
                    label: 'Bookings',
                    data: bookingsData,
                    backgroundColor: 'rgba(59, 130, 246, 0.15)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: chartDefaults
        });
    }
}

function renderActivity(activities) {
    const container = document.getElementById('activity-list');
    if (!container) return;

    if (!activities?.length) {
        container.innerHTML = '<p class="text-dark-300 text-sm text-center py-6">No recent activity</p>';
        return;
    }

    container.innerHTML = activities.map(a => `
        <div class="list-item flex items-center justify-between py-3 px-3 rounded-xl cursor-default">
            <div class="flex items-center space-x-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-semibold
                    ${a.type === 'booking' ? 'bg-blue-50 text-blue-600' :
            a.type === 'payment' ? 'bg-green-50 text-green-600' : 'bg-purple-50 text-purple-600'}">
                    ${a.type === 'booking' ? 'B' : a.type === 'payment' ? 'P' : 'A'}
                </span>
                <div>
                    <p class="text-sm font-medium text-dark-500">${Utils.escapeHtml(a.title)}</p>
                    <p class="text-xs text-dark-300">${Utils.escapeHtml(a.subtitle || '')}</p>
                </div>
            </div>
            ${Utils.statusBadge(a.status, 'booking')}
        </div>
    `).join('');
}

// ==================== HELPERS ====================

async function loadHelpers(page = 1) {
    currentPage = page;
    store.setLoading(true);

    const status = document.getElementById('helper-filter-status')?.value || '';
    const verification = document.getElementById('helper-filter-verification')?.value || '';
    const search = document.getElementById('helper-search')?.value || '';

    const params = new URLSearchParams({ page, limit: 10 });
    if (status) params.set('status', status);
    if (verification) params.set('verification', verification);
    if (search) params.set('search', search);

    try {
        const data = await api.get(`/helpers?${params}`);

        if (data.success) {
            renderHelpers(data.data);
            renderPagination('helpers', data.meta);
        }
    } catch (e) {
        console.error('Helpers load error:', e);
        toast.error('Failed to load maids');
    } finally {
        store.setLoading(false);
    }
}

function renderHelpers(helpers) {
    const container = document.getElementById('helpers-list');
    if (!container) return;

    if (!helpers?.length) {
        container.innerHTML = `
            <div class="text-center py-12">
                <div class="w-16 h-16 bg-cream-200 rounded-2xl mx-auto mb-3 flex items-center justify-center">
                    <svg class="w-7 h-7 text-dark-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <p class="text-dark-300 text-sm">No maids found</p>
            </div>`;
        return;
    }

    container.innerHTML = helpers.map(h => `
        <div class="list-item glass-card rounded-2xl p-4 flex items-center justify-between cursor-pointer" onclick="showHelperDetail(${h.id})">
            <div class="flex items-center space-x-3">
                <img src="${h.profile_photo || `https://ui-avatars.com/api/?name=${encodeURIComponent(h.full_name)}&background=f59e0b&color=fff&bold=true`}"
                     class="w-12 h-12 rounded-xl object-cover"
                     alt="${Utils.escapeHtml(h.full_name)}"
                     onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(h.full_name)}&background=f59e0b&color=fff&bold=true'">
                <div>
                    <p class="font-semibold text-dark-500 text-sm">${Utils.escapeHtml(h.full_name)}</p>
                    <p class="text-xs text-dark-300">${Utils.escapeHtml(h.work_type)} &middot; ${Utils.escapeHtml(h.location || 'N/A')}</p>
                    <div class="flex items-center space-x-2 mt-1">
                        <span class="text-[11px] font-medium ${h.verification_status === 'verified' ? 'text-green-600' : 'text-gold-600'}">
                            ${h.verification_status === 'verified' ? '&#10003; Verified' : '&#9679; Pending'}
                        </span>
                        <span class="text-[11px] text-dark-300">&starf; ${h.rating_avg || '0'}</span>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm font-semibold text-gold-600">${Utils.formatCurrency(h.salary_min)}</p>
                ${Utils.statusBadge(h.status, 'booking')}
            </div>
        </div>
    `).join('');
}

window.showHelperDetail = async function (id) {
    try {
        const data = await api.get(`/helpers/${id}`);

        if (data.success) {
            const h = data.helper;
            showModal(`
                <div class="p-6">
                    <div class="flex items-center space-x-4 mb-6">
                        <img src="${h.profile_photo || `https://ui-avatars.com/api/?name=${encodeURIComponent(h.full_name)}&background=f59e0b&color=fff&bold=true`}"
                             class="w-16 h-16 rounded-2xl object-cover"
                             alt="${Utils.escapeHtml(h.full_name)}">
                        <div>
                            <h3 class="text-lg font-display font-bold text-dark-500">${Utils.escapeHtml(h.full_name)}</h3>
                            <p class="text-dark-300 text-sm">${Utils.formatPhone(h.phone)}</p>
                            <div class="flex items-center space-x-2 mt-1">
                                ${Utils.statusBadge(h.verification_status, 'verification')}
                                ${Utils.statusBadge(h.badge_level, 'badge')}
                            </div>
                        </div>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between py-2 border-b border-dark-100"><span class="text-dark-300">Work Type</span><span class="font-medium text-dark-500">${Utils.escapeHtml(h.work_type)}</span></div>
                        <div class="flex justify-between py-2 border-b border-dark-100"><span class="text-dark-300">Location</span><span class="font-medium text-dark-500">${Utils.escapeHtml(h.location || 'N/A')}</span></div>
                        <div class="flex justify-between py-2 border-b border-dark-100"><span class="text-dark-300">Accommodation</span><span class="font-medium text-dark-500">${Utils.escapeHtml(h.accommodation || 'N/A')}</span></div>
                        <div class="flex justify-between py-2 border-b border-dark-100"><span class="text-dark-300">Salary Range</span><span class="font-medium text-dark-500">${Utils.formatCurrency(h.salary_min)} - ${Utils.formatCurrency(h.salary_max)}</span></div>
                        <div class="flex justify-between py-2 border-b border-dark-100"><span class="text-dark-300">Rating</span><span class="font-medium text-dark-500">&starf; ${h.rating_avg} (${h.rating_count} reviews)</span></div>
                        <div class="flex justify-between py-2"><span class="text-dark-300">Skills</span><span class="font-medium text-dark-500">${(h.skills || []).join(', ') || 'N/A'}</span></div>
                    </div>
                    <div class="flex space-x-2 mt-6">
                        ${h.verification_status !== 'verified' ? `
                            <button onclick="verifyHelper(${h.id})" class="flex-1 py-3 bg-green-500 text-white rounded-xl text-sm font-semibold hover:bg-green-600 transition">Verify</button>
                        ` : ''}
                        <button onclick="updateHelperStatus(${h.id}, '${h.status === 'active' ? 'inactive' : 'active'}')"
                                class="flex-1 py-3 ${h.status === 'active' ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600'} text-white rounded-xl text-sm font-semibold transition">
                            ${h.status === 'active' ? 'Deactivate' : 'Activate'}
                        </button>
                        <button onclick="hideModal()" class="flex-1 py-3 bg-cream-200 text-dark-500 rounded-xl text-sm font-semibold hover:bg-dark-100 transition">Close</button>
                    </div>
                </div>
            `);
        }
    } catch (e) {
        console.error('Helper detail error:', e);
        toast.error('Failed to load maid details');
    }
};

window.verifyHelper = async function (id) {
    if (!confirm('Verify this maid?')) return;

    try {
        await api.put(`/helpers/${id}/verify`, { action: 'approve' });
        toast.success('Maid verified');
        hideModal();
        loadHelpers(currentPage);
    } catch (e) {
        toast.error('Failed to verify maid');
    }
};

window.updateHelperStatus = async function (id, status) {
    if (!confirm(`${status === 'inactive' ? 'Deactivate' : 'Activate'} this maid?`)) return;

    try {
        await api.put(`/helpers/${id}`, { status });
        toast.success(`Maid ${status === 'active' ? 'activated' : 'deactivated'}`);
        hideModal();
        loadHelpers(currentPage);
    } catch (e) {
        toast.error('Failed to update maid');
    }
};

// ==================== BOOKINGS ====================

async function loadBookings(page = 1) {
    currentPage = page;
    store.setLoading(true);

    const status = document.getElementById('booking-filter-status')?.value || '';
    const params = new URLSearchParams({ page, limit: 10 });
    if (status) params.set('status', status);

    try {
        const data = await api.get(`/bookings?${params}`);

        if (data.success) {
            renderBookings(data.data);
            renderPagination('bookings', data.meta);
        }
    } catch (e) {
        console.error('Bookings load error:', e);
        toast.error('Failed to load bookings');
    } finally {
        store.setLoading(false);
    }
}

function renderBookings(bookings) {
    const container = document.getElementById('bookings-list');
    if (!container) return;

    if (!bookings?.length) {
        container.innerHTML = `<div class="text-center py-12"><p class="text-dark-300 text-sm">No bookings found</p></div>`;
        return;
    }

    container.innerHTML = bookings.map(b => `
        <div class="glass-card rounded-2xl p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-mono text-dark-300">${Utils.escapeHtml(b.reference)}</span>
                ${Utils.statusBadge(b.status, 'booking')}
            </div>
            <div class="flex items-center space-x-3">
                <img src="${b.helper_photo || `https://ui-avatars.com/api/?name=${encodeURIComponent(b.helper_name)}&background=f59e0b&color=fff&bold=true`}"
                     class="w-10 h-10 rounded-xl object-cover"
                     alt="${Utils.escapeHtml(b.helper_name)}">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-dark-500 truncate">${Utils.escapeHtml(b.helper_name)}</p>
                    <p class="text-xs text-dark-300">${Utils.formatPhone(b.employer_phone)} &middot; ${Utils.escapeHtml(b.work_type)}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-sm font-semibold text-gold-600">${Utils.formatCurrency(b.service_fee)}</p>
                    <p class="text-[11px] text-dark-300">${Utils.formatDate(b.created_at)}</p>
                </div>
            </div>
        </div>
    `).join('');
}

// ==================== PAYMENTS ====================

async function loadPayments(page = 1) {
    currentPage = page;
    store.setLoading(true);

    try {
        const [paymentsRes, statsRes] = await Promise.all([
            api.get(`/payments?page=${page}&limit=10`),
            api.get('/payments/stats')
        ]);

        if (paymentsRes.success) {
            renderPayments(paymentsRes.data);
            renderPagination('payments', paymentsRes.meta);
        }

        if (statsRes.success) {
            document.getElementById('payment-today').textContent = Utils.formatCurrency(statsRes.stats.today_revenue || 0);
            document.getElementById('payment-month').textContent = Utils.formatCurrency(statsRes.stats.month_revenue || 0);
            document.getElementById('payment-total').textContent = Utils.formatCurrency(statsRes.stats.total_revenue || 0);
        }
    } catch (e) {
        console.error('Payments load error:', e);
        toast.error('Failed to load payments');
    } finally {
        store.setLoading(false);
    }
}

function renderPayments(payments) {
    const container = document.getElementById('payments-list');
    if (!container) return;

    if (!payments?.length) {
        container.innerHTML = `<div class="text-center py-12"><p class="text-dark-300 text-sm">No payments found</p></div>`;
        return;
    }

    container.innerHTML = payments.map(p => `
        <div class="glass-card rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-dark-500 text-sm truncate">${Utils.escapeHtml(p.tx_ref)}</p>
                    <p class="text-xs text-dark-300 mt-0.5">${Utils.escapeHtml(p.helper_name)} &middot; ${Utils.escapeHtml(p.gateway)}</p>
                </div>
                <div class="text-right ml-4 flex-shrink-0">
                    <p class="font-bold text-sm ${p.status === 'success' ? 'text-green-600' : p.status === 'pending' ? 'text-gold-600' : 'text-red-500'}">
                        ${Utils.formatCurrency(p.amount)}
                    </p>
                    ${Utils.statusBadge(p.status, 'payment')}
                </div>
            </div>
        </div>
    `).join('');
}

window.exportPayments = function () {
    window.open('/admin/api/payments/export', '_blank');
};

// ==================== AGENCIES ====================

async function loadAgencies(page = 1) {
    currentPage = page;
    store.setLoading(true);

    const search = document.getElementById('agency-search')?.value || '';
    const params = new URLSearchParams({ page, limit: 10 });
    if (search) params.set('search', search);

    try {
        const data = await api.get(`/agencies?${params}`);

        if (data.success) {
            renderAgencies(data.agencies);
        }
    } catch (e) {
        console.error('Agencies load error:', e);
        toast.error('Failed to load agencies');
    } finally {
        store.setLoading(false);
    }
}

function renderAgencies(agencies) {
    const container = document.getElementById('agencies-list');
    if (!container) return;

    if (!agencies?.length) {
        container.innerHTML = `<div class="text-center py-12"><p class="text-dark-300 text-sm">No agencies found</p></div>`;
        return;
    }

    container.innerHTML = agencies.map(a => `
        <div class="list-item glass-card rounded-2xl p-4 flex items-center justify-between cursor-pointer" onclick="showAgencyDetail(${a.id})">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-gold-100 to-gold-200 flex items-center justify-center">
                    <svg class="w-6 h-6 text-gold-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="font-semibold text-dark-500 text-sm">Agency #${a.id}</p>
                    <p class="text-xs text-dark-300">${Utils.formatPhone(a.phone)} &middot; ${Utils.escapeHtml(a.email || 'No Email')}</p>
                    <p class="text-[11px] text-dark-200 mt-0.5">Joined ${Utils.formatDate(a.created_at)}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold text-dark-500">${a.maid_count || 0} <span class="text-dark-300 font-normal text-xs">Maids</span></p>
                ${Utils.statusBadge(a.status, 'booking')}
            </div>
        </div>
    `).join('');
}

window.showAgencyDetail = async function (id) {
    try {
        const data = await api.get(`/agencies/${id}`);

        if (data.success) {
            const a = data.agency;
            const maids = a.maids || [];

            showModal(`
                <div class="p-6">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-14 h-14 bg-gradient-to-br from-gold-100 to-gold-200 rounded-2xl flex items-center justify-center text-gold-600 font-display font-bold text-xl">
                            A
                        </div>
                        <div>
                            <h3 class="text-lg font-display font-bold text-dark-500">Agency #${a.id}</h3>
                            <p class="text-dark-300 text-sm">${Utils.formatPhone(a.phone)}</p>
                            <div class="flex items-center space-x-2 mt-1">
                                ${Utils.statusBadge(a.status, 'badge')}
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h4 class="text-xs font-semibold text-dark-400 uppercase tracking-wider mb-3">Attached Maids (${maids.length})</h4>
                        <div class="max-h-40 overflow-y-auto space-y-2 border border-dark-100 rounded-xl p-3 bg-cream-50">
                            ${maids.length ? maids.map(m => `
                                <div class="flex items-center justify-between text-sm p-2 bg-white rounded-lg">
                                    <span class="font-medium text-dark-500">${Utils.escapeHtml(m.full_name)}</span>
                                    ${Utils.statusBadge(m.status, 'booking')}
                                </div>
                            `).join('') : '<p class="text-xs text-dark-300 text-center py-3">No maids attached</p>'}
                        </div>
                    </div>

                    <div class="flex space-x-2">
                        <button onclick="updateAgencyStatus(${a.id}, '${a.status === 'active' ? 'suspended' : 'active'}')"
                                class="flex-1 py-3 ${a.status === 'active' ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600'} text-white rounded-xl text-sm font-semibold transition">
                            ${a.status === 'active' ? 'Suspend' : 'Activate'}
                        </button>
                        <button onclick="deleteAgency(${a.id})" class="flex-1 py-3 bg-dark-500 text-white rounded-xl text-sm font-semibold hover:bg-dark-600 transition">Delete</button>
                        <button onclick="hideModal()" class="flex-1 py-3 bg-cream-200 text-dark-500 rounded-xl text-sm font-semibold hover:bg-dark-100 transition">Close</button>
                    </div>
                </div>
            `);
        }
    } catch (e) {
        console.error('Agency detail error:', e);
        toast.error('Failed to load agency details');
    }
};

window.updateAgencyStatus = async function (id, status) {
    if (!confirm(`${status === 'suspended' ? 'Suspend' : 'Activate'} this agency?`)) return;

    try {
        await api.put(`/agencies/${id}/verify`, { status });
        toast.success(`Agency ${status}`);
        hideModal();
        loadAgencies(currentPage);
    } catch (e) {
        toast.error('Failed to update agency');
    }
};

window.deleteAgency = async function (id) {
    if (!confirm('Delete this agency? This will also affect their maids.')) return;

    try {
        await api.delete(`/agencies/${id}`);
        toast.success('Agency deleted');
        hideModal();
        loadAgencies(currentPage);
    } catch (e) {
        toast.error('Failed to delete agency');
    }
};

// ==================== SETTINGS ====================

async function loadSettings() {
    store.setLoading(true);

    try {
        const [verRes, usersRes, rolesRes, settingsRes] = await Promise.all([
            api.get('/verifications?limit=5'),
            api.get('/users'),
            api.get('/roles'),
            api.get('/settings')
        ]);

        if (verRes.success) renderVerificationQueue(verRes.data);
        if (usersRes.success) renderAdminUsers(usersRes.data);
        if (rolesRes.success) renderRoles(rolesRes.data);

        if (settingsRes.success) {
            if (settingsRes.settings?.payments?.service_fee) {
                const fee = settingsRes.settings.payments.service_fee.value;
                const feeInput = document.getElementById('service-fee-input');
                if (feeInput) feeInput.value = fee.amount || 10000;
            }

            if (settingsRes.settings?.payments?.payment_flutterwave_public) {
                const pubKey = settingsRes.settings.payments.payment_flutterwave_public.value;
                const pubInput = document.getElementById('flw-public-key');
                if (pubInput) pubInput.value = pubKey || '';
            }

            if (settingsRes.settings?.payments?.payment_flutterwave_secret) {
                const secretInput = document.getElementById('flw-secret-key');
                if (secretInput && settingsRes.settings.payments.payment_flutterwave_secret.value) {
                    secretInput.placeholder = "Set to change (Hidden for security)";
                }
            }
        }
    } catch (e) {
        console.error('Settings load error:', e);
        toast.error('Failed to load settings');
    } finally {
        store.setLoading(false);
    }
}

function renderVerificationQueue(verifications) {
    const container = document.getElementById('verification-queue');
    const countEl = document.getElementById('pending-count');

    if (countEl) countEl.textContent = verifications?.length || 0;
    if (!container) return;

    if (!verifications?.length) {
        container.innerHTML = '<p class="text-dark-300 text-sm text-center py-4">No pending verifications</p>';
        return;
    }

    container.innerHTML = verifications.map(v => `
        <div class="flex items-center justify-between py-3 px-3 rounded-xl hover:bg-cream-100 transition">
            <div class="flex items-center space-x-3">
                <img src="${v.profile_photo || `https://ui-avatars.com/api/?name=${encodeURIComponent(v.helper_name)}&background=f59e0b&color=fff&bold=true`}"
                     class="w-9 h-9 rounded-lg object-cover">
                <div>
                    <p class="text-sm font-medium text-dark-500">${Utils.escapeHtml(v.helper_name)}</p>
                    <p class="text-[11px] text-dark-300">${Utils.escapeHtml(v.document_type)}</p>
                </div>
            </div>
            <div class="flex space-x-1.5">
                <button onclick="approveVerification(${v.id})" class="w-8 h-8 flex items-center justify-center bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition text-sm font-bold">&#10003;</button>
                <button onclick="rejectVerification(${v.id})" class="w-8 h-8 flex items-center justify-center bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition text-sm font-bold">&times;</button>
            </div>
        </div>
    `).join('');
}

function renderAdminUsers(users) {
    const container = document.getElementById('admin-users-list');
    if (!container) return;

    container.innerHTML = users.map(u => `
        <div class="flex items-center justify-between py-3 px-3 rounded-xl hover:bg-cream-100 transition">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-dark-400 to-dark-500 flex items-center justify-center text-white text-xs font-bold">${(u.name || 'A').charAt(0).toUpperCase()}</div>
                <div>
                    <p class="text-sm font-medium text-dark-500">${Utils.escapeHtml(u.name)}</p>
                    <p class="text-[11px] text-dark-300">${Utils.escapeHtml(u.email)} &middot; ${Utils.escapeHtml(u.role_name || 'No role')}</p>
                </div>
            </div>
            ${Utils.statusBadge(u.status, 'booking')}
        </div>
    `).join('');
}

function renderRoles(roles) {
    const container = document.getElementById('roles-list');
    if (!container) return;

    container.innerHTML = roles.map(r => `
        <div class="flex items-center justify-between py-3 px-3 rounded-xl hover:bg-cream-100 transition">
            <div>
                <p class="text-sm font-medium text-dark-500">${Utils.escapeHtml(r.name)}</p>
                <p class="text-[11px] text-dark-300">${Utils.escapeHtml(r.description || 'No description')}</p>
            </div>
            <span class="text-xs text-dark-300 bg-cream-200 px-2.5 py-1 rounded-full font-medium">${r.user_count} users</span>
        </div>
    `).join('');
}

window.approveVerification = async function (id) {
    try {
        await api.post(`/verifications/${id}/approve`);
        toast.success('Verification approved');
        loadSettings();
    } catch (e) {
        toast.error('Failed to approve verification');
    }
};

window.rejectVerification = async function (id) {
    const reason = prompt('Rejection reason:');
    if (!reason) return;

    try {
        await api.post(`/verifications/${id}/reject`, { reason });
        toast.success('Verification rejected');
        loadSettings();
    } catch (e) {
        toast.error('Failed to reject verification');
    }
};

window.updateServiceFee = async function () {
    const amount = document.getElementById('service-fee-input')?.value;
    if (!amount) return;

    try {
        await api.put('/settings/service_fee', {
            value: { amount: parseInt(amount), currency: 'NGN' },
            category: 'payments'
        });
        toast.success('Service fee updated');
    } catch (e) {
        toast.error('Failed to update service fee');
    }
};

window.updatePaymentKeys = async function () {
    const pubKey = document.getElementById('flw-public-key')?.value;
    const secretKey = document.getElementById('flw-secret-key')?.value;

    if (!pubKey && !secretKey) {
        toast.info('No changes to save');
        return;
    }

    try {
        const promises = [];

        if (pubKey) {
            promises.push(api.put('/settings/payment_flutterwave_public', {
                value: pubKey,
                category: 'payments'
            }));
        }

        if (secretKey) {
            promises.push(api.put('/settings/payment_flutterwave_secret', {
                value: secretKey,
                category: 'payments'
            }));
        }

        await Promise.all(promises);
        toast.success('Payment keys updated');

        if (secretKey) {
            const secretInput = document.getElementById('flw-secret-key');
            if (secretInput) {
                secretInput.value = '';
                secretInput.placeholder = "Set to change (Hidden for security)";
            }
        }
    } catch (e) {
        console.error('Update payment keys error:', e);
        toast.error('Failed to update payment keys');
    }
};

window.showAddAdminModal = function () {
    showModal(`
        <div class="p-6">
            <h3 class="text-lg font-display font-bold text-dark-500 mb-5">Add Admin User</h3>
            <form id="add-admin-form" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-dark-400 uppercase tracking-wider mb-2">Name</label>
                    <input type="text" id="new-admin-name" placeholder="Full name" required class="w-full px-4 py-3 bg-cream-100 border-0 rounded-xl focus:ring-2 focus:ring-gold-500 focus:bg-white transition text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-dark-400 uppercase tracking-wider mb-2">Email</label>
                    <input type="email" id="new-admin-email" placeholder="admin@maids.ng" required class="w-full px-4 py-3 bg-cream-100 border-0 rounded-xl focus:ring-2 focus:ring-gold-500 focus:bg-white transition text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-dark-400 uppercase tracking-wider mb-2">Password</label>
                    <input type="password" id="new-admin-password" placeholder="Create password" required class="w-full px-4 py-3 bg-cream-100 border-0 rounded-xl focus:ring-2 focus:ring-gold-500 focus:bg-white transition text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-dark-400 uppercase tracking-wider mb-2">Role</label>
                    <select id="new-admin-role" class="w-full px-4 py-3 bg-cream-100 border-0 rounded-xl focus:ring-2 focus:ring-gold-500 text-sm">
                        <option value="2">Admin</option>
                        <option value="3">Staff</option>
                        <option value="4">Viewer</option>
                    </select>
                </div>
                <div class="flex space-x-2 pt-2">
                    <button type="submit" class="flex-1 py-3 btn-primary text-white rounded-xl text-sm font-semibold">Create User</button>
                    <button type="button" onclick="hideModal()" class="flex-1 py-3 bg-cream-200 text-dark-500 rounded-xl text-sm font-semibold hover:bg-dark-100 transition">Cancel</button>
                </div>
            </form>
        </div>
    `);

    document.getElementById('add-admin-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await api.post('/users', {
                name: document.getElementById('new-admin-name').value,
                email: document.getElementById('new-admin-email').value,
                password: document.getElementById('new-admin-password').value,
                role_id: parseInt(document.getElementById('new-admin-role').value)
            });
            toast.success('Admin user created');
            hideModal();
            loadSettings();
        } catch (e) {
            toast.error('Failed to create admin user');
        }
    });
};

// ==================== UTILITIES ====================

function getLast7Days() {
    const dates = [];
    for (let i = 6; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        dates.push(d.toISOString().split('T')[0]);
    }
    return dates;
}

function renderPagination(type, meta) {
    const container = document.getElementById(`${type}-pagination`);
    if (!container) return;

    if (!meta || meta.pages <= 1) {
        container.innerHTML = '';
        return;
    }

    const loadFn = type === 'helpers' ? 'loadHelpers' :
        type === 'bookings' ? 'loadBookings' : 'loadPayments';

    let html = '';
    const totalPages = Math.min(meta.pages, 10);

    // Previous button
    html += `<button onclick="${loadFn}(${Math.max(1, meta.page - 1)})" ${meta.page <= 1 ? 'disabled' : ''}
             class="w-9 h-9 rounded-xl flex items-center justify-center transition ${meta.page <= 1 ? 'text-dark-200 cursor-not-allowed' : 'text-dark-400 hover:bg-cream-200'}">
             <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
             </button>`;

    for (let i = 1; i <= totalPages; i++) {
        html += `<button onclick="${loadFn}(${i})"
                 class="w-9 h-9 rounded-xl text-sm font-medium transition ${i === meta.page ? 'btn-primary text-white shadow-sm' : 'text-dark-400 hover:bg-cream-200'}">${i}</button>`;
    }

    // Next button
    html += `<button onclick="${loadFn}(${Math.min(meta.pages, meta.page + 1)})" ${meta.page >= meta.pages ? 'disabled' : ''}
             class="w-9 h-9 rounded-xl flex items-center justify-center transition ${meta.page >= meta.pages ? 'text-dark-200 cursor-not-allowed' : 'text-dark-400 hover:bg-cream-200'}">
             <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
             </button>`;

    container.innerHTML = html;
}

window.debounceSearch = function (fn) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(fn, 300);
};

function showModal(content) {
    if (elements.modalContent) {
        elements.modalContent.innerHTML = content;
    }
    elements.modalContainer?.classList.add('active');
}

window.hideModal = function () {
    elements.modalContainer?.classList.remove('active');
};

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

// Expose functions globally
window.showPage = showPage;
window.loadHelpers = loadHelpers;
window.loadBookings = loadBookings;
window.loadPayments = loadPayments;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initApp);
