/**
 * Agency Dashboard Application
 * Maids.ng - Agency Portal
 */

const API_BASE = '/api/agency';

// State
const state = {
    user: null,
    stats: {},
    maids: [],
    pagination: { page: 1, limit: 10, total: 0, last: 1 }
};

let currentPage = 'overview';

// ========================= INIT =========================
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    setupEventListeners();
    showPage('overview');
});

// ========================= AUTH =========================
function checkAuth() {
    // Session-based auth; if API returns 401, redirect to login
}

function logout() {
    localStorage.removeItem('agency_token');
    window.location.href = 'login.html';
}

// ========================= EVENT LISTENERS =========================
function setupEventListeners() {
    // Sidebar navigation
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = e.currentTarget.dataset.page;
            if (page) showPage(page);
        });
    });

    // Logout
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) logoutBtn.addEventListener('click', logout);

    // Add Maid Form
    const addMaidForm = document.getElementById('add-maid-form');
    if (addMaidForm) addMaidForm.addEventListener('submit', handleAddMaid);

    // Profile Form
    const profileForm = document.getElementById('profile-form');
    if (profileForm) profileForm.addEventListener('submit', handleUpdateProfile);
}

// ========================= NAVIGATION =========================
function showPage(pageId) {
    currentPage = pageId;

    // Update sidebar active state
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.classList.remove('active');
        if (link.dataset.page === pageId) link.classList.add('active');
    });

    // Update bottom nav active state
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.page === pageId) item.classList.add('active');
    });

    // Toggle pages
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const target = document.getElementById(`${pageId}-page`);
    if (target) target.classList.add('active');

    // Update desktop page title
    const titles = { overview: 'Overview', maids: 'My Maids', profile: 'Agency Profile' };
    const pageTitle = document.getElementById('page-title');
    if (pageTitle) pageTitle.textContent = titles[pageId] || 'Dashboard';

    // Load data
    if (pageId === 'overview') loadOverview();
    if (pageId === 'maids') loadMaids();
    if (pageId === 'profile') loadProfile();
}

// ========================= DATA LOADING =========================
async function loadOverview() {
    try {
        const response = await fetch(`${API_BASE}/dashboard`);
        if (response.status === 401) return (window.location.href = 'login.html');

        const data = await response.json();

        if (data.success) {
            state.stats = data.stats;
            if (data.agency) {
                state.user = data.agency;
                updateAgencyInfo(data.agency);
            }
            updateOverviewUI(data);
        }
    } catch (e) {
        console.error('Failed to load overview:', e);
    }
}

async function loadMaids(page = 1) {
    try {
        const response = await fetch(`${API_BASE}/maids?page=${page}`);
        if (response.status === 401) return (window.location.href = 'login.html');

        const data = await response.json();

        if (data.success) {
            state.maids = data.data;
            state.pagination = {
                page: data.meta.current_page,
                limit: data.meta.per_page,
                total: data.meta.total,
                last: data.meta.last_page
            };
            renderMaidsTable();
            renderMaidsCards();
            renderPagination();
        }
    } catch (e) {
        console.error('Failed to load maids:', e);
    }
}

async function loadProfile() {
    try {
        const response = await fetch(`${API_BASE}/profile`);
        const data = await response.json();

        if (data.success && data.profile) {
            const phoneEl = document.getElementById('profile-phone');
            if (phoneEl) phoneEl.value = data.profile.phone || '';
        }
    } catch (e) {
        console.error('Failed to load profile:', e);
    }
}

// ========================= AGENCY INFO =========================
function updateAgencyInfo(agency) {
    const name = agency.business_name || agency.phone || 'Agency';
    const initial = name.charAt(0).toUpperCase();

    // Sidebar
    const sidebarInfo = document.getElementById('sidebar-agency-info');
    if (sidebarInfo) sidebarInfo.textContent = name;

    // Mobile header
    const mobileInfo = document.getElementById('agency-info');
    if (mobileInfo) mobileInfo.textContent = name;

    // Desktop header
    const desktopInfo = document.getElementById('desktop-agency-info');
    if (desktopInfo) desktopInfo.textContent = name;
}

