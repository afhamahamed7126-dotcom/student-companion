// Theme toggle
function toggleTheme() {
    var html  = document.documentElement;
    var cur   = html.getAttribute('data-bs-theme');
    var next  = cur === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', next);
    localStorage.setItem('theme', next);
    var icon = document.getElementById('theme-icon');
    if (icon) icon.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon';
}

// Update theme icon on load
(function () {
    var theme = localStorage.getItem('theme') || 'light';
    var icon = document.getElementById('theme-icon');
    if (icon) icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon';
})();

// CSRF token from meta tag (for AJAX)
function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// Generic fetch POST helper with CSRF
function postJson(url, data) {
    data._csrf = getCsrfToken();
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
        body: JSON.stringify(data)
    }).then(function (r) { return r.json(); });
}

// Confirm before destructive actions
function confirmAction(msg) {
    return window.confirm(msg || 'Are you sure?');
}

// Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});

// Flash message auto-dismiss
document.addEventListener('DOMContentLoaded', function () {
    var alerts = document.querySelectorAll('.alert-auto-dismiss');
    alerts.forEach(function (a) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(a);
            if (bsAlert) bsAlert.close();
        }, 4000);
    });
});
