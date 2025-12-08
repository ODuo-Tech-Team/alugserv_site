// ===== ADMIN HELPER FUNCTIONS - ALUGSERV =====

// Get API base URL dynamically
function getApiBase() {
    const path = window.location.pathname;
    const basePath = path.substring(0, path.indexOf('/admin'));
    return window.location.origin + basePath + '/api';
}

const API_BASE = getApiBase();

// Get token from localStorage
function getToken() {
    return localStorage.getItem('admin_token');
}

// Set token
function setToken(token) {
    localStorage.setItem('admin_token', token);
}

// Clear token
function clearToken() {
    localStorage.removeItem('admin_token');
    localStorage.removeItem('admin_user');
}

// Get user info
function getUser() {
    const userStr = localStorage.getItem('admin_user');
    return userStr ? JSON.parse(userStr) : null;
}

// Set user info
function setUser(user) {
    localStorage.setItem('admin_user', JSON.stringify(user));
}

// Check if authenticated
function isAuthenticated() {
    return !!getToken();
}

// Redirect to login if not authenticated
function requireAuth() {
    if (!isAuthenticated()) {
        window.location.href = 'login.html';
    }
}

// Logout
function logout() {
    clearToken();
    window.location.href = 'login.html';
}

// ===== API FUNCTIONS =====

// Make API request
async function apiRequest(endpoint, options = {}) {
    const token = getToken();
    const headers = {
        ...options.headers,
    };

    if (token && !options.noAuth) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    // Handle FormData (for file uploads)
    if (!(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    try {
        const response = await fetch(`${API_BASE}/${endpoint}`, {
            ...options,
            headers,
        });

        // Get response text first to debug
        const text = await response.text();

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON Parse Error:', e);
            console.error('Response text:', text);
            throw new Error('Resposta inválida do servidor (não é JSON válido)');
        }

        if (!response.ok) {
            if (response.status === 401) {
                clearToken();
                window.location.href = 'login.html';
                throw new Error('Sessão expirada');
            }
            throw new Error(data.message || data.error || 'Erro na requisição');
        }

        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// ===== UI FUNCTIONS =====

// Show alert
function showAlert(message, type = 'info', duration = 5000) {
    const container = document.getElementById('alertContainer') || document.body;
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;

    container.appendChild(alert);

    if (duration > 0) {
        setTimeout(() => {
            alert.remove();
        }, duration);
    }

    return alert;
}

// Confirm dialog
function confirmDialog(message) {
    return confirm(message);
}

// Format date
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
}

// Format currency
function formatCurrency(value) {
    if (!value) return '-';
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

// Truncate text
function truncate(text, length = 100) {
    if (!text) return '';
    return text.length > length ? text.substring(0, length) + '...' : text;
}

// Create slug from title
function createSlug(title) {
    return title
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^\w\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
}

// ===== MODAL FUNCTIONS =====

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Close modal on background click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

// ===== FILE PREVIEW =====

function previewImage(input, previewElementId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const preview = document.getElementById(previewElementId);
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ===== TABLE ACTIONS =====

function initializeTableActions() {
    // Delete buttons
    document.querySelectorAll('[data-action="delete"]').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const id = btn.dataset.id;
            const type = btn.dataset.type;

            if (confirmDialog('Tem certeza que deseja deletar este item?')) {
                try {
                    await apiRequest(`${type}.php?id=${id}`, { method: 'DELETE' });
                    showAlert('Item deletado com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } catch (error) {
                    showAlert(error.message, 'error');
                }
            }
        });
    });
}

// ===== INITIALIZE =====

document.addEventListener('DOMContentLoaded', () => {
    // Add logout listener
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (confirmDialog('Deseja realmente sair?')) {
                logout();
            }
        });
    }

    // Display user info
    const userNameEl = document.getElementById('userName');
    if (userNameEl) {
        const user = getUser();
        if (user) {
            userNameEl.textContent = user.username;
        }
    }

    // Initialize table actions
    initializeTableActions();
});
