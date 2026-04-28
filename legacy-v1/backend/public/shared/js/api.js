/**
 * API Service - Centralized API communication layer
 * Handles all HTTP requests with error handling, token management, and retry logic
 */
class ApiService {
    constructor(baseUrl = '/api') {
        this.baseUrl = baseUrl;
        this.token = localStorage.getItem('access_token');
        this.refreshToken = localStorage.getItem('refresh_token');
        this.onUnauthorized = null;
    }

    setTokens(tokens) {
        if (tokens.access_token) {
            this.token = tokens.access_token;
            localStorage.setItem('access_token', tokens.access_token);
        }
        if (tokens.refresh_token) {
            this.refreshToken = tokens.refresh_token;
            localStorage.setItem('refresh_token', tokens.refresh_token);
        }
    }

    clearTokens() {
        this.token = null;
        this.refreshToken = null;
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        // Add auth token if available
        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers,
                credentials: 'include' // Include cookies for session auth
            });

            // Handle token expiry
            if (response.status === 401) {
                const data = await response.json();
                if (data.code === 'TOKEN_EXPIRED' && this.refreshToken) {
                    const refreshed = await this.refreshAccessToken();
                    if (refreshed) {
                        // Retry original request with new token
                        headers['Authorization'] = `Bearer ${this.token}`;
                        const retryResponse = await fetch(url, { ...options, headers });
                        return this.handleResponse(retryResponse);
                    }
                }

                if (this.onUnauthorized) {
                    this.onUnauthorized();
                }
                throw new ApiError('Unauthorized', 401, data);
            }

            return this.handleResponse(response);
        } catch (error) {
            if (error instanceof ApiError) throw error;
            throw new ApiError('Network error. Please check your connection.', 0, { network: true });
        }
    }

    async handleResponse(response) {
        const data = await response.json();

        if (!response.ok) {
            throw new ApiError(
                data.error || data.message || 'An error occurred',
                response.status,
                data
            );
        }

        return data;
    }

    async refreshAccessToken() {
        try {
            const response = await fetch(`${this.baseUrl}/auth/refresh`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ refresh_token: this.refreshToken })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.tokens) {
                    this.setTokens(data.tokens);
                    return true;
                }
            }
        } catch (e) {
            console.error('Token refresh failed:', e);
        }

        this.clearTokens();
        return false;
    }

    // Convenience methods
    get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    }

    post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
}

class ApiError extends Error {
    constructor(message, status, data = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

// Export for use in modules or global scope
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ApiService, ApiError };
} else {
    window.ApiService = ApiService;
    window.ApiError = ApiError;
}
