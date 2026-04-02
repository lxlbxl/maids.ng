/**
 * Maids.ng UI Utilities v2.0
 * Modern, accessible, and user-friendly
 */

// Toast Notification System
const Toast = {
  container: null,
  
  init() {
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.className = 'toast-container';
      document.body.appendChild(this.container);
    }
  },
  
  show(message, type = 'info', duration = 5000) {
    this.init();
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icons = {
      success: '✓',
      error: '✕',
      warning: '⚠',
      info: 'ℹ'
    };
    
    toast.innerHTML = `
      <span class="alert-icon">${icons[type] || icons.info}</span>
      <div class="toast-content">${this.escapeHtml(message)}</div>
      <button class="toast-close" onclick="Toast.dismiss(this.parentElement)">✕</button>
    `;
    
    this.container.appendChild(toast);
    
    if (duration > 0) {
      setTimeout(() => this.dismiss(toast), duration);
    }
    
    return toast;
  },
  
  success(message, duration) {
    return this.show(message, 'success', duration);
  },
  
  error(message, duration) {
    return this.show(message, 'error', duration);
  },
  
  warning(message, duration) {
    return this.show(message, 'warning', duration);
  },
  
  info(message, duration) {
    return this.show(message, 'info', duration);
  },
  
  dismiss(toast) {
    if (toast && toast.parentElement) {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(100%)';
      setTimeout(() => toast.remove(), 150);
    }
  },
  
  clearAll() {
    if (this.container) {
      this.container.innerHTML = '';
    }
  },
  
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
};

// Global toast shortcut
const showToast = (message, type, duration) => Toast.show(message, type, duration);

// Loading Utilities
const Loading = {
  show(container, options = {}) {
    const { size = 'normal', text = '' } = options;
    const sizeClass = size === 'sm' ? 'spinner-sm' : size === 'lg' ? 'spinner-lg' : '';
    
    if (typeof container === 'string') {
      container = document.querySelector(container);
    }
    
    if (!container) return null;
    
    const wrapper = document.createElement('div');
    wrapper.className = 'loading-container';
    wrapper.innerHTML = `
      <div class="flex flex-col items-center gap-4">
        <div class="spinner ${sizeClass}"></div>
        ${text ? `<p class="text-secondary text-sm">${Toast.escapeHtml(text)}</p>` : ''}
      </div>
    `;
    
    container.innerHTML = '';
    container.appendChild(wrapper);
    return wrapper;
  },
  
  hide(container) {
    if (typeof container === 'string') {
      container = document.querySelector(container);
    }
    if (container) {
      container.innerHTML = '';
    }
  }
};

// Skeleton Loading Components
const Skeleton = {
  text(options = {}) {
    const { width = '100%', lines = 1, className = '' } = options;
    let html = '';
    for (let i = 0; i < lines; i++) {
      html += `<div class="skeleton skeleton-text" style="width: ${i === lines - 1 ? width : '100%'}; margin-bottom: ${i < lines - 1 ? '0.5rem' : 0}"></div>`;
    }
    return html;
  },
  
  card(options = {}) {
    const { variant = 'default', className = '' } = options;
    const base = 'skeleton-card';
    return `<div class="${base} ${className}">
      <div class="skeleton skeleton-text" style="height: 2rem; width: 60%; margin-bottom: 1rem;"></div>
      ${this.text({ lines: 3 })}
    </div>`;
  },
  
  avatar(options = {}) {
    const { size = 48, className = '' } = options;
    return `<div class="skeleton skeleton-avatar" style="width: ${size}px; height: ${size}px; ${className}"></div>`;
  }
};

// Date Formatting
const formatDate = (dateString, options = {}) => {
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return 'Invalid date';
  
  const defaultOptions = {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  };
  
  const fmt = { ...defaultOptions, ...options };
  return date.toLocaleDateString('en-NG', fmt);
};

const formatRelativeTime = (dateString) => {
  const date = new Date(dateString);
  const now = new Date();
  const diff = Math.floor((now - date) / 1000); // seconds
  
  if (diff < 60) return 'Just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
  return formatDate(dateString);
};

// Status Badge System
const getStatusBadge = (status) => {
  const statusLower = (status || '').toLowerCase();
  const map = {
    'approved': 'badge-success',
    'active': 'badge-success',
    'completed': 'badge-success',
    'verified': 'badge-success',
    'available': 'badge-success',
    'pending': 'badge-warning',
    'processing': 'badge-warning',
    'review': 'badge-warning',
    'onhold': 'badge-warning',
    'rejected': 'badge-danger',
    'cancelled': 'badge-danger',
    'expired': 'badge-danger',
    'suspended': 'badge-danger',
    'blocked': 'badge-danger',
    'inactive': 'badge-info',
    'draft': 'badge-info',
    'default': 'badge-info'
  };
  return map[statusLower] || map.default;
};

const formatStatus = (status) => {
  if (!status) return '';
  return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
};

