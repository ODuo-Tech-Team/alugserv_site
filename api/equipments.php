<?php
/**
 * API de Equipamentos - AlugServ
 *
 * GET    /api/equipments.php              - Listar todos
 * GET    /api/equipments.php?id=1         - Buscar por ID
 * GET    /api/equipments.php?slug=xxx     - Buscar por slug
 * GET    /api/equipments.php?category=xxx - Buscar por categoria (slug)
 * GET    /api/equipments.php?search=xxx   - Buscar por termo
 * POST   /api/equipments.php              - Criar (requer auth)
 * PUT    /api/equipments.php?id=1         - Atualizar (requer auth)
 * DELETE /api/equipments.php?id=1         - Deletar (requer auth)
 */

require_once 'config.php';

$method = getRequestMethod();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : null;
$category = isset($_GET['category']) ? sanitize($_GET['category']) : null;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : null;

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                getEquipment($id);
            } elseif ($slug) {
                getEquipmentBySlug($slug);
            } elseif ($category) {
                getEquipmentsByCategory($category);
            } elseif ($search) {
                searchEquipments($search);
            } else {
                listEquipments();
            }
            break;

        case 'POST':
            authenticate();
            createEquipment();
            break;

        case 'PUT':
            authenticate();
            if (!$id) errorResponse('ID é obrigatório');
            updateEquipment($id);
            break;

        case 'DELETE':
            authenticate();
            if (!$id) errorResponse('ID é obrigatório');
            deleteEquipment($id);
            break;

        default:
            errorResponse('Método não permitido', 405);
    }
} catch (Exception $e) {
    error_log("Equipments API Error: " . $e->getMessage());
    errorResponse('Erro interno do servidor', 500);
}

// ===== FUNÇÕES =====

/**
 * Formatar equipamento para resposta
 */
function formatEquipment($row) {
    return [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'slug' => $row['slug'],
        'description' => $row['description'],
        'short_description' => $row['short_description'],
        'category_id' => (int)$row['category_id'],
        'category_name' => $row['category_name'] ?? null,
        'category_slug' => $row['category_slug'] ?? null,
        'image' => $row['image'],
        'gallery' => $row['gallery'] ? json_decode($row['gallery'], true) : [],
        'price' => $row['price'] ? (float)$row['price'] : null,
        'price_type' => $row['price_type'],
        'sku' => $row['sku'],
        'brand' => $row['brand'],
        'model' => $row['model'],
        'specs' => $row['specs'] ? json_decode($row['specs'], true) : [],
        'stock_status' => $row['stock_status'],
        'featured' => (bool)$row['featured'],
        'status' => $row['status'],
        'views' => (int)$row['views'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

/**
 * Listar todos os equipamentos
 */
function listEquipments() {
    $db = getDB();
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? ITEMS_PER_PAGE);
    $status = $_GET['status'] ?? null;
    $featured = isset($_GET['featured']) ? (bool)$_GET['featured'] : null;

    $where = [];
    $params = [];

    // Filtro de status (padrão: active para público)
    if ($status) {
        $where[] = "e.status = ?";
        $params[] = $status;
    } elseif (!getBearerToken()) {
        // Se não autenticado, mostrar apenas ativos
        $where[] = "e.status = 'active'";
    }

    if ($featured !== null) {
        $where[] = "e.featured = ?";
        $params[] = $featured ? 1 : 0;
    }

    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

    $query = "
        SELECT e.*, c.name as category_name, c.slug as category_slug
        FROM equipments e
        LEFT JOIN categories c ON e.category_id = c.id
        {$whereClause}
        ORDER BY e.sort_order ASC, e.name ASC
    ";

    $result = paginate($query, $params, $page, $perPage);
    $equipments = array_map('formatEquipment', $result['items']);

    successResponse([
        'equipments' => $equipments,
        'pagination' => $result['pagination']
    ]);
}

/**
 * Buscar equipamento por ID
 */
function getEquipment($id) {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT e.*, c.name as category_name, c.slug as category_slug
        FROM equipments e
        LEFT JOIN categories c ON e.category_id = c.id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $equipment = $stmt->fetch();

    if (!$equipment) {
        errorResponse('Equipamento não encontrado', 404);
    }

    // Incrementar views
    $db->prepare("UPDATE equipments SET views = views + 1 WHERE id = ?")->execute([$id]);

    successResponse(['equipment' => formatEquipment($equipment)]);
}

/**
 * Buscar equipamento por slug
 */
function getEquipmentBySlug($slug) {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT e.*, c.name as category_name, c.slug as category_slug
        FROM equipments e
        LEFT JOIN categories c ON e.category_id = c.id
        WHERE e.slug = ? AND e.status = 'active'
    ");
    $stmt->execute([$slug]);
    $equipment = $stmt->fetch();

    if (!$equipment) {
        errorResponse('Equipamento não encontrado', 404);
    }

    // Incrementar views
    $db->prepare("UPDATE equipments SET views = views + 1 WHERE id = ?")->execute([$equipment['id']]);

    successResponse(['equipment' => formatEquipment($equipment)]);
}

/**
 * Buscar equipamentos por categoria
 */
function getEquipmentsByCategory($categorySlug) {
    $db = getDB();
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? ITEMS_PER_PAGE);

    // Buscar categoria
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ? AND status = 'active'");
    $stmt->execute([$categorySlug]);
    $category = $stmt->fetch();

    if (!$category) {
        successResponse([
            'equipments' => [],
            'category' => null,
            'pagination' => ['page' => 1, 'total' => 0, 'total_pages' => 0]
        ]);
        return;
    }

    $query = "
        SELECT e.*, c.name as category_name, c.slug as category_slug
        FROM equipments e
        LEFT JOIN categories c ON e.category_id = c.id
        WHERE e.category_id = ? AND e.status = 'active'
        ORDER BY e.sort_order ASC, e.name ASC
    ";

    $result = paginate($query, [$category['id']], $page, $perPage);
    $equipments = array_map('formatEquipment', $result['items']);

    successResponse([
        'equipments' => $equipments,
        'category' => $category,
        'pagination' => $result['pagination']
    ]);
}

/**
 * Buscar equipamentos
 */
function searchEquipments($term) {
    $db = getDB();
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? ITEMS_PER_PAGE);

    $searchTerm = "%{$term}%";

    $query = "
        SELECT e.*, c.name as category_name, c.slug as category_slug
        FROM equipments e
        LEFT JOIN categories c ON e.category_id = c.id
        WHERE e.status = 'active' AND (
            e.name LIKE ? OR
            e.description LIKE ? OR
            e.short_description LIKE ? OR
            e.brand LIKE ? OR
            e.model LIKE ? OR
            c.name LIKE ?
        )
        ORDER BY e.name ASC
    ";

    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $result = paginate($query, $params, $page, $perPage);
    $equipments = array_map('formatEquipment', $result['items']);

    successResponse([
        'equipments' => $equipments,
        'search_term' => $term,
        'pagination' => $result['pagination']
    ]);
}

