/**
 * Renderização de Produtos - AlugServ
 * Funções para exibir produtos e categorias nas páginas
 */

// ===== RENDERIZAÇÃO DE CATEGORIAS =====

/**
 * Renderizar grid de categorias
 */
function renderCategoriesGrid(categories, containerId = 'categoriesGrid') {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!categories || categories.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <p>Nenhuma categoria encontrada.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = categories.map(category => `
        <a href="categoria.html?slug=${category.slug}" class="category-card">
            <div class="category-image">
                ${category.image
                    ? `<img src="${category.image}" alt="${category.name}" loading="lazy">`
                    : `<div class="category-placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        </svg>
                    </div>`
                }
            </div>
            <div class="category-info">
                <h3 class="category-name">${category.name}</h3>
                ${category.count > 0 ? `<span class="category-count">${category.count} equipamento${category.count !== 1 ? 's' : ''}</span>` : ''}
            </div>
        </a>
    `).join('');
}

// ===== RENDERIZAÇÃO DE PRODUTOS =====

/**
 * Renderizar grid de produtos
 */
function renderProductsGrid(products, containerId = 'productsGrid') {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!products || products.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <h3>Nenhum equipamento encontrado</h3>
                <p>Tente buscar por outra categoria ou entre em contato conosco.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = products.map(product => renderProductCard(product)).join('');
}

/**
 * Renderizar card de produto individual
 */
function renderProductCard(product) {
    const imageUrl = product.image || getPlaceholderImage();
    const description = truncateText(stripHtml(product.short_description || product.description), 80);

    return `
        <a href="produto.html?id=${product.id}" class="product-card">
            <div class="product-image">
                <img src="${imageUrl}" alt="${product.name}" loading="lazy" onerror="this.src='${getPlaceholderImage()}'">
                ${!product.in_stock ? '<span class="product-badge out-of-stock">Indisponível</span>' : ''}
            </div>
            <div class="product-info">
                <h3 class="product-title">${product.name}</h3>
                ${description ? `<p class="product-description">${description}</p>` : ''}
                <div class="product-footer">
                    <span class="product-price">${formatPrice(product.price)}</span>
                    ${product.category ? `<span class="product-category">${product.category.name}</span>` : ''}
                </div>
            </div>
        </a>
    `;
}

// ===== PÁGINA DE CATEGORIA =====

/**
 * Inicializar página de categoria
 */
async function initCategoryPage() {
    const slug = getUrlParam('slug');
    if (!slug) {
        window.location.href = 'equipamentos.html';
        return;
    }

    showLoading('productsGrid');

    try {
        // Buscar produtos da categoria
        const data = await getProductsByCategory(slug);

        // Atualizar título e breadcrumb
        if (data.category) {
            document.title = `${data.category.name} - AlugServ`;
            updateBreadcrumb(data.category.name);
            updateCategoryHeader(data.category);
        }

        // Renderizar produtos
        renderProductsGrid(data.products);

        // Configurar paginação
        if (data.has_more) {
            setupPagination(slug, data.page);
        }

    } catch (error) {
        console.error('Erro ao carregar categoria:', error);
        showError('productsGrid', 'Erro ao carregar equipamentos. Tente novamente.');
    }
}

/**
 * Atualizar header da categoria
 */
function updateCategoryHeader(category) {
    const titleEl = document.getElementById('categoryTitle');
    const descEl = document.getElementById('categoryDescription');
    const imageEl = document.getElementById('categoryImage');

    if (titleEl) titleEl.textContent = category.name;
    if (descEl && category.description) descEl.textContent = stripHtml(category.description);
    if (imageEl && category.image) {
        imageEl.src = category.image;
        imageEl.style.display = 'block';
    }
}

// ===== PÁGINA DE PRODUTO =====

/**
 * Inicializar página de produto individual
 */
async function initProductPage() {
    const productId = getUrlParam('id');
    if (!productId) {
        window.location.href = 'equipamentos.html';
        return;
    }

    showLoading('productContent');

    try {
        const product = await getProductById(productId);

        if (!product) {
            showError('productContent', 'Produto não encontrado.');
            return;
        }

        document.title = `${product.name} - AlugServ`;
        updateBreadcrumb(product.category?.name, product.name);
        renderProductDetails(product);

    } catch (error) {
        console.error('Erro ao carregar produto:', error);
        showError('productContent', 'Erro ao carregar produto. Tente novamente.');
    }
}

/**
 * Renderizar detalhes do produto
 */
