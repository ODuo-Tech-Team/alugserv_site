<?php
/**
 * Script para Sincronizar Categorias com Equipamentos
 * Analisa os nomes dos equipamentos e tenta associá-los às categorias corretas
 *
 * Acesse: /api/sync-categories.php?key=alugserv2024sync
 *
 * REMOVA ESTE ARQUIVO APÓS USAR!
 */

// Chave de segurança
define('SYNC_KEY', 'alugserv2024sync');

if (!isset($_GET['key']) || $_GET['key'] !== SYNC_KEY) {
    die(json_encode(['error' => 'Chave inválida. Use: ?key=' . SYNC_KEY]));
}

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(300);

/**
 * Mapeamento de palavras-chave para categorias
 * Adicione ou modifique conforme necessário
 */
$categoryKeywords = [
    // Andaimes
    'andaime' => 'andaimes',
    'andaimes' => 'andaimes',
    'torre' => 'andaimes',
    'plataforma' => 'andaimes',
    'scaffolding' => 'andaimes',

    // Betoneiras
    'betoneira' => 'betoneiras',
    'misturador' => 'betoneiras',
    'concreto' => 'betoneiras',

    // Compactadores
    'compactador' => 'compactadores',
    'compactadora' => 'compactadores',
    'sapo' => 'compactadores',
    'placa vibratória' => 'compactadores',
    'rolo compactador' => 'compactadores',

    // Geradores
    'gerador' => 'geradores',
    'geradores' => 'geradores',
    'grupo gerador' => 'geradores',
    'energia' => 'geradores',

    // Marteletes
    'martelete' => 'marteletes',
    'rompedor' => 'marteletes',
    'demolição' => 'marteletes',
    'demolidor' => 'marteletes',
    'martelo' => 'marteletes',

    // Serras
    'serra' => 'serras',
    'cortadora' => 'serras',
    'corte' => 'serras',
    'disco' => 'serras',

    // Bombas
    'bomba' => 'bombas',
    'submersível' => 'bombas',
    'água' => 'bombas',
    'esgotamento' => 'bombas',

    // Elevadores de carga
    'elevador' => 'elevadores',
    'guincho' => 'elevadores',
    'talha' => 'elevadores',

    // Vibradores
    'vibrador' => 'vibradores',
    'mangote' => 'vibradores',

    // Escoras
    'escoramento' => 'escoramento',
    'escora' => 'escoramento',

    // Containers
    'container' => 'containers',
    'contêiner' => 'containers',

    // Equipamentos de segurança
    'epi' => 'seguranca',
    'capacete' => 'seguranca',
    'cinto' => 'seguranca',
    'trava queda' => 'seguranca',

    // Ferramentas diversas
    'furadeira' => 'ferramentas',
    'lixadeira' => 'ferramentas',
    'esmerilhadeira' => 'ferramentas',
    'parafusadeira' => 'ferramentas',

    // Compressores
    'compressor' => 'compressores',
    'ar comprimido' => 'compressores'
];

/**
 * Normalizar texto para comparação
 */
function normalizeText($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[áàãâä]/u', 'a', $text);
    $text = preg_replace('/[éèêë]/u', 'e', $text);
    $text = preg_replace('/[íìîï]/u', 'i', $text);
    $text = preg_replace('/[óòõôö]/u', 'o', $text);
    $text = preg_replace('/[úùûü]/u', 'u', $text);
    $text = preg_replace('/[ç]/u', 'c', $text);
    return $text;
}

try {
    $db = getDB();

    $results = [
        'updated' => 0,
        'no_match' => 0,
        'already_correct' => 0,
        'details' => []
    ];

    // Buscar todas as categorias
    $stmt = $db->query("SELECT id, name, slug FROM categories WHERE status = 'active'");
    $categories = $stmt->fetchAll();

    // Criar mapa de slug -> id
    $categoryMap = [];
    foreach ($categories as $cat) {
        $categoryMap[$cat['slug']] = $cat['id'];
        $categoryMap[normalizeText($cat['name'])] = $cat['id'];
    }

    // Buscar todos os equipamentos
    $stmt = $db->query("SELECT id, name, description, category_id FROM equipments");
    $equipments = $stmt->fetchAll();

    foreach ($equipments as $equip) {
        $id = $equip['id'];
        $name = $equip['name'];
        $description = $equip['description'];
        $currentCategoryId = $equip['category_id'];

        // Texto combinado para análise
        $searchText = normalizeText($name . ' ' . $description);

        // Tentar encontrar categoria baseada em keywords
        $foundCategorySlug = null;
        $foundKeyword = null;

        foreach ($categoryKeywords as $keyword => $categorySlug) {
            $normalizedKeyword = normalizeText($keyword);
            if (strpos($searchText, $normalizedKeyword) !== false) {
                // Verificar se a categoria existe
                if (isset($categoryMap[$categorySlug])) {
                    $foundCategorySlug = $categorySlug;
                    $foundKeyword = $keyword;
                    break;
                }
            }
        }

        // Se não encontrou por keyword, verificar se o nome da categoria está no nome do equipamento
        if (!$foundCategorySlug) {
            foreach ($categories as $cat) {
                $normalizedCatName = normalizeText($cat['name']);
                if (strpos($searchText, $normalizedCatName) !== false) {
                    $foundCategorySlug = $cat['slug'];
                    $foundKeyword = $cat['name'];
                    break;
                }
            }
        }

        if ($foundCategorySlug && isset($categoryMap[$foundCategorySlug])) {
            $newCategoryId = $categoryMap[$foundCategorySlug];

            if ($newCategoryId != $currentCategoryId) {
                // Atualizar categoria
                $updateStmt = $db->prepare("UPDATE equipments SET category_id = ? WHERE id = ?");
                $updateStmt->execute([$newCategoryId, $id]);

                $results['updated']++;
                $results['details'][] = [
                    'id' => $id,
                    'name' => $name,
                    'keyword_matched' => $foundKeyword,
                    'old_category_id' => $currentCategoryId,
                    'new_category_id' => $newCategoryId,
                    'new_category_slug' => $foundCategorySlug
                ];
            } else {
                $results['already_correct']++;
            }
        } else {
            $results['no_match']++;
            $results['details'][] = [
                'id' => $id,
                'name' => $name,
                'status' => 'no_match',
                'current_category_id' => $currentCategoryId
            ];
        }
    }

    // Tentar atualizar contagem de equipamentos por categoria
    // (ignora se a coluna não existir)
    try {
        // Verificar se a coluna existe
        $stmt = $db->query("SHOW COLUMNS FROM categories LIKE 'equipment_count'");
        if ($stmt->fetch()) {
            $db->exec("
                UPDATE categories c
                SET equipment_count = (
                    SELECT COUNT(*) FROM equipments e
                    WHERE e.category_id = c.id AND e.status = 'active'
                )
            ");
        }
    } catch (Exception $e) {
        // Ignora erro se coluna não existir
    }

    echo json_encode([
        'success' => true,
        'message' => "Sincronização concluída!",
        'summary' => [
            'total' => count($equipments),
            'updated' => $results['updated'],
            'already_correct' => $results['already_correct'],
            'no_match' => $results['no_match']
        ],
        'categories_available' => array_map(function($c) {
            return ['id' => $c['id'], 'name' => $c['name'], 'slug' => $c['slug']];
        }, $categories),
        'details' => $results['details'],
        'note' => 'Equipamentos marcados como "no_match" precisam ser categorizados manualmente no admin.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
