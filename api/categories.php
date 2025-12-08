<?php
/**
 * API de Categorias - AlugServ
 *
 * GET    /api/categories.php           - Listar todas
 * GET    /api/categories.php?id=1      - Buscar por ID
 * GET    /api/categories.php?slug=xxx  - Buscar por slug
 * POST   /api/categories.php           - Criar (requer auth)
 * PUT    /api/categories.php?id=1      - Atualizar (requer auth)
 * DELETE /api/categories.php?id=1      - Deletar (requer auth)
 */

require_once 'config.php';

$method = getRequestMethod();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : null;

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                getCategory($id);
            } elseif ($slug) {
                getCategoryBySlug($slug);
            } else {
                listCategories();
            }
            break;

        case 'POST':
            authenticate();
            createCategory();
            break;

        case 'PUT':
            authenticate();
            if (!$id) errorResponse('ID é obrigatório');
            updateCategory($id);
            break;

        case 'DELETE':
            authenticate();
            if (!$id) errorResponse('ID é obrigatório');
            deleteCategory($id);
            break;

        default:
            errorResponse('Método não permitido', 405);
    }
} catch (Exception $e) {
    error_log("Categories API Error: " . $e->getMessage());
    errorResponse('Erro interno do servidor', 500);
}

// ===== FUNÇÕES =====

/**
 * Listar todas as categorias
 */
function listCategories() {
    $db = getDB();
    $status = $_GET['status'] ?? null;
    $parent = isset($_GET['parent']) ? (int)$_GET['parent'] : null;

    $where = [];
    $params = [];

    if ($status) {
        $where[] = "status = ?";
        $params[] = $status;
    }

    if ($parent !== null) {
        $where[] = "parent_id " . ($parent === 0 ? "IS NULL" : "= ?");
        if ($parent !== 0) $params[] = $parent;
    }

    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

    $stmt = $db->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM equipments e WHERE e.category_id = c.id AND e.status = 'active') as equipment_count
        FROM categories c
        {$whereClause}
        ORDER BY c.sort_order ASC, c.name ASC
    ");
    $stmt->execute($params);
    $categories = $stmt->fetchAll();

    successResponse(['categories' => $categories]);
}

/**
 * Buscar categoria por ID
 */
function getCategory($id) {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM equipments e WHERE e.category_id = c.id AND e.status = 'active') as equipment_count
        FROM categories c
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if (!$category) {
        errorResponse('Categoria não encontrada', 404);
    }

    successResponse(['category' => $category]);
}

/**
 * Buscar categoria por slug
 */
function getCategoryBySlug($slug) {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM equipments e WHERE e.category_id = c.id AND e.status = 'active') as equipment_count
        FROM categories c
        WHERE c.slug = ? AND c.status = 'active'
    ");
    $stmt->execute([$slug]);
    $category = $stmt->fetch();

    if (!$category) {
        errorResponse('Categoria não encontrada', 404);
    }

    successResponse(['category' => $category]);
}

/**
 * Criar categoria
 */
function createCategory() {
    $db = getDB();
    $data = getRequestData();

    // Validar campos obrigatórios
    $name = sanitize($data['name'] ?? '');
    if (empty($name)) {
        errorResponse('Nome é obrigatório');
    }

    // Gerar slug
    $slug = $data['slug'] ?? generateSlug($name);
    $slug = generateSlug($slug);

    // Verificar slug único
    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        $slug = $slug . '-' . time();
    }

    // Upload de imagem
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = uploadFile($_FILES['image'], 'categories');
    }

    // Inserir
    $stmt = $db->prepare("
        INSERT INTO categories (name, slug, description, image, icon, parent_id, sort_order, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $name,
        $slug,
        sanitize($data['description'] ?? ''),
        $image,
        sanitize($data['icon'] ?? ''),
        !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
        (int)($data['sort_order'] ?? 0),
        $data['status'] ?? 'active'
    ]);

    $categoryId = $db->lastInsertId();
    logActivity('create', 'category', $categoryId, "Categoria criada: {$name}");

    successResponse(['id' => $categoryId], 'Categoria criada com sucesso');
}

/**
 * Atualizar categoria
 */
function updateCategory($id) {
    $db = getDB();
    $data = getRequestData();

    // Verificar se existe
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if (!$category) {
        errorResponse('Categoria não encontrada', 404);
    }

    // Preparar dados
    $name = sanitize($data['name'] ?? $category['name']);

    // Slug
    $slug = $category['slug'];
    if (isset($data['slug']) && $data['slug'] !== $category['slug']) {
        $slug = generateSlug($data['slug']);
        $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            $slug = $slug . '-' . time();
        }
    }

    // Upload de imagem
    $image = $category['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        deleteFile($category['image']);
        $image = uploadFile($_FILES['image'], 'categories');
    }

    // Atualizar
    $stmt = $db->prepare("
        UPDATE categories SET
            name = ?,
            slug = ?,
            description = ?,
            image = ?,
            icon = ?,
            parent_id = ?,
            sort_order = ?,
            status = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $name,
        $slug,
        sanitize($data['description'] ?? $category['description']),
        $image,
        sanitize($data['icon'] ?? $category['icon']),
        !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
        (int)($data['sort_order'] ?? $category['sort_order']),
        $data['status'] ?? $category['status'],
        $id
    ]);

    logActivity('update', 'category', $id, "Categoria atualizada: {$name}");

    successResponse([], 'Categoria atualizada com sucesso');
}

/**
 * Deletar categoria
 */
function deleteCategory($id) {
    $db = getDB();

    // Verificar se existe
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if (!$category) {
        errorResponse('Categoria não encontrada', 404);
    }

    // Verificar se tem equipamentos
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM equipments WHERE category_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetch()['count'];

    if ($count > 0) {
        errorResponse("Não é possível deletar. Existem {$count} equipamento(s) nesta categoria.");
    }

    // Deletar imagem
    deleteFile($category['image']);

    // Deletar
    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);

    logActivity('delete', 'category', $id, "Categoria deletada: {$category['name']}");

    successResponse([], 'Categoria deletada com sucesso');
}
