/**
 * API Integration - AlugServ
 * Funções para comunicação com a API de equipamentos (MySQL)
 */

// Configuração da API
const API = {
    base: '',
    equipmentsEndpoint: '/equipments.php',
    categoriesEndpoint: '/categories.php'
};

// Detectar base URL corretamente
(function() {
    // Tentar detectar o caminho base do site
    const path = window.location.pathname;

    // Se estiver em uma subpasta (ex: /subpasta/produto.html)
    // O basePath seria /subpasta
    let basePath = path.substring(0, path.lastIndexOf('/'));

    // Se basePath estiver vazio, estamos na raiz
    if (!basePath) basePath = '';

    // Construir URL da API
    API.base = window.location.origin + basePath + '/api';

    // Debug (remover em produção)
    console.log('API Base URL:', API.base);
})();

/**
 * Fazer requisição à API
 */
async function apiRequest(endpoint, params = {}) {
    let url = API.base + endpoint;

    // Adicionar parâmetros de query
    const queryParams = new URLSearchParams(params).toString();
    if (queryParams) {
        url += '?' + queryParams;
    }

    try {
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('API Request Error:', error);
        throw error;
    }
}

// ===== CATEGORIAS =====

/**
 * Buscar todas as categorias
 */
async function getCategories() {
    const data = await apiRequest(API.categoriesEndpoint);
    return data.categories || [];
}

/**
 * Buscar categoria por slug
 */
async function getCategoryBySlug(slug) {
    const data = await apiRequest(API.categoriesEndpoint, { slug });
    return data.category || null;
}

/**
 * Buscar categoria por ID
 */
async function getCategoryById(id) {
    const data = await apiRequest(API.categoriesEndpoint, { id });
    return data.category || null;
}

// ===== EQUIPAMENTOS =====

/**
 * Buscar todos os equipamentos
 */
async function getProducts(page = 1, perPage = 12) {
    const data = await apiRequest(API.equipmentsEndpoint, { page, per_page: perPage });
    return {
        products: data.equipments || [],
        pagination: data.pagination || { page: 1, total: 0, total_pages: 0 }
    };
}

/**
 * Buscar equipamentos por categoria (slug)
 */
async function getProductsByCategory(categorySlug, page = 1, perPage = 12) {
    const data = await apiRequest(API.equipmentsEndpoint, {
        category: categorySlug,
        page,
        per_page: perPage
    });
    return {
        products: data.equipments || [],
        category: data.category || null,
        pagination: data.pagination || { page: 1, total: 0, total_pages: 0 }
    };
}

/**
 * Buscar equipamento por ID
 */
async function getProductById(id) {
    const data = await apiRequest(API.equipmentsEndpoint, { id });
    return data.equipment || null;
}

/**
 * Buscar equipamento por slug
 */
async function getProductBySlug(slug) {
    const data = await apiRequest(API.equipmentsEndpoint, { slug });
    return data.equipment || null;
}

/**
 * Buscar equipamentos (search)
 */
async function searchProducts(term, page = 1, perPage = 12) {
    const data = await apiRequest(API.equipmentsEndpoint, {
        search: term,
        page,
        per_page: perPage
    });
    return {
        products: data.equipments || [],
        search_term: data.search_term || term,
        pagination: data.pagination || { page: 1, total: 0, total_pages: 0 }
    };
}

/**
 * Buscar equipamentos em destaque
 */
async function getFeaturedProducts(limit = 6) {
    const data = await apiRequest(API.equipmentsEndpoint, {
        featured: 1,
        per_page: limit
    });
    return data.equipments || [];
}

// ===== HELPERS =====

/**
 * Obter parâmetro da URL
 */
function getUrlParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
}

/**
 * Formatar preço para exibição
 */
function formatPrice(price) {
    if (!price || price === '' || price === '0' || price === 0) {
        return 'Consulte valor';
    }
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(price);
}

/**
 * Gerar link do WhatsApp para orçamento - Louveira
 */
function getWhatsAppLinkLouveira(product) {
    return 'https://wa.link/sxon0i';
}

/**
 * Gerar link do WhatsApp para orçamento - Jundiaí
 */
function getWhatsAppLinkJundiai(product) {
    return 'https://wa.link/uqms8y';
}

/**
 * Truncar texto
 */
function truncateText(text, maxLength = 100) {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

/**
 * Remover tags HTML
 */
function stripHtml(html) {
    if (!html) return '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

/**
 * Placeholder de imagem
 */
function getPlaceholderImage() {
    return 'assets/images/logo.png';
}

/**
 * Criar card de produto HTML
 */
function createProductCard(product) {
    const imageUrl = product.image || getPlaceholderImage();
    const shortDesc = truncateText(stripHtml(product.short_description || product.description), 80);

    return `
        <a href="produto.html?slug=${product.slug}" class="product-card">
            <div class="product-image">
                <img src="${imageUrl}" alt="${product.name}" loading="lazy" onerror="this.src='${getPlaceholderImage()}'">
                ${product.featured ? '<span class="product-badge">Destaque</span>' : ''}
            </div>
            <div class="product-info">
                <span class="product-category">${product.category_name || ''}</span>
                <h3 class="product-title">${product.name}</h3>
                <p class="product-description">${shortDesc}</p>
                <div class="product-footer">
                    <span class="product-price">${formatPrice(product.price)}</span>
                    <span class="product-cta">Ver detalhes</span>
                </div>
            </div>
        </a>
    `;
}

/**
 * Renderizar paginação
 */
function renderPagination(pagination, containerSelector, callback) {
    const container = document.querySelector(containerSelector);
    if (!container || pagination.total_pages <= 1) {
        if (container) container.innerHTML = '';
        return;
    }

    let html = '<div class="pagination">';

    // Previous
    if (pagination.page > 1) {
        html += `<button class="page-btn" data-page="${pagination.page - 1}">&laquo; Anterior</button>`;
    }

    // Page numbers
    const start = Math.max(1, pagination.page - 2);
    const end = Math.min(pagination.total_pages, pagination.page + 2);

    if (start > 1) {
        html += `<button class="page-btn" data-page="1">1</button>`;
        if (start > 2) html += `<span class="page-ellipsis">...</span>`;
    }

    for (let i = start; i <= end; i++) {
        html += `<button class="page-btn ${i === pagination.page ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }

    if (end < pagination.total_pages) {
        if (end < pagination.total_pages - 1) html += `<span class="page-ellipsis">...</span>`;
        html += `<button class="page-btn" data-page="${pagination.total_pages}">${pagination.total_pages}</button>`;
    }

    // Next
    if (pagination.page < pagination.total_pages) {
        html += `<button class="page-btn" data-page="${pagination.page + 1}">Próximo &raquo;</button>`;
    }

    html += '</div>';
    container.innerHTML = html;

    // Add click handlers
    container.querySelectorAll('.page-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            callback(parseInt(btn.dataset.page));
        });
    });
}

// Exportar funções (para uso como módulo se necessário)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        getCategories,
        getCategoryBySlug,
        getCategoryById,
        getProducts,
        getProductsByCategory,
        getProductById,
        getProductBySlug,
        searchProducts,
        getFeaturedProducts,
        getUrlParam,
        formatPrice,
        getWhatsAppLinkLouveira,
        getWhatsAppLinkJundiai,
        truncateText,
        stripHtml,
        createProductCard,
        renderPagination
    };
}