// ========================= UI UPDATES =========================
function updateOverviewUI(data) {
    const { stats, recent_maids } = data;

    // Stats
    setText('stat-total-maids', stats.total_maids || 0);
    setText('stat-active-maids', stats.active_maids || 0);
    setText('stat-verified-maids', stats.verified_maids || 0);
    setText('stat-total-jobs', stats.total_jobs || 0);

    // Recent Maids
    const list = document.getElementById('recent-maids-list');
    if (!list) return;

    if (!recent_maids || recent_maids.length === 0) {
        list.innerHTML = `
            <div class="p-8 text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-cream-100 rounded-2xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-dark-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <p class="text-dark-300 text-sm">No maids added yet</p>
                <button onclick="showPage('maids')" class="text-gold-600 text-sm font-medium mt-2 hover:text-gold-700 transition">Add your first maid</button>
            </div>`;
        return;
    }

    list.innerHTML = recent_maids.map(maid => `
        <div class="px-5 py-4 flex items-center justify-between card-hover cursor-pointer" onclick="showPage('maids')">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-gold-100 to-gold-200 flex items-center justify-center text-gold-700 font-bold text-sm overflow-hidden flex-shrink-0">
                    ${maid.profile_photo ? `<img src="${maid.profile_photo}" class="w-full h-full object-cover">` : escapeHtml(maid.full_name.charAt(0))}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-dark-500 truncate">${escapeHtml(maid.full_name)}</p>
                    <p class="text-xs text-dark-300">Added ${formatDate(maid.created_at)}</p>
                </div>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full flex-shrink-0 ${getStatusBadge(maid.status)}">${maid.status}</span>
        </div>
    `).join('');
}