function renderProductDetails(product) {
    const container = document.getElementById('productContent');
    if (!container) return;

    const mainImage = product.image || getPlaceholderImage();
    const gallery = product.gallery || [];
    const description = product.description || product.short_description || '';
    const specs = product.specs || {};

    container.innerHTML = `
        <div class="product-detail-grid">
            <!-- Galeria -->
            <div class="product-gallery">
                <div class="gallery-main">
                    <img src="${mainImage}" alt="${product.name}" id="mainImage" onerror="this.src='${getPlaceholderImage()}'">
                </div>
                ${gallery.length > 1 ? `
                    <div class="gallery-thumbs">
                        ${gallery.map((img, index) => `
                            <button class="gallery-thumb ${index === 0 ? 'active' : ''}" onclick="changeMainImage('${img}', this)">
                                <img src="${img}" alt="${product.name} - Imagem ${index + 1}">
                            </button>
                        `).join('')}
                    </div>
                ` : ''}
            </div>

            <!-- Informações -->
            <div class="product-details">
                <div class="product-header">
                    ${product.category ? `<span class="product-category-tag">${product.category.name}</span>` : ''}
                    <h1 class="product-title">${product.name}</h1>
                    ${product.sku ? `<p class="product-sku">SKU: ${product.sku}</p>` : ''}
                </div>

                <div class="product-price-box">
                    <span class="price-label">Valor:</span>
                    <span class="price-value">${formatPrice(product.price)}</span>
                </div>

                ${description ? `
                    <div class="product-description">
                        <h3>Descrição</h3>
                        <div class="description-content">${description}</div>
                    </div>
                ` : ''}

                ${Object.keys(specs).length > 0 ? `
                    <div class="product-specs">
                        <h3>Especificações</h3>
                        <table class="specs-table">
                            ${Object.entries(specs).map(([key, value]) => `
                                <tr>
                                    <th>${key}</th>
                                    <td>${value}</td>
                                </tr>
                            `).join('')}
                        </table>
                    </div>
                ` : ''}

                <div class="product-actions">
                    <a href="${getWhatsAppLink(product, WHATSAPP_NUMBERS[0])}" class="btn btn-whatsapp" target="_blank" rel="noopener">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Solicitar Orçamento
                    </a>
                    <a href="tel:${CONTACT_PHONE}" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        Ligar Agora
                    </a>
                </div>

                <div class="product-share">
                    <span>Compartilhar:</span>
                    <div class="share-buttons">
                        <a href="https://wa.me/?text=${encodeURIComponent(product.name + ' - ' + window.location.href)}" target="_blank" class="share-btn whatsapp" title="WhatsApp">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.href)}" target="_blank" class="share-btn facebook" title="Facebook">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Mudar imagem principal da galeria
 */
function changeMainImage(imageUrl, thumbElement) {
    const mainImage = document.getElementById('mainImage');
    if (mainImage) {
        mainImage.src = imageUrl;
    }

    // Atualizar thumb ativa
    document.querySelectorAll('.gallery-thumb').forEach(thumb => {
        thumb.classList.remove('active');
    });
    if (thumbElement) {
        thumbElement.classList.add('active');
    }
}

// ===== UI HELPERS =====

/**
 * Mostrar loading
 */
function showLoading(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = `
        <div class="loading-state">
            <div class="loading-spinner"></div>
            <p>Carregando...</p>
        </div>
    `;
}

/**
 * Mostrar erro
 */
function showError(containerId, message) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = `
        <div class="error-state">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <p>${message}</p>
            <button class="btn btn-primary" onclick="location.reload()">Tentar Novamente</button>
        </div>
    `;
}

/**
 * Atualizar breadcrumb
 */
function updateBreadcrumb(categoryName, productName = null) {
    const breadcrumb = document.getElementById('breadcrumb');
    if (!breadcrumb) return;

    let html = `
        <a href="index.html">Home</a>
        <span class="separator">/</span>
        <a href="equipamentos.html">Equipamentos</a>
    `;

    if (categoryName) {
        if (productName) {
            html += `
                <span class="separator">/</span>
                <a href="categoria.html?slug=${categoryName.toLowerCase().replace(/\s+/g, '-')}">${categoryName}</a>
                <span class="separator">/</span>
                <span class="current">${productName}</span>
            `;
        } else {
            html += `
                <span class="separator">/</span>
                <span class="current">${categoryName}</span>
            `;
        }
    }

    breadcrumb.innerHTML = html;
}

/**
 * Configurar paginação
 */
function setupPagination(slug, currentPage) {
    const container = document.getElementById('pagination');
    if (!container) return;

    container.innerHTML = `
        <button class="btn btn-secondary" onclick="loadMoreProducts('${slug}', ${currentPage + 1})">
            Carregar mais equipamentos
        </button>
    `;
    container.style.display = 'block';
}

/**
 * Carregar mais produtos
 */
async function loadMoreProducts(slug, page) {
    try {
        const data = await getProductsByCategory(slug, page);
        const container = document.getElementById('productsGrid');

        if (data.products && data.products.length > 0) {
            const newProducts = data.products.map(product => renderProductCard(product)).join('');
            container.insertAdjacentHTML('beforeend', newProducts);

            if (!data.has_more) {
                document.getElementById('pagination').style.display = 'none';
            } else {
                setupPagination(slug, page);
            }
        }
    } catch (error) {
        console.error('Erro ao carregar mais produtos:', error);
    }
}
