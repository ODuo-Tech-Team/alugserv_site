/**
 * WooCommerce API Integration - AlugServ
 * Funções para comunicação com a API de produtos
 */

// Configuração da API
const WC_API = {
    base: window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '') + '/api',
    productsEndpoint: '/produtos.php',
    categoriesEndpoint: '/categorias.php'
};

// Detectar base URL corretamente
(function() {
    const path = window.location.pathname;
    const basePath = path.substring(0, path.lastIndexOf('/'));
    WC_API.base = window.location.origin + basePath + '/api';
})();

/**
 * Fazer requisição à API
 */
async function apiRequest(endpoint, params = {}) {
    let url = WC_API.base + endpoint;

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
    const data = await apiRequest(WC_API.categoriesEndpoint);
    return data.categories || [];
}

/**
 * Buscar categoria por slug
 */
async function getCategoryBySlug(slug) {
    const data = await apiRequest(WC_API.categoriesEndpoint, { slug });
    return data.category || null;
}

/**
 * Buscar categoria por ID
 */
async function getCategoryById(id) {
    const data = await apiRequest(WC_API.categoriesEndpoint, { id });
    return data.category || null;
}

// ===== PRODUTOS =====

/**
 * Buscar todos os produtos
 */
async function getProducts(page = 1, perPage = 12) {
    const data = await apiRequest(WC_API.productsEndpoint, { page, per_page: perPage });
    return data;
}

/**
 * Buscar produtos por categoria (slug)
 */
async function getProductsByCategory(categorySlug, page = 1, perPage = 12) {
    const data = await apiRequest(WC_API.productsEndpoint, {
        categoria: categorySlug,
        page,
        per_page: perPage
    });
    return data;
}

/**
 * Buscar produto por ID
 */
async function getProductById(id) {
    const data = await apiRequest(WC_API.productsEndpoint, { id });
    return data.product || null;
}

/**
 * Buscar produtos (search)
 */
async function searchProducts(term, page = 1, perPage = 12) {
    const data = await apiRequest(WC_API.productsEndpoint, {
        search: term,
        page,
        per_page: perPage
    });
    return data;
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
    if (!price || price === '' || price === '0') {
        return 'Consulte valor';
    }
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(price);
}

/**
 * Gerar link do WhatsApp para orçamento
 */
function getWhatsAppLink(product, phone = '5511999999999') {
    const message = encodeURIComponent(
        `Olá! Gostaria de solicitar um orçamento para o equipamento:\n\n` +
        `*${product.name}*\n` +
        `Categoria: ${product.category?.name || 'N/A'}\n\n` +
        `Aguardo retorno. Obrigado!`
    );
    return `https://wa.me/${phone}?text=${message}`;
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
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

/**
 * Placeholder de imagem
 */
function getPlaceholderImage() {
    return 'assets/images/placeholder-product.png';
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
        searchProducts,
        getUrlParam,
        formatPrice,
        getWhatsAppLink,
        truncateText,
        stripHtml
    };
}