// ========================= MAIDS TABLE (Desktop) =========================
function renderMaidsTable() {
    const tbody = document.getElementById('maids-table-body');
    if (!tbody) return;

    if (state.maids.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 bg-cream-100 rounded-2xl flex items-center justify-center">
                <svg class="w-8 h-8 text-dark-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
            <p class="text-dark-300 text-sm">No maids found</p>
            <p class="text-dark-200 text-xs mt-1">Click "Add Maid" to get started</p>
        </td></tr>`;
        return;
    }

    tbody.innerHTML = state.maids.map(maid => `
        <tr class="hover:bg-cream-50 transition-colors">
            <td class="px-6 py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-gold-100 to-gold-200 overflow-hidden flex-shrink-0 flex items-center justify-center text-gold-700 font-bold text-sm">
                        ${maid.profile_photo ? `<img src="${maid.profile_photo}" class="w-full h-full object-cover">` : escapeHtml(maid.full_name.charAt(0))}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-dark-500">${escapeHtml(maid.full_name)}</p>
                        <p class="text-xs text-dark-300">${escapeHtml(maid.location || 'No location')}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full ${getStatusBadge(maid.status)}">${maid.status}</span>
            </td>
            <td class="px-6 py-4 text-sm text-dark-400">${escapeHtml(maid.work_type)}</td>
            <td class="px-6 py-4 text-sm text-dark-300">${formatDate(maid.created_at)}</td>
            <td class="px-6 py-4 text-right">
                <button onclick="editMaid(${maid.id})" class="text-gold-600 hover:text-gold-700 text-sm font-medium mr-3 transition">Edit</button>
                <button onclick="deleteMaid(${maid.id})" class="text-red-400 hover:text-red-600 text-sm font-medium transition">Delete</button>
            </td>
        </tr>
    `).join('');
}

// ========================= MAIDS CARDS (Mobile) =========================
function renderMaidsCards() {
    const container = document.getElementById('maids-card-list');
    if (!container) return;

    if (state.maids.length === 0) {
        container.innerHTML = `
            <div class="glass-card rounded-2xl p-8 text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-cream-100 rounded-2xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-dark-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <p class="text-dark-300 text-sm">No maids found</p>
                <p class="text-dark-200 text-xs mt-1">Tap "Add Maid" to get started</p>
            </div>`;
        return;
    }

    container.innerHTML = state.maids.map(maid => `
        <div class="glass-card rounded-2xl p-4 card-hover">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-gold-100 to-gold-200 overflow-hidden flex-shrink-0 flex items-center justify-center text-gold-700 font-bold">
                    ${maid.profile_photo ? `<img src="${maid.profile_photo}" class="w-full h-full object-cover">` : escapeHtml(maid.full_name.charAt(0))}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-dark-500 truncate">${escapeHtml(maid.full_name)}</p>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full flex-shrink-0 ml-2 ${getStatusBadge(maid.status)}">${maid.status}</span>
                    </div>
                    <div class="flex items-center space-x-3 mt-1">
                        <span class="text-xs text-dark-300">${escapeHtml(maid.work_type)}</span>
                        <span class="text-dark-200">·</span>
                        <span class="text-xs text-dark-300">${escapeHtml(maid.location || 'N/A')}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-dark-100/50">
                <span class="text-[11px] text-dark-300">Added ${formatDate(maid.created_at)}</span>
                <div class="flex items-center space-x-3">
                    <button onclick="editMaid(${maid.id})" class="text-gold-600 text-xs font-semibold hover:text-gold-700 transition">Edit</button>
                    <button onclick="deleteMaid(${maid.id})" class="text-red-400 text-xs font-semibold hover:text-red-600 transition">Delete</button>
                </div>
            </div>
        </div>
    `).join('');
}

// ========================= PAGINATION =========================
function renderPagination() {
    const { page, last } = state.pagination;

    // Desktop pagination
    const desktopPag = document.getElementById('pagination-controls');
    if (desktopPag) {
        if (last <= 1) {
            desktopPag.innerHTML = `<span class="text-xs text-dark-300">${state.pagination.total} maid${state.pagination.total !== 1 ? 's' : ''} total</span><div></div>`;
        } else {
            desktopPag.innerHTML = `
                <span class="text-xs text-dark-300">Page <span class="font-semibold text-dark-500">${page}</span> of <span class="font-semibold text-dark-500">${last}</span></span>
                <div class="flex space-x-2">
                    <button ${page <= 1 ? 'disabled' : ''} onclick="loadMaids(${page - 1})"
                        class="px-3 py-1.5 text-xs font-medium border border-dark-100 rounded-lg hover:bg-cream-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button ${page >= last ? 'disabled' : ''} onclick="loadMaids(${page + 1})"
                        class="px-3 py-1.5 text-xs font-medium border border-dark-100 rounded-lg hover:bg-cream-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>`;
        }
    }

    // Mobile pagination
    const mobilePag = document.getElementById('mobile-pagination');
    if (mobilePag) {
        if (last <= 1) {
            mobilePag.innerHTML = '';
        } else {
            mobilePag.innerHTML = `
                <button ${page <= 1 ? 'disabled' : ''} onclick="loadMaids(${page - 1})"
                    class="px-4 py-2.5 text-sm font-medium bg-white border border-dark-100 rounded-xl disabled:opacity-40 disabled:cursor-not-allowed transition card-hover">
                    Previous
                </button>
                <span class="flex items-center px-3 text-sm text-dark-300">${page} / ${last}</span>
                <button ${page >= last ? 'disabled' : ''} onclick="loadMaids(${page + 1})"
                    class="px-4 py-2.5 text-sm font-medium bg-white border border-dark-100 rounded-xl disabled:opacity-40 disabled:cursor-not-allowed transition card-hover">
                    Next
                </button>`;
        }
    }
}

// ========================= MODAL =========================
function showAddMaidModal(maid = null) {
    const modal = document.getElementById('add-maid-modal');
    const form = document.getElementById('add-maid-form');
    if (!modal || !form) return;

    const title = modal.querySelector('h3');
    const btn = form.querySelector('button[type="submit"]');

    form.reset();

    if (maid) {
        if (title) title.textContent = 'Edit Maid';
        if (btn) btn.textContent = 'Update Maid';
        document.getElementById('maid-id').value = maid.id;

        // Populate fields
        const fields = ['full_name', 'work_type', 'location', 'accommodation', 'salary_min', 'salary_max', 'experience_years', 'gender'];
        fields.forEach(f => {
            const el = form.querySelector(`[name="${f}"]`);
            if (el && maid[f] !== undefined) el.value = maid[f];
        });

        if (maid.date_of_birth) {
            const d = new Date(maid.date_of_birth);
            const dateEl = form.querySelector('[name="date_of_birth"]');
            if (dateEl) dateEl.value = d.toISOString().split('T')[0];
        }
    } else {
        if (title) title.textContent = 'Add New Maid';
        if (btn) btn.textContent = 'Add Maid';
        document.getElementById('maid-id').value = '';
    }

    modal.classList.remove('hidden');
}

