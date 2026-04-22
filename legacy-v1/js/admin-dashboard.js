/* Admin Dashboard Logic */

const apiService = new ApiService();

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    const token = localStorage.getItem('access_token');
    if (!token) {
        window.location.href = 'admin-login.html';
        return;
    }
    apiService.token = token;

    // Load initial data
    await loadDashboard();

    window.switchView = function (viewId) {
        // Update Sidebar / Bottom Nav
        const navIds = ['dashboard', 'helpers', 'agencies', 'users', 'payments', 'service-requests', 'settings'];

        navIds.forEach(id => {
            // Desktop Sidebar Links
            const desktopLink = document.querySelector(`nav a[onclick*="'${id}'"]`);
            if (desktopLink) {
                if (id === viewId) {
                    desktopLink.classList.add('bg-primary', 'text-white', 'shadow-lg', 'shadow-primary/20');
                    desktopLink.classList.remove('text-slate-600', 'hover:bg-slate-50');
                } else {
                    desktopLink.classList.remove('bg-primary', 'text-white', 'shadow-lg', 'shadow-primary/20');
                    desktopLink.classList.add('text-slate-600', 'hover:bg-slate-50');
                }
            }

            // Mobile Bottom Nav Links
            const mobileLink = document.querySelector(`.md\\:hidden a[onclick*="'${id}'"]`);
            if (mobileLink) {
                if (id === viewId) {
                    mobileLink.classList.add('text-primary');
                    mobileLink.classList.remove('text-slate-400');
                } else {
                    mobileLink.classList.remove('text-primary');
                    mobileLink.classList.add('text-slate-400');
                }
            }
        });

        // Toggle Views
        const sections = ['dashboard', 'helpers', 'agencies', 'users', 'payments', 'service-requests', 'settings'];
        sections.forEach(s => {
            const el = document.getElementById(`view-${s}`);
            if (el) el.classList.add('hidden');
        });

        const activeView = document.getElementById(`view-${viewId}`);
        if (activeView) activeView.classList.remove('hidden');

        // Update Page Title
        const titleEl = document.getElementById('current-view-title');
        if (titleEl) {
            const titles = {
                'dashboard': 'Overview',
                'helpers': 'Domestic Helpers',
                'agencies': 'Agency Partners',
                'users': 'Manage Users',
                'payments': 'Payment History',
                'service-requests': 'Service Requests',
                'settings': 'Settings',
            };
            titleEl.textContent = titles[viewId] || (viewId.charAt(0).toUpperCase() + viewId.slice(1));
        }

        // Load specific data
        if (viewId === 'helpers' || viewId === 'maids') loadMaids();
        if (viewId === 'agencies') loadAgencies();
        if (viewId === 'users') loadAdminUsers();
        if (viewId === 'settings') loadSettings();
        if (viewId === 'service-requests') loadServiceRequests(1);
        if (viewId === 'payments') loadPayments();
    }

    async function loadSettings() {
        try {
            const response = await apiService.get('/admin/settings');
            if (response.success && response.settings) {
                const settings = response.settings;
                // Settings are structured normally as settings.category.key_name.value
                
                // General Settings (Example)
                const general = settings.general || {};
                if (document.getElementById('setting-site-name') && general.site_name) {
                    document.getElementById('setting-site-name').value = general.site_name.value || '';
                }

                // Verification Settings
                const verification = settings.verification || {};
                if (document.getElementById('setting-qoreid_token') && verification.qoreid_token) {
                    document.getElementById('setting-qoreid_token').value = verification.qoreid_token.value || '';
                }
                if (document.getElementById('setting-qoreid_base_url') && verification.qoreid_base_url) {
                    document.getElementById('setting-qoreid_base_url').value = verification.qoreid_base_url.value || '';
                }

                // Payments Settings
                const payments = settings.payments || {};
                if (document.getElementById('fee-nin-amount')) {
                    const ninFee = payments.nin_verification_fee?.value || {amount: 5000};
                    document.getElementById('fee-nin-amount').value = ninFee.amount || 5000;
                }
                if (document.getElementById('fee-matching-amount')) {
                    const matchingFee = payments.matching_profile_fee?.value || {amount: 5000};
                    document.getElementById('fee-matching-amount').value = matchingFee.amount || 5000;
                }
                if (document.getElementById('setting-commission_percent')) {
                    const commissionPercent = payments.commission_percentage?.value || 0;
                    document.getElementById('setting-commission_percent').value = commissionPercent;
                }
            }
        } catch (error) {
            console.error('Error loading settings:', error);
        }
    }

    async function loadDashboard() {
        try {
            const response = await apiService.get('/admin/stats');
            if (response.success) {
                updateStats(response.data);
                renderRecentActivity(response.data.recent_activity);
            }
        } catch (error) {
            console.error('Error loading dashboard:', error);
        }
    }

    function updateStats(stats) {
        if (!stats) return;
        document.getElementById('stat-total-helpers').textContent = stats.total_helpers || 0;
        document.getElementById('stat-active-requests').textContent = stats.active_requests || 0;
        document.getElementById('stat-total-revenue').textContent = `₦${(stats.total_revenue || 0).toLocaleString()}`;
        document.getElementById('stat-avg-rating').textContent = stats.avg_rating || '0.0';
    }

    function renderRecentActivity(activity) {
        const container = document.getElementById('recent-activity');
        if (!container || !activity || !activity.length) return;

        container.innerHTML = activity.map(item => `
            <div class="flex items-start space-x-4 p-4 hover:bg-slate-50 transition-colors">
                <div class="p-2 bg-primary/10 rounded-full">
                    <i class="fas fa-${item.icon || 'circle'} text-primary text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-900">${item.title}</p>
                    <p class="text-xs text-slate-500 truncate">${item.description}</p>
                </div>
                <div class="text-xs text-slate-400 whitespace-nowrap">${item.time}</div>
            </div>
        `).join('');
    }

    window.saveBillingSettings = async function() {
        const ninAmount = document.getElementById('fee-nin-amount').value || 5000;
        const matchAmount = document.getElementById('fee-matching-amount').value || 5000;
        const commission = document.getElementById('setting-commission_percent').value || 0;

        const payload = {
            nin_verification_fee: { amount: parseInt(ninAmount), currency: 'NGN' },
            matching_profile_fee: { amount: parseInt(matchAmount), currency: 'NGN' },
            commission_percentage: parseInt(commission)
        };

        try {
            const response = await apiService.post('/admin/settings', payload);

            if (response.success) {
                alert('Billing settings updated successfully!');
            } else {
                alert('Error updating billing settings: ' + response.error);
            }
        } catch (e) {
            console.error(e);
            alert('Server error saving settings.');
        }
    };

    // Helpers Management
    let currentPage = 1;
    async function loadMaids(page = 1) {
        currentPage = page;
        const tbody = document.getElementById('helpers-tbody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center"><i class="fas fa-spinner fa-spin text-primary mr-2"></i>Loading helpers...</td></tr>';

        try {
            const search = document.getElementById('helper-search')?.value || '';
            const status = document.getElementById('helper-status-filter')?.value || '';
            const response = await apiService.get(`/admin/helpers?page=${page}&search=${search}&status=${status}`);

            if (response.success) {
                renderHelpersTable(response.data);
                updatePagination('helper-pagination', response.pagination, loadMaids);
            }
        } catch (error) {
            console.error('Error loading helpers:', error);
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-red-500">Failed to load helpers</td></tr>';
        }
    }

    function renderHelpersTable(helpers) {
        const tbody = document.getElementById('helpers-tbody');
        if (!tbody) return;

        if (!helpers.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">No helpers found</td></tr>';
            return;
        }

        tbody.innerHTML = helpers.map(helper => `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="h-10 w-10 flex-shrink-0">
                            <img class="h-10 w-10 rounded-full object-cover border-2 border-slate-100" src="${helper.photo_url || 'https://via.placeholder.com/40'}" alt="">
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-bold text-slate-900">${helper.full_name}</div>
                            <div class="text-xs text-slate-500">${helper.category || 'General'}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-slate-600">${helper.location || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-900">
                    ₦${(helper.salary_expectation || 0).toLocaleString()}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full ${helper.status === 'available' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'}">
                        ${(helper.status || 'unknown').toUpperCase()}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center text-amber-400">
                        <i class="fas fa-star text-xs mr-1"></i>
                        <span class="text-sm font-bold text-slate-700">${helper.rating || '5.0'}</span>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                    <div class="flex items-center justify-end space-x-2">
                        <button onclick="viewHelper(${helper.id})" class="p-2 text-primary hover:bg-primary/5 rounded-lg transition-colors" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="editHelper(${helper.id})" class="p-2 text-slate-400 hover:bg-slate-50 hover:text-slate-600 rounded-lg transition-colors" title="Edit Helper">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteHelper(${helper.id})" class="p-2 text-rose-400 hover:bg-rose-50 hover:text-rose-600 rounded-lg transition-colors" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Agencies Management
    async function loadAgencies() {
        const tbody = document.getElementById('agencies-table-body');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center">Loading agencies...</td></tr>';

        try {
            const response = await apiService.get('/admin/agencies');
            if (response.success) {
                renderAgenciesTable(response.data);
            }
        } catch (error) {
            console.error('Error loading agencies:', error);
            tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-10 text-center text-red-500">Failed to load agencies</td></tr>';
        }
    }

    function renderAgenciesTable(agencies) {
        const tbody = document.getElementById('agencies-table-body');
        if (!tbody) return;

        if (!agencies.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">No agencies found</td></tr>';
            return;
        }

        tbody.innerHTML = agencies.map(agency => `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-bold text-slate-900">${agency.name}</div>
                    <div class="text-xs text-slate-500">${agency.registration_number || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-slate-600">${agency.contact_person || 'N/A'}</div>
                    <div class="text-xs text-slate-500">${agency.phone || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-slate-600">${agency.email || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full ${agency.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'}">
                        ${(agency.status || 'pending').toUpperCase()}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                    <button onclick="window.viewAgency(${agency.id})" class="text-primary hover:text-blue-700 font-medium">Manage</button>
                    <button onclick="window.openVerifyAgencyModal(${agency.id}, '${agency.name}')" class="text-purple-600 hover:text-purple-800 ml-3"><i class="fas fa-check-circle"></i> Verify</button>
                </td>
            </tr>
        `).join('');
    }

    // Service Requests
    let srPage = 1;
    async function loadServiceRequests(page = 1) {
        srPage = page;
        const tbody = document.getElementById('service-requests-tbody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center">Loading requests...</td></tr>';

        try {
            const response = await apiService.get(`/admin/service-requests?page=${page}`);
            if (response.success) {
                renderServiceRequestsTable(response.data);
                updatePagination('sr-pagination', response.pagination, loadServiceRequests);
            }
        } catch (error) {
            console.error('Error loading service requests:', error);
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-red-500">Failed to load requests</td></tr>';
        }
    }

    function renderServiceRequestsTable(requests) {
        const tbody = document.getElementById('service-requests-tbody');
        if (!tbody) return;

        if (!requests || !requests.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">No service requests found</td></tr>';
            return;
        }

        tbody.innerHTML = requests.map(sr => `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">${new Date(sr.created_at).toLocaleDateString()}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-bold text-slate-900">${sr.full_name || 'N/A'}</div>
                    <div class="text-xs text-slate-500">${sr.phone || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">${sr.service_type || 'N/A'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">${sr.location || 'N/A'}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full ${sr.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}">
                        ${(sr.status || 'unknown').toUpperCase()}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                    <button onclick="window.viewServiceRequest(${sr.id})" class="text-rose-600 hover:text-rose-800 font-medium">Manage</button>
                </td>
            </tr>
        `).join('');
    }

    // Admin Users
    async function loadAdminUsers() {
        const tbody = document.getElementById('users-table-body');
        if (!tbody) return;

        try {
            const response = await apiService.get('/admin/users');
            if (response.success) {
                tbody.innerHTML = response.data.map(user => `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-bold text-slate-900">${user.username}</div>
                            <div class="text-xs text-slate-500">${user.email}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">${user.role || 'Admin'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">${new Date(user.created_at).toLocaleDateString()}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <button onclick="editUser(${user.id})" class="text-slate-400 hover:text-slate-600 mr-3"><i class="fas fa-edit"></i></button>
                            ${user.id !== 1 ? `<button onclick="deleteUser(${user.id})" class="text-rose-400 hover:text-rose-600"><i class="fas fa-trash"></i></button>` : ''}
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('Error loading admin users:', error);
        }
    }

    // Pagination Helper
    function updatePagination(id, pagination, loadFunc) {
        const container = document.getElementById(id);
        if (!container || !pagination) return;

        container.innerHTML = `
            <div class="flex items-center justify-between mt-4 px-4 py-3 bg-white border-t border-slate-100">
                <div class="flex-1 flex justify-between sm:hidden">
                    <button onclick="${loadFunc.name}(${pagination.current_page - 1})" ${pagination.current_page === 1 ? 'disabled' : ''} class="relative inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50">Previous</button>
                    <button onclick="${loadFunc.name}(${pagination.current_page + 1})" ${pagination.current_page === pagination.last_page ? 'disabled' : ''} class="ml-3 relative inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50">Next</button>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-slate-700">
                            Showing <span class="font-medium">${(pagination.current_page - 1) * pagination.per_page + 1}</span> to <span class="font-medium">${Math.min(pagination.current_page * pagination.per_page, pagination.total)}</span> of <span class="font-medium">${pagination.total}</span> results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <button onclick="${loadFunc.name}(${pagination.current_page - 1})" ${pagination.current_page === 1 ? 'disabled' : ''} class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            ${Array.from({ length: Math.min(5, pagination.last_page) }, (_, i) => {
                                const p = i + 1;
                                return `<button onclick="${loadFunc.name}(${p})" class="relative inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium ${p === pagination.current_page ? 'z-10 bg-primary/10 border-primary text-primary' : 'text-slate-500 hover:bg-slate-50'}">${p}</button>`;
                            }).join('')}
                            <button onclick="${loadFunc.name}(${pagination.current_page + 1})" ${pagination.current_page === pagination.last_page ? 'disabled' : ''} class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        `;
    }

    // Modal Helpers
    window.closeModal = function(id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    }

    window.openModal = function(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('hidden');
    }

    // Global Actions
    window.logout = function() {
        localStorage.removeItem('access_token');
        window.location.href = 'admin-login.html';
    }

    // Payments Implementation
    let paymentPage = 1;
    window.loadPayments = async function(page = 1) {
        paymentPage = page;
        const tbody = document.getElementById('payments-table-body');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center"><i class="fas fa-spinner fa-spin text-primary mr-2"></i>Loading transactions...</td></tr>';

        try {
            const status = document.getElementById('payment-status-filter')?.value || '';
            const search = document.getElementById('payment-search')?.value || '';
            
            const response = await apiService.get(`/admin/payments?page=${paymentPage}&status=${status}&search=${search}`);
            
            if (response.success) {
                renderPayments(response.data);
                updatePaymentPagination(response.pagination);
                updatePaymentStats(response.stats);
            }
        } catch (error) {
            console.error('Error loading payments:', error);
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-red-500">Failed to load payments</td></tr>';
        }
    };

    function renderPayments(payments) {
        const tbody = document.getElementById('payments-table-body');
        if (!tbody) return;
        
        if (!payments || !payments.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-slate-400 font-medium">No transactions found</td></tr>';
            return;
        }

        tbody.innerHTML = payments.map(p => {
            const date = new Date(p.created_at).toLocaleDateString('en-NG', {
                year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            const statusColors = {
                'success': 'bg-emerald-100 text-emerald-700',
                'pending': 'bg-amber-100 text-amber-700',
                'failed': 'bg-rose-100 text-rose-700'
            };

            const statusClass = statusColors[p.status] || 'bg-slate-100 text-slate-700';
            
            // Determine customer name
            let customerName = p.helper_name || p.sr_name || 'System';
            let customerPhone = p.employer_phone || p.sr_phone || '';

            return `
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">${date}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium text-slate-900">${customerName}</div>
                        <div class="text-xs text-slate-500">${customerPhone}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-600">
                            ${(p.type || 'payment').replace('_', ' ').toUpperCase()}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-900">
                        ₦${parseFloat(p.amount || 0).toLocaleString()}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full ${statusClass}">
                            ${(p.status || 'pending').toUpperCase()}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-mono text-slate-400">
                        ${p.tx_ref || 'N/A'}
                    </td>
                </tr>
            `;
        }).join('');
    }

    function updatePaymentPagination(pagination) {
        if (!pagination) return;
        const info = document.getElementById('payment-count-info');
        const prevBtn = document.getElementById('payment-prev-btn');
        const nextBtn = document.getElementById('payment-next-btn');

        if (info) {
            const start = (pagination.current_page - 1) * (pagination.per_page || 20) + 1;
            const end = Math.min(pagination.current_page * (pagination.per_page || 20), pagination.total);
            info.textContent = `Showing ${start}-${end} of ${pagination.total}`;
        }

        if (prevBtn) prevBtn.disabled = pagination.current_page <= 1;
        if (nextBtn) nextBtn.disabled = pagination.current_page >= pagination.last_page;
    }

    function updatePaymentStats(stats) {
        if (!stats) return;
        const totalEl = document.getElementById('stat-payments-total');
        const monthEl = document.getElementById('stat-payments-month');
        const rateEl = document.getElementById('stat-payments-rate');

        if (totalEl) totalEl.textContent = `₦${parseFloat(stats.total_revenue || 0).toLocaleString()}`;
        if (monthEl) monthEl.textContent = `₦${parseFloat(stats.monthly_revenue || 0).toLocaleString()}`;
        if (rateEl) rateEl.textContent = `${stats.success_rate || 0}%`;
    }

    window.exportPayments = function() {
        const token = localStorage.getItem('access_token');
        window.open(`${apiService.baseUrl}/admin/payments/export?token=${token}`, '_blank');
    };

    // --- Admin Users ---
    window.openCreateAdminModal = () => openModal('create-admin-modal');
    window.closeCreateAdminModal = () => closeModal('create-admin-modal');

    window.saveNewAdmin = async function() {
        const name = document.getElementById('new-admin-name').value;
        const email = document.getElementById('new-admin-email').value;
        const password = document.getElementById('new-admin-password').value;
        const role = document.getElementById('new-admin-role').value;

        if (!name || !email || !password) {
            alert('Please fill all fields');
            return;
        }

        try {
            const response = await apiService.post('/admin/users', { name, email, password, role });
            if (response.success) {
                alert('Admin user created successfully');
                closeCreateAdminModal();
                loadAdminUsers();
            } else {
                alert(response.error || 'Failed to create admin user');
            }
        } catch (error) {
            console.error('Error creating admin:', error);
            alert('An error occurred while creating admin user');
        }
    };

    // --- Agencies Verification ---
    let currentAgencyId = null;
    window.openVerifyAgencyModal = (id, name) => {
        currentAgencyId = id;
        document.getElementById('verify-agency-name').textContent = name;
        openModal('verify-agency-modal');
    };
    window.closeVerifyAgencyModal = () => closeModal('verify-agency-modal');

    window.confirmVerifyAgency = async function() {
        const status = document.getElementById('update-agency-status').value;
        if (!currentAgencyId) return;

        try {
            const response = await apiService.put(`/admin/agencies/${currentAgencyId}/verify`, { status });
            if (response.success) {
                alert('Agency status updated successfully');
                closeVerifyAgencyModal();
                loadAgencies();
            } else {
                alert(response.error || 'Failed to update agency');
            }
        } catch (error) {
            console.error('Error updating agency:', error);
            alert('An error occurred');
        }
    };

    // --- Service Requests Details ---
    let currentRequestId = null;
    window.viewServiceRequest = async function(id) {
        currentRequestId = id;
        const body = document.getElementById('sr-details-body');
        body.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Loading details...</div>';
        openModal('service-request-modal');

        try {
            const response = await apiService.get(`/admin/bookings/${id}`);
            if (response.success) {
                const sr = response.data;
                body.innerHTML = `
                    <div class="grid grid-cols-2 gap-4">
                        <div><p class="font-bold">Client Name</p><p>${sr.full_name || 'N/A'}</p></div>
                        <div><p class="font-bold">Phone</p><p>${sr.phone || 'N/A'}</p></div>
                        <div><p class="font-bold">Service Type</p><p>${sr.service_type || 'N/A'}</p></div>
                        <div><p class="font-bold">Location</p><p>${sr.location || 'N/A'}</p></div>
                        <div class="col-span-2"><p class="font-bold">Specific Requirements</p><p class="bg-slate-50 p-3 rounded-lg border border-slate-100 italic">"${sr.requirements || 'None provided'}"</p></div>
                        <div><p class="font-bold">Date Created</p><p>${new Date(sr.created_at).toLocaleString()}</p></div>
                        <div><p class="font-bold">Current Status</p><span class="px-2 py-1 text-xs font-bold rounded-full bg-slate-100 text-slate-700 uppercase">${sr.status}</span></div>
                    </div>
                `;
                document.getElementById('update-sr-status').value = sr.status;
            }
        } catch (error) {
            body.innerHTML = '<div class="text-center py-4 text-red-500">Failed to load request details</div>';
        }
    };

    window.closeServiceRequestModal = () => closeModal('service-request-modal');

    window.saveServiceRequestStatus = async function() {
        const status = document.getElementById('update-sr-status').value;
        if (!currentRequestId) return;

        try {
            const response = await apiService.put(`/admin/bookings/${currentRequestId}`, { status });
            if (response.success) {
                alert('Request status updated successfully');
                closeServiceRequestModal();
                loadServiceRequests(1);
            } else {
                alert(response.error || 'Failed to update status');
            }
        } catch (error) {
            console.error('Error updating request:', error);
            alert('An error occurred');
        }
    };
});
