/**
 * Main Application JavaScript
 * Conference Interactive
 */

// Auto-refresh functionality for admin pages
function initAutoRefresh(interval = 10000) {
    const url = new URL(window.location.href);
    url.searchParams.set('_t', Date.now());
    
    setTimeout(() => {
        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Update content areas that might have changed
                const contentAreas = document.querySelectorAll('[data-refresh]');
                contentAreas.forEach(area => {
                    const id = area.id;
                    const newContent = doc.getElementById(id);
                    if (newContent) {
                        area.innerHTML = newContent.innerHTML;
                    }
                });
            })
            .catch(() => {});
    }, interval);
}

// Chart update helper
function updateChart(chart, newData) {
    chart.data.labels = Object.keys(newData);
    chart.data.datasets[0].data = Object.values(newData);
    chart.update('active');
}

// Toast notifications
function showToast(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed bottom-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-y-20 opacity-0 z-50`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-20', 'opacity-0');
    });
    
    setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Copy to clipboard
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showToast('Copied to clipboard!', 'success');
    } catch (err) {
        showToast('Failed to copy', 'error');
    }
}

// Form validation
function validateForm(form) {
    const inputs = form.querySelectorAll('[required]');
    let valid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            valid = false;
            input.classList.add('border-red-500');
        } else {
            input.classList.remove('border-red-500');
        }
    });
    
    return valid;
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Add data-refresh attribute to elements that should auto-update
    const autoRefreshContainers = document.querySelectorAll('.auto-refresh');
    autoRefreshContainers.forEach(el => el.setAttribute('data-refresh', 'true'));
    
    // Initialize form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            if (!validateForm(form)) {
                e.preventDefault();
                showToast('Please fill in all required fields', 'error');
            }
        });
    });
});