/**
 * Criar equipamento
 */
function createEquipment() {
    $db = getDB();
    $data = getRequestData();

    // Validar campos obrigatórios
    $name = sanitize($data['name'] ?? '');
    $categoryId = (int)($data['category_id'] ?? 0);

    if (empty($name)) {
        errorResponse('Nome é obrigatório');
    }
    if (!$categoryId) {
        errorResponse('Categoria é obrigatória');
    }

    // Verificar categoria
    $stmt = $db->prepare("SELECT id FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    if (!$stmt->fetch()) {
        errorResponse('Categoria não encontrada');
    }

    // Gerar slug
    $slug = $data['slug'] ?? generateSlug($name);
    $slug = generateSlug($slug);

    // Verificar slug único
    $stmt = $db->prepare("SELECT id FROM equipments WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        $slug = $slug . '-' . time();
    }

    // Upload de imagem principal
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = uploadFile($_FILES['image'], 'equipments');
    }

    // Gallery
    $gallery = [];
    if (isset($data['gallery']) && is_array($data['gallery'])) {
        $gallery = $data['gallery'];
    }

    // Specs
    $specs = [];
    if (isset($data['specs'])) {
        $specs = is_array($data['specs']) ? $data['specs'] : json_decode($data['specs'], true);
    }

    // Inserir
    $stmt = $db->prepare("
        INSERT INTO equipments (
            name, slug, description, short_description, category_id, image, gallery,
            price, price_type, sku, brand, model, specs, stock_status, featured, sort_order, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $name,
        $slug,
        $data['description'] ?? '',
        sanitize($data['short_description'] ?? ''),
        $categoryId,
        $image,
        json_encode($gallery),
        !empty($data['price']) ? (float)$data['price'] : null,
        $data['price_type'] ?? 'daily',
        sanitize($data['sku'] ?? ''),
        sanitize($data['brand'] ?? ''),
        sanitize($data['model'] ?? ''),
        json_encode($specs),
        $data['stock_status'] ?? 'available',
        isset($data['featured']) ? (int)(bool)$data['featured'] : 0,
        (int)($data['sort_order'] ?? 0),
        $data['status'] ?? 'active'
    ]);

    $equipmentId = $db->lastInsertId();
    logActivity('create', 'equipment', $equipmentId, "Equipamento criado: {$name}");

    successResponse(['id' => $equipmentId], 'Equipamento criado com sucesso');
}

