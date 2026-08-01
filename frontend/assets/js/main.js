
const Toast = {
    container: null,

    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            this.container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
            document.body.appendChild(this.container);
        }
    },

    show(type, title, message, duration = 4000) {
        this.init();
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-times-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };

        const bgColors = {
            success: '#d1fae5',
            error: '#fee2e2',
            warning: '#fef3c7',
            info: '#dbeafe'
        };

        const textColors = {
            success: '#047857',
            error: '#b91c1c',
            warning: '#b45309',
            info: '#1d4ed8'
        };

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `background:${bgColors[type] || '#fff'};color:${textColors[type] || '#0f172a'};border:1px solid rgba(0,0,0,0.05);padding:14px 18px;border-radius:12px;box-shadow:0 10px 25px -5px rgba(0,0,0,0.1);display:flex;align-items:center;gap:12px;min-width:280px;animation:fadeInDown 0.3s ease;`;

        toast.innerHTML = `
            <i class="${icons[type] || icons.info}" style="font-size:20px;"></i>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:14px;">${title}</div>
                <div style="font-size:12px;opacity:0.9;">${message}</div>
            </div>
            <button style="background:none;border:none;cursor:pointer;color:inherit;font-size:14px;" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        `;

        this.container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    success(title, message) { this.show('success', title, message); },
    error(title, message) { this.show('error', title, message); },
    warning(title, message) { this.show('warning', title, message); },
    info(title, message) { this.show('info', title, message); }
};

const Modal = {
    open(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('show', 'active');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    },

    close(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('show', 'active');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    },

    closeAll() {
        document.querySelectorAll('.modal.show, .modal.active, .modal-overlay.show, .modal-overlay.active').forEach(m => {
            m.classList.remove('show', 'active');
            m.style.display = 'none';
        });
        document.body.style.overflow = '';
    }
};

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay') || (e.target.classList.contains('modal') && (e.target.classList.contains('show') || e.target.classList.contains('active')))) {
        Modal.closeAll();
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') Modal.closeAll();
});

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
}

function toggleNotifications() {
    const dropdown = document.querySelector('.dropdown-menu');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

document.addEventListener('click', (e) => {
    const dropdown = document.querySelector('.dropdown-menu');
    const btn = document.querySelector('.notification-btn');
    if (dropdown && !dropdown.contains(e.target) && btn && !btn.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

async function ajaxRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {}
    };

    if (data instanceof FormData) {
        options.body = data;
    } else if (data) {
        options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        options.body = new URLSearchParams(data).toString();
    }

    try {
        const response = await fetch(url, options);
        const json = await response.json();
        return json;
    } catch (error) {
        console.error('AJAX Error:', error);
        Toast.error('Error', 'Something went wrong. Please try again.');
        return { success: false, message: error.message };
    }
}

function setupImagePreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);

    if (!input || !preview) return;

    input.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                Toast.error('File Too Large', 'Maximum file size is 5MB.');
                this.value = '';
                preview.style.display = 'none';
                return;
            }

            if (!file.type.startsWith('image/')) {
                Toast.error('Invalid File', 'Please select an image file.');
                this.value = '';
                preview.style.display = 'none';
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    });
}

function animateCounters() {
    const counters = document.querySelectorAll('[data-count]');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-count'));
        const duration = 1200;
        const start = 0;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(start + (target - start) * eased);
            counter.textContent = current.toLocaleString();
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        requestAnimationFrame(update);
    });
}

function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    let isValid = true;
    const required = form.querySelectorAll('[required]');

    form.querySelectorAll('.form-error').forEach(e => e.remove());

    required.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = 'var(--danger)';

            const error = document.createElement('div');
            error.className = 'form-error';
            error.style.cssText = 'color: var(--danger); font-size: 12px; margin-top: 4px;';
            error.innerHTML = '<i class="fas fa-exclamation-circle"></i> This field is required';
            field.parentElement.appendChild(error);

            field.addEventListener('input', function handler() {
                field.style.borderColor = '';
                const err = field.parentElement.querySelector('.form-error');
                if (err) err.remove();
                field.removeEventListener('input', handler);
            }, { once: true });
        }
    });

    return isValid;
}

function setupTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);

    if (!input || !table) return;

    input.addEventListener('input', function () {
        const term = this.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
}

async function markNotificationRead(notifId) {
    const backendUrl = document.querySelector('meta[name="backend-url"]')?.content || '../backend';
    await ajaxRequest(backendUrl + '/includes/mark_notification.php', 'POST', {
        notification_id: notifId
    });
}

async function markAllNotificationsRead() {
    const backendUrl = document.querySelector('meta[name="backend-url"]')?.content || '../backend';
    const result = await ajaxRequest(backendUrl + '/includes/mark_notification.php', 'POST', {
        mark_all: '1'
    });
    if (result.success) {
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.classList.remove('unread');
        });
        const badge = document.querySelector('.notification-badge');
        if (badge) badge.style.display = 'none';
        Toast.success('Done', 'All notifications marked as read');
    }
}

function togglePassword(inputId, iconElement) {
    const input = document.getElementById(inputId);
    if (!input) return;

    if (input.type === 'password') {
        input.type = 'text';
        iconElement.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        iconElement.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function confirmAction(title, message, callback) {
    let confirmModal = document.getElementById('global-confirm-modal');
    if (!confirmModal) {
        confirmModal = document.createElement('div');
        confirmModal.id = 'global-confirm-modal';
        confirmModal.className = 'modal-overlay';
        confirmModal.innerHTML = `
            <div class="modal" style="max-width: 440px;">
                <div class="modal-header">
                    <h3 id="g-confirm-title">Confirm Action</h3>
                    <button class="modal-close" onclick="Modal.close('global-confirm-modal')"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <p id="g-confirm-message" style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="Modal.close('global-confirm-modal')">Cancel</button>
                    <button type="button" class="btn btn-danger" id="g-confirm-btn">Confirm Proceed</button>
                </div>
            </div>
        `;
        document.body.appendChild(confirmModal);
    }

    document.getElementById('g-confirm-title').textContent = title || 'Confirm Action';
    document.getElementById('g-confirm-message').textContent = message || 'Are you sure you want to proceed?';

    const confirmBtn = document.getElementById('g-confirm-btn');
    const newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

    newBtn.addEventListener('click', async () => {
        Modal.close('global-confirm-modal');
        if (typeof callback === 'function') {
            await callback();
        }
    });

    Modal.open('global-confirm-modal');
}


document.addEventListener('DOMContentLoaded', () => {
    animateCounters();

    setupImagePreview('complaint_image', 'image_preview');
    setupImagePreview('repair_image', 'repair_preview');

    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

