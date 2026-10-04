var _seenNotifIds = [];

function pollNotifications() {
    fetch(window._notifPollUrl || '/student-companion/includes/notification_poll.php', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var badge = document.getElementById('notif-badge');
            if (!badge) return;

            if (data.count > 0) {
                badge.textContent = data.count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }

            // Show toast for truly new notifications
            if (data.notifications && data.notifications.length > 0) {
                data.notifications.forEach(function (n) {
                    if (_seenNotifIds.indexOf(n.id) === -1) {
                        _seenNotifIds.push(n.id);
                        showNotifToast(n.title, n.message);
                    }
                });
            }
        })
        .catch(function () {});
}

function showNotifToast(title, message) {
    var container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    var id = 'toast-' + Date.now();
    var html = '<div id="' + id + '" class="toast align-items-center text-bg-primary border-0" role="alert">'
        + '<div class="d-flex"><div class="toast-body"><strong>' + title + '</strong><br><small>' + message + '</small></div>'
        + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>';
    container.insertAdjacentHTML('beforeend', html);
    var toastEl = document.getElementById(id);
    var toast = new bootstrap.Toast(toastEl, { delay: 5000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', function () { toastEl.remove(); });
}

document.addEventListener('DOMContentLoaded', function () {
    pollNotifications();
    setInterval(pollNotifications, 15000);
});
