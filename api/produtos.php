<?php
/**
 * API de Produtos - AlugServ
 *
 * Endpoints:
 * GET /api/produtos.php - Lista todos os produtos
 * GET /api/produtos.php?categoria=slug - Lista produtos por categoria
 * GET /api/produtos.php?id=123 - Retorna produto específico
 * GET /api/produtos.php?search=termo - Busca produtos
 */

require_once 'config.php';

// Obter parâmetros
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$categoria = isset($_GET['categoria']) ? sanitize($_GET['categoria']) : null;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : null;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : PRODUCTS_PER_PAGE;

// Limitar per_page
$perPage = min($perPage, 100);

try {
    // Buscar produto específico por ID
    if ($id) {
        $product = getProductById($id);
        if (!$product) {
            errorResponse('Produto não encontrado', 404);
        }
        jsonResponse(['success' => true, 'product' => $product]);
    }

    // Buscar produtos por categoria
    if ($categoria) {
        $result = getProductsByCategory($categoria, $page, $perPage);
        jsonResponse($result);
    }

    // Buscar produtos (search)
    if ($search) {
        $result = searchProducts($search, $page, $perPage);
        jsonResponse($result);
    }

    // Listar todos os produtos
    $result = getAllProducts($page, $perPage);
    jsonResponse($result);

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    errorResponse('Erro interno do servidor', 500);
}

// ===== FUNÇÕES =====

/**
 * Buscar produto por ID
 */
function getProductById($id) {
    // Verificar cache
    if (CACHE_ENABLED) {
        $cached = getCachedProduct($id);
        if ($cached) return $cached;
    }

    // Buscar do WooCommerce
    $response = woocommerceRequest("products/{$id}");

    if (isset($response['error'])) {
        return null;
    }

    $product = formatProduct($response);

    // Salvar cache
    if (CACHE_ENABLED) {
        cacheProduct($id, $product);
    }

    return $product;
}

/**
 * Buscar produtos por categoria (slug)
 */
function getProductsByCategory($categorySlug, $page = 1, $perPage = 12) {
    // Primeiro, buscar ID da categoria pelo slug
    $categories = woocommerceRequest('products/categories', ['slug' => $categorySlug]);

    if (empty($categories) || isset($categories['error'])) {
        return [
            'success' => true,
            'products' => [],
            'category' => null,
            'total' => 0,
            'page' => $page,
            'total_pages' => 0
        ];
    }

    $category = $categories[0];
    $categoryId = $category['id'];

    // Buscar produtos da categoria
    $response = woocommerceRequest('products', [
        'category' => $categoryId,
        'page' => $page,
        'per_page' => $perPage,
        'status' => 'publish',
        'orderby' => 'title',
        'order' => 'asc'
    ]);

    if (isset($response['error'])) {
        return [
            'success' => false,
            'error' => $response['message']
        ];
    }

    $products = array_map('formatProduct', $response);

    // Buscar total de produtos para paginação
    $totalResponse = woocommerceRequest('products', [
        'category' => $categoryId,
        'per_page' => 1,
        'status' => 'publish'
    ]);

    // WooCommerce retorna o total no header, mas para simplificar
    // vamos estimar baseado no número de produtos retornados
    $hasMore = count($response) === $perPage;

    return [
        'success' => true,
        'products' => $products,
        'category' => [
            'id' => $category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'description' => $category['description'] ?? '',
            'image' => $category['image']['src'] ?? null
        ],
        'page' => $page,
        'per_page' => $perPage,
        'has_more' => $hasMore
    ];
}

/**
 * Buscar todos os produtos
 */
function getAllProducts($page = 1, $perPage = 12) {
    $response = woocommerceRequest('products', [
        'page' => $page,
        'per_page' => $perPage,
        'status' => 'publish',
        'orderby' => 'title',
        'order' => 'asc'
    ]);

    if (isset($response['error'])) {
        return [
            'success' => false,
            'error' => $response['message']
        ];
    }

    $products = array_map('formatProduct', $response);
    $hasMore = count($response) === $perPage;

    return [
        'success' => true,
        'products' => $products,
        'page' => $page,
        'per_page' => $perPage,
        'has_more' => $hasMore
    ];
}

/**
 * Buscar produtos (search)
 */
function searchProducts($term, $page = 1, $perPage = 12) {
    $response = woocommerceRequest('products', [
        'search' => $term,
        'page' => $page,
        'per_page' => $perPage,
        'status' => 'publish'
    ]);

    if (isset($response['error'])) {
        return [
            'success' => false,
            'error' => $response['message']
        ];
    }

    $products = array_map('formatProduct', $response);
    $hasMore = count($response) === $perPage;

    return [
        'success' => true,
        'products' => $products,
        'search_term' => $term,
        'page' => $page,
        'per_page' => $perPage,
        'has_more' => $hasMore
    ];
}

/**
 * Formatar produto para resposta
 */
function formatProduct($wcProduct) {
    // Pegar primeira imagem ou placeholder
    $image = !empty($wcProduct['images']) ? $wcProduct['images'][0]['src'] : null;
    $gallery = array_map(function($img) {
        return $img['src'];
    }, $wcProduct['images'] ?? []);

    // Pegar categoria principal
    $category = !empty($wcProduct['categories']) ? $wcProduct['categories'][0] : null;

    // Pegar atributos/especificações
    $specs = [];
    if (!empty($wcProduct['attributes'])) {
        foreach ($wcProduct['attributes'] as $attr) {
            $specs[$attr['name']] = implode(', ', $attr['options']);
        }
    }

    return [
        'id' => $wcProduct['id'],
        'name' => $wcProduct['name'],
        'slug' => $wcProduct['slug'],
        'description' => $wcProduct['description'],
        'short_description' => $wcProduct['short_description'],
        'price' => $wcProduct['price'],
        'regular_price' => $wcProduct['regular_price'],
        'sale_price' => $wcProduct['sale_price'],
        'image' => $image,
        'gallery' => $gallery,
        'category' => $category ? [
            'id' => $category['id'],
            'name' => $category['name'],
            'slug' => $category['slug']
        ] : null,
        'categories' => array_map(function($cat) {
            return [
                'id' => $cat['id'],
                'name' => $cat['name'],
                'slug' => $cat['slug']
            ];
        }, $wcProduct['categories'] ?? []),
        'specs' => $specs,
        'sku' => $wcProduct['sku'] ?? null,
        'stock_status' => $wcProduct['stock_status'],
        'in_stock' => $wcProduct['stock_status'] === 'instock',
        'permalink' => $wcProduct['permalink']
    ];
}

/**
 * Buscar produto do cache
 */
function getCachedProduct($id) {
    $db = getDBConnection();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("
            SELECT data FROM products_cache
            WHERE wc_id = ? AND updated_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$id, CACHE_DURATION]);
        $result = $stmt->fetch();

        if ($result) {
            return json_decode($result['data'], true);
        }
    } catch (Exception $e) {
        error_log("Cache read error: " . $e->getMessage());
    }

    return null;
}

/**
 * Salvar produto no cache
 */
function cacheProduct($id, $product) {
    $db = getDBConnection();
    if (!$db) return;

    try {
        $stmt = $db->prepare("
            INSERT INTO products_cache (wc_id, data, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = NOW()
        ");
        $stmt->execute([$id, json_encode($product)]);
    } catch (Exception $e) {
        error_log("Cache write error: " . $e->getMessage());
    }
}
