/**
 * CampusNest - Main JavaScript
 */

// API Base URL
const API_BASE_URL = '/php/api/';

/**
 * Make API request
 */
async function apiRequest(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        }
    };

    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(API_BASE_URL + endpoint, options);
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Request failed' };
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.maxWidth = '400px';

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 5000);
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

/**
 * Format date
 */
function formatDate(date, format = 'MMM d, Y') {
    const d = new Date(date);
    const month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][d.getMonth()];
    const day = d.getDate();
    const year = d.getFullYear();

    return `${month} ${day}, ${year}`;
}

/**
 * Validate email
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Initialize event listeners
 */
document.addEventListener('DOMContentLoaded', function () {
    // Add any global event listeners here
    console.log('CampusNest loaded');
});

/**
 * Search properties
 */
function searchProperties() {
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            const city = this.querySelector('[name="city"]').value;
            const type = this.querySelector('[name="property_type"]').value;

            let url = 'properties.php?';
            if (city) url += 'city=' + encodeURIComponent(city) + '&';
            if (type) url += 'property_type=' + encodeURIComponent(type);

            window.location.href = url;
        });
    }
}

/**
 * Handle booking form
 */
function handleBooking() {
    if (document.querySelector('.booking-shell')) {
        return;
    }

    const checkInInput = document.getElementById('check_in_date');
    const checkOutInput = document.getElementById('check_out_date');

    if (checkInInput && checkOutInput) {
        checkInInput.addEventListener('change', calculateTotal);
        checkOutInput.addEventListener('change', calculateTotal);
    }
}

/**
 * Calculate booking total
 */
function calculateTotal() {
    const checkIn = new Date(document.getElementById('check_in_date').value);
    const checkOut = new Date(document.getElementById('check_out_date').value);
    const pricePerMonth = parseFloat(document.querySelector('[data-price]')?.dataset.price || 0);

    if (checkIn && checkOut && checkOut > checkIn) {
        const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
        const pricePerNight = pricePerMonth / 30;
        const total = nights * pricePerNight;

        const totalElement = document.getElementById('total-price');
        if (totalElement) {
            totalElement.textContent = formatCurrency(total);
        }
    }
}

/**
 * Load more (pagination)
 */
function loadMore(page) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('page', page);
    window.location.href = currentUrl.toString();
}

/**
 * Toggle menu
 */
function toggleMenu() {
    const menu = document.querySelector('nav ul');
    if (menu) {
        menu.classList.toggle('active');
    }
}

/**
 * Handle form submission with validation
 */
function setupFormValidation() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const emailInputs = this.querySelectorAll('[type="email"]');

            for (let email of emailInputs) {
                if (!validateEmail(email.value)) {
                    e.preventDefault();
                    showNotification('Invalid email format', 'error');
                    return false;
                }
            }

            const passwordInputs = this.querySelectorAll('[type="password"]');
            for (let pwd of passwordInputs) {
                if (pwd.value.length < 6) {
                    e.preventDefault();
                    showNotification('Password must be at least 6 characters', 'error');
                    return false;
                }
            }
        });
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    setupFormValidation();
    searchProperties();
    handleBooking();
});

// Export functions for global use
window.apiRequest = apiRequest;
window.showNotification = showNotification;
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;
window.validateEmail = validateEmail;
window.toggleMenu = toggleMenu;
window.loadMore = loadMore;