/**
 * Atualizar equipamento
 */
function updateEquipment($id) {
    $db = getDB();
    $data = getRequestData();

    // Verificar se existe
    $stmt = $db->prepare("SELECT * FROM equipments WHERE id = ?");
    $stmt->execute([$id]);
    $equipment = $stmt->fetch();

    if (!$equipment) {
        errorResponse('Equipamento não encontrado', 404);
    }

    // Preparar dados
    $name = sanitize($data['name'] ?? $equipment['name']);
    $categoryId = (int)($data['category_id'] ?? $equipment['category_id']);

    // Verificar categoria
    $stmt = $db->prepare("SELECT id FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    if (!$stmt->fetch()) {
        errorResponse('Categoria não encontrada');
    }

    // Slug
    $slug = $equipment['slug'];
    if (isset($data['slug']) && $data['slug'] !== $equipment['slug']) {
        $slug = generateSlug($data['slug']);
        $stmt = $db->prepare("SELECT id FROM equipments WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            $slug = $slug . '-' . time();
        }
    }

    // Upload de imagem
    $image = $equipment['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        deleteFile($equipment['image']);
        $image = uploadFile($_FILES['image'], 'equipments');
    }

    // Gallery
    $gallery = $equipment['gallery'] ? json_decode($equipment['gallery'], true) : [];
    if (isset($data['gallery'])) {
        $gallery = is_array($data['gallery']) ? $data['gallery'] : json_decode($data['gallery'], true);
    }

    // Specs
    $specs = $equipment['specs'] ? json_decode($equipment['specs'], true) : [];
    if (isset($data['specs'])) {
        $specs = is_array($data['specs']) ? $data['specs'] : json_decode($data['specs'], true);
    }

    // Atualizar
    $stmt = $db->prepare("
        UPDATE equipments SET
            name = ?,
            slug = ?,
            description = ?,
            short_description = ?,
            category_id = ?,
            image = ?,
            gallery = ?,
            price = ?,
            price_type = ?,
            sku = ?,
            brand = ?,
            model = ?,
            specs = ?,
            stock_status = ?,
            featured = ?,
            sort_order = ?,
            status = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $name,
        $slug,
        $data['description'] ?? $equipment['description'],
        sanitize($data['short_description'] ?? $equipment['short_description']),
        $categoryId,
        $image,
        json_encode($gallery),
        isset($data['price']) ? (float)$data['price'] : $equipment['price'],
        $data['price_type'] ?? $equipment['price_type'],
        sanitize($data['sku'] ?? $equipment['sku']),
        sanitize($data['brand'] ?? $equipment['brand']),
        sanitize($data['model'] ?? $equipment['model']),
        json_encode($specs),
        $data['stock_status'] ?? $equipment['stock_status'],
        isset($data['featured']) ? (int)(bool)$data['featured'] : $equipment['featured'],
        (int)($data['sort_order'] ?? $equipment['sort_order']),
        $data['status'] ?? $equipment['status'],
        $id
    ]);

    logActivity('update', 'equipment', $id, "Equipamento atualizado: {$name}");

    successResponse([], 'Equipamento atualizado com sucesso');
}

/**
 * Deletar equipamento
 */
function deleteEquipment($id) {
    $db = getDB();

    // Verificar se existe
    $stmt = $db->prepare("SELECT * FROM equipments WHERE id = ?");
    $stmt->execute([$id]);
    $equipment = $stmt->fetch();

    if (!$equipment) {
        errorResponse('Equipamento não encontrado', 404);
    }

    // Deletar imagem
    deleteFile($equipment['image']);

    // Deletar galeria
    if ($equipment['gallery']) {
        $gallery = json_decode($equipment['gallery'], true);
        foreach ($gallery as $img) {
            deleteFile($img);
        }
    }

    // Deletar
    $stmt = $db->prepare("DELETE FROM equipments WHERE id = ?");
    $stmt->execute([$id]);

    logActivity('delete', 'equipment', $id, "Equipamento deletado: {$equipment['name']}");

    successResponse([], 'Equipamento deletado com sucesso');
}