function hideAddMaidModal() {
    const modal = document.getElementById('add-maid-modal');
    if (modal) modal.classList.add('hidden');
}

// ========================= MAID CRUD =========================
async function handleAddMaid(e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    const isEdit = document.getElementById('maid-id').value !== '';

    submitBtn.textContent = 'Saving...';
    submitBtn.disabled = true;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    const id = data.id;

    try {
        const url = isEdit ? `${API_BASE}/maids/${id}` : `${API_BASE}/maids`;
        const method = isEdit ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            if (typeof toast !== 'undefined') {
                toast.success(result.message || 'Saved successfully!');
            } else {
                alert(result.message || 'Saved successfully!');
            }
            hideAddMaidModal();
            loadMaids();
            loadOverview();
        } else {
            if (typeof toast !== 'undefined') {
                toast.error(result.error || 'Failed to save');
            } else {
                alert(result.error || 'Failed to save');
            }
        }
    } catch (err) {
        console.error('Error saving maid:', err);
        if (typeof toast !== 'undefined') {
            toast.error('An error occurred. Please try again.');
        } else {
            alert('An error occurred. Please try again.');
        }
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

window.editMaid = function (id) {
    const maid = state.maids.find(m => m.id === id);
    if (maid) showAddMaidModal(maid);
};

window.deleteMaid = async function (id) {
    if (!confirm('Are you sure you want to delete this maid? This action cannot be undone.')) return;

    try {
        const response = await fetch(`${API_BASE}/maids/${id}`, { method: 'DELETE' });
        const result = await response.json();

        if (result.success) {
            state.maids = state.maids.filter(m => m.id !== id);
            renderMaidsTable();
            renderMaidsCards();
            loadMaids(state.pagination.page);
            if (typeof toast !== 'undefined') toast.success('Maid deleted');
        } else {
            if (typeof toast !== 'undefined') {
                toast.error(result.error || 'Failed to delete');
            } else {
                alert(result.error || 'Failed to delete maid');
            }
        }
    } catch (e) {
        console.error('Delete failed:', e);
        if (typeof toast !== 'undefined') {
            toast.error('An error occurred');
        } else {
            alert('An error occurred');
        }
    }
};

// ========================= PROFILE =========================
async function handleUpdateProfile(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.textContent = 'Saving...';
    btn.disabled = true;

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    try {
        const response = await fetch(`${API_BASE}/profile`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            if (typeof toast !== 'undefined') {
                toast.success('Profile updated successfully');
            } else {
                alert('Profile updated successfully');
            }
            e.target.reset();
            loadProfile();
        } else {
            if (typeof toast !== 'undefined') {
                toast.error(result.error || 'Failed to update profile');
            } else {
                alert(result.error || 'Failed to update profile');
            }
        }
    } catch (err) {
        console.error('Profile update error:', err);
        if (typeof toast !== 'undefined') {
            toast.error('Error updating profile');
        } else {
            alert('Error updating profile');
        }
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

// ========================= HELPERS =========================
function getStatusBadge(status) {
    const map = {
        active: 'bg-green-100 text-green-700',
        inactive: 'bg-red-100 text-red-700',
        pending: 'bg-yellow-100 text-yellow-700',
        verified: 'bg-blue-100 text-blue-700'
    };
    return map[status] || 'bg-dark-100 text-dark-400';
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

// ========================= GLOBAL EXPORTS =========================
window.showPage = showPage;
window.loadMaids = loadMaids;
window.showAddMaidModal = () => showAddMaidModal(null);
window.hideAddMaidModal = hideAddMaidModal;
window.logout = logout;
