<?php
/**
 * API de Categorias - AlugServ
 *
 * Endpoints:
 * GET /api/categorias.php - Lista todas as categorias
 * GET /api/categorias.php?slug=xxx - Retorna categoria específica por slug
 * GET /api/categorias.php?id=123 - Retorna categoria específica por ID
 */

require_once 'config.php';

// Obter parâmetros
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : null;
$parent = isset($_GET['parent']) ? intval($_GET['parent']) : null;

try {
    // Buscar categoria por ID
    if ($id) {
        $category = getCategoryById($id);
        if (!$category) {
            errorResponse('Categoria não encontrada', 404);
        }
        jsonResponse(['success' => true, 'category' => $category]);
    }

    // Buscar categoria por slug
    if ($slug) {
        $category = getCategoryBySlug($slug);
        if (!$category) {
            errorResponse('Categoria não encontrada', 404);
        }
        jsonResponse(['success' => true, 'category' => $category]);
    }

    // Listar todas as categorias
    $result = getAllCategories($parent);
    jsonResponse($result);

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    errorResponse('Erro interno do servidor', 500);
}

// ===== FUNÇÕES =====

/**
 * Buscar categoria por ID
 */
function getCategoryById($id) {
    $response = woocommerceRequest("products/categories/{$id}");

    if (isset($response['error'])) {
        return null;
    }

    return formatCategory($response);
}

/**
 * Buscar categoria por slug
 */
function getCategoryBySlug($slug) {
    $response = woocommerceRequest('products/categories', ['slug' => $slug]);

    if (empty($response) || isset($response['error'])) {
        return null;
    }

    return formatCategory($response[0]);
}

/**
 * Buscar todas as categorias
 */
function getAllCategories($parent = null) {
    $params = [
        'per_page' => 100,
        'orderby' => 'name',
        'order' => 'asc',
        'hide_empty' => false
    ];

    // Filtrar por categoria pai
    if ($parent !== null) {
        $params['parent'] = $parent;
    }

    $response = woocommerceRequest('products/categories', $params);

    if (isset($response['error'])) {
        return [
            'success' => false,
            'error' => $response['message']
        ];
    }

    $categories = array_map('formatCategory', $response);

    // Organizar categorias em hierarquia (opcional)
    $organized = organizeCategories($categories);

    return [
        'success' => true,
        'categories' => $categories,
        'tree' => $organized,
        'total' => count($categories)
    ];
}

/**
 * Formatar categoria para resposta
 */
function formatCategory($wcCategory) {
    return [
        'id' => $wcCategory['id'],
        'name' => $wcCategory['name'],
        'slug' => $wcCategory['slug'],
        'description' => $wcCategory['description'] ?? '',
        'image' => $wcCategory['image']['src'] ?? null,
        'parent' => $wcCategory['parent'],
        'count' => $wcCategory['count'],
        'link' => "categoria.html?slug=" . $wcCategory['slug']
    ];
}

/**
 * Organizar categorias em árvore hierárquica
 */
function organizeCategories($categories) {
    $tree = [];
    $indexed = [];

    // Indexar por ID
    foreach ($categories as $cat) {
        $indexed[$cat['id']] = $cat;
        $indexed[$cat['id']]['children'] = [];
    }

    // Construir árvore
    foreach ($indexed as $id => $cat) {
        if ($cat['parent'] === 0) {
            $tree[$id] = &$indexed[$id];
        } else {
            if (isset($indexed[$cat['parent']])) {
                $indexed[$cat['parent']]['children'][] = &$indexed[$id];
            }
        }
    }

    return array_values($tree);
}
