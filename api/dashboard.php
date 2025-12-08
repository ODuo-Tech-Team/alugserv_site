<?php
/**
 * API do Dashboard - AlugServ
 * GET /api/dashboard.php
 */

require_once 'config.php';

// Apenas GET e requer autenticação
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Método não permitido', 405);
}

authenticate();

try {
    $db = getDB();

    // Total de equipamentos
    $stmt = $db->query("SELECT COUNT(*) as total FROM equipments");
    $totalEquipments = (int)$stmt->fetch()['total'];

    // Equipamentos disponíveis (ativos)
    $stmt = $db->query("SELECT COUNT(*) as total FROM equipments WHERE status = 'active'");
    $availableEquipments = (int)$stmt->fetch()['total'];

    // Total de categorias
    $stmt = $db->query("SELECT COUNT(*) as total FROM categories");
    $totalCategories = (int)$stmt->fetch()['total'];

    // Total de contatos (se houver tabela)
    $totalContacts = 0;

    // Equipamentos recentes
    $stmt = $db->query("
        SELECT e.id, e.name, e.status, e.created_at, c.name as category
        FROM equipments e
        LEFT JOIN categories c ON e.category_id = c.id
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $recentEquipments = $stmt->fetchAll();

    // Formatar para resposta
    $formattedRecent = array_map(function($item) {
        return [
            'id' => (int)$item['id'],
            'title' => $item['name'],
            'category' => $item['category'],
            'status' => $item['status'],
            'created_at' => $item['created_at']
        ];
    }, $recentEquipments);

    successResponse([
        'totalEquipments' => $totalEquipments,
        'availableEquipments' => $availableEquipments,
        'totalCategories' => $totalCategories,
        'totalContacts' => $totalContacts,
        'recentEquipments' => $formattedRecent
    ]);

} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    errorResponse('Erro interno do servidor', 500);
}
