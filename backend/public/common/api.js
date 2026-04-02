// API Service for Maids.ng
const API_BASE_URL = '/api';

class ApiService {
  constructor() {
    this.baseUrl = API_BASE_URL;
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const token = localStorage.getItem('token');
    
    const headers = {
      'Content-Type': 'application/json',
      ...(token && { 'Authorization': `Bearer ${token}` }),
      ...options.headers
    };

    const config = {
      ...options,
      headers
    };

    try {
      const response = await fetch(url, config);
      
      if (!response.ok) {
        const error = await response.json().catch(() => ({ message: 'Request failed' }));
        throw new Error(error.message || `HTTP ${response.status}`);
      }
      
      return await response.json();
    } catch (error) {
      console.error('API request failed:', error);
      throw error;
    }
  }

  // Auth endpoints
  async login(role, email, password) {
    return this.request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ role, email, password })
    });
  }

  async register(data) {
    return this.request('/auth/register', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = '/';
  }

  // Household endpoints
  async searchHelpers(params = {}) {
    const query = new URLSearchParams(params).toString();
    return this.request(`/helpers?${query}`);
  }

  async getHelper(id) {
    return this.request(`/helpers/${id}`);
  }

  async sendHireRequest(helperId, jobDetails) {
    return this.request('/hire-requests', {
      method: 'POST',
      body: JSON.stringify({ helperId, ...jobDetails })
    });
  }

  async getHouseholdRequests() {
    return this.request('/household/requests');
  }

  // Helper endpoints
  async getHelperProfile() {
    return this.request('/helper/profile');
  }

  async updateHelperProfile(data) {
    return this.request('/helper/profile', {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async getHelperIncomingRequests() {
    return this.request('/helper/requests/incoming');
  }

  async respondToRequest(requestId, response) {
    return this.request(`/helper/requests/${requestId}`, {
      method: 'PUT',
      body: JSON.stringify({ response })
    });
  }

  async uploadDocument(file, type) {
    const token = localStorage.getItem('token');
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type);

    const response = await fetch(`${this.baseUrl}/helper/documents`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` },
      body: formData
    });

    if (!response.ok) throw new Error('Upload failed');
    return response.json();
  }

  // Agency endpoints
  async getAgencyDashboard() {
    return this.request('/agency/dashboard');
  }

  async getAgencyHelpers() {
    return this.request('/agency/helpers');
  }

  async addAgencyHelper(data) {
    return this.request('/agency/helpers', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async bulkUploadHelpers(csvFile) {
    const token = localStorage.getItem('token');
    const formData = new FormData();
    formData.append('file', csvFile);

    const response = await fetch(`${this.baseUrl}/agency/helpers/bulk`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` },
      body: formData
    });

    if (!response.ok) throw new Error('Bulk upload failed');
    return response.json();
  }

  async getAgencyRequests() {
    return this.request('/agency/requests');
  }

  async respondToAgencyRequest(requestId, status, notes) {
    return this.request(`/agency/requests/${requestId}`, {
      method: 'PUT',
      body: JSON.stringify({ status, notes })
    });
  }

  // Admin endpoints
  async getAdminDashboard() {
    return this.request('/admin/dashboard');
  }

  async getAllHelpers(filters = {}) {
    const query = new URLSearchParams(filters).toString();
    return this.request(`/admin/helpers?${query}`);
  }

  async createHelper(data) {
    return this.request('/admin/helpers', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async bulkActionHelpers(action, ids) {
    return this.request('/admin/helpers/bulk', {
      method: 'POST',
      body: JSON.stringify({ action, ids })
    });
  }

  async getAllAgencies(filters = {}) {
    const query = new URLSearchParams(filters).toString();
    return this.request(`/admin/agencies?${query}`);
  }

  async getAllHireRequests(filters = {}) {
    const query = new URLSearchParams(filters).toString();
    return this.request(`/admin/hire-requests?${query}`);
  }

  async updateHireRequest(requestId, status, notes) {
    return this.request(`/admin/hire-requests/${requestId}`, {
      method: 'PUT',
      body: JSON.stringify({ status, notes })
    });
  }
}

// Initialize global API instance
window.api = new ApiService();

// Auth helper functions
function requireAuth(roles = []) {
  const token = localStorage.getItem('token');
  const user = JSON.parse(localStorage.getItem('user') || '{}');
  
  if (!token) {
    window.location.href = '/';
    return false;
  }
  
  if (roles.length && !roles.includes(user.role)) {
    window.location.href = `/${user.role}/`;
    return false;
  }
  
  return true;
}

function getCurrentUser() {
  return JSON.parse(localStorage.getItem('user') || '{}');
}