// Mobile Menu Management
const MobileMenu = {
  toggleBtn: null,
  menu: null,
  
  init() {
    this.toggleBtn = document.querySelector('.mobile-menu-toggle');
    this.menu = document.querySelector('.mobile-menu');
    
    if (this.toggleBtn && this.menu) {
      this.toggleBtn.addEventListener('click', () => this.toggle());
      
      // Close on link click
      this.menu.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => this.close());
      });
      
      // Close on escape key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.menu.classList.contains('open')) {
          this.close();
        }
      });
      
      // Close on outside click
      document.addEventListener('click', (e) => {
        if (this.menu.classList.contains('open') && 
            !this.menu.contains(e.target) && 
            !this.toggleBtn.contains(e.target)) {
          this.close();
        }
      });
    }
  },
  
  toggle() {
    if (this.menu.classList.contains('open')) {
      this.close();
    } else {
      this.open();
    }
  },
  
  open() {
    if (this.menu && this.toggleBtn) {
      this.menu.classList.add('open');
      this.toggleBtn.classList.add('active');
      document.body.style.overflow = 'hidden';
      this.toggleBtn.setAttribute('aria-expanded', 'true');
    }
  },
  
  close() {
    if (this.menu && this.toggleBtn) {
      this.menu.classList.remove('open');
      this.toggleBtn.classList.remove('active');
      document.body.style.overflow = '';
      this.toggleBtn.setAttribute('aria-expanded', 'false');
    }
  }
};

// Header scroll effect
const Header = {
  init() {
    const header = document.querySelector('.header');
    if (!header) return;
    
    let lastScroll = 0;
    
    window.addEventListener('scroll', () => {
      const currentScroll = window.pageYOffset;
      
      if (currentScroll > 50) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
      
      lastScroll = currentScroll;
    }, { passive: true });
  }
};

// Active Navigation Link
const setActiveNav = (path) => {
  document.querySelectorAll('.nav-link').forEach(link => {
    link.classList.remove('active');
    if (link.getAttribute('href') === path) {
      link.classList.add('active');
    }
  });
};

// Form Validation Helpers
const Validator = {
  required(value) {
    return value && value.toString().trim().length > 0;
  },
  
  email(value) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(value);
  },
  
  phone(value) {
    const regex = /^\+?[1-9]\d{10,14}$/;
    return regex.test(value.replace(/\s/g, ''));
  },
  
  minLength(value, min) {
    return value && value.length >= min;
  },
  
  maxLength(value, max) {
    return !value || value.length <= max;
  },
  
  pattern(value, regex) {
    return regex.test(value);
  },
  
  validate(formData, rules) {
    const errors = {};
    
    for (const field in rules) {
      const value = formData.get(field) || formData[field];
      const fieldRules = rules[field];
      
      for (const rule of fieldRules) {
        let isValid = true;
        let message = '';
        
        if (rule === 'required' && !this.required(value)) {
          isValid = false;
          message = 'This field is required';
        } else if (rule.type === 'email' && !this.email(value)) {
          isValid = false;
          message = 'Please enter a valid email address';
        } else if (rule.type === 'phone' && !this.phone(value)) {
          isValid = false;
          message = 'Please enter a valid phone number';
        } else if (rule.type === 'min' && value < rule.value) {
          isValid = false;
          message = `Minimum value is ${rule.value}`;
        } else if (rule.type === 'max' && value > rule.value) {
          isValid = false;
          message = `Maximum value is ${rule.value}`;
        } else if (rule.type === 'minLength' && !this.minLength(value, rule.value)) {
          isValid = false;
          message = `Minimum length is ${rule.value} characters`;
        } else if (rule.type === 'maxLength' && !this.maxLength(value, rule.value)) {
          isValid = false;
          message = `Maximum length is ${rule.value} characters`;
        } else if (rule.type === 'pattern' && !this.pattern(value, rule.value)) {
          isValid = false;
          message = rule.message || 'Invalid format';
        }
        
        if (!isValid) {
          errors[field] = message;
          break;
        }
      }
    }
    
    return errors;
  }
};

// DOM Helpers
const $ = (selector, context = document) => context.querySelector(selector);
const $$ = (selector, context = document) => Array.from(context.querySelectorAll(selector));

// Debounce utility
const debounce = (func, wait) => {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
};

// Throttle utility
const throttle = (func, limit) => {
  let inThrottle;
  return function(...args) {
    if (!inThrottle) {
      func.apply(this, args);
      inThrottle = true;
      setTimeout(() => inThrottle = false, limit);
    }
  };
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  MobileMenu.init();
  Header.init();
  
  // Initialize toast container
  Toast.init();
  
  console.log('Maids.ng frontend initialized');
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    Toast,
    Loading,
    Skeleton,
    formatDate,
    formatRelativeTime,
    getStatusBadge,
    formatStatus,
    MobileMenu,
    Header,
    setActiveNav,
    Validator,
    $,
    $$,
    debounce,
    throttle
  };
}

// Global access
window.app = window.app || {};
window.app.Toast = Toast;
window.app.Loading = Loading;
window.app.Skeleton = Skeleton;
window.app.formatDate = formatDate;
window.app.formatRelativeTime = formatRelativeTime;
window.app.getStatusBadge = getStatusBadge;
window.app.formatStatus = formatStatus;
window.app.Validator = Validator;