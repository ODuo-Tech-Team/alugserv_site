<?php
/**
 * Script de Teste da API de Produtos
 * Acesse: /api/test-product.php
 *
 * Testa se a API de equipamentos está funcionando corretamente
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

try {
    $db = getDB();

    // 1. Testar conexão com banco
    $result['tests']['database'] = [
        'status' => 'OK',
        'message' => 'Conexão com banco OK'
    ];

    // 2. Contar equipamentos
    $stmt = $db->query("SELECT COUNT(*) as total FROM equipments");
    $total = $stmt->fetch()['total'];
    $result['tests']['equipments_count'] = [
        'status' => 'OK',
        'total' => $total,
        'message' => "Total de equipamentos: $total"
    ];

    // 3. Buscar primeiro equipamento
    $stmt = $db->query("SELECT id, name, slug, status FROM equipments LIMIT 1");
    $first = $stmt->fetch();
    if ($first) {
        $result['tests']['first_equipment'] = [
            'status' => 'OK',
            'data' => $first,
            'message' => "Primeiro equipamento: {$first['name']}"
        ];

        // 4. Testar busca por slug
        $stmt = $db->prepare("SELECT id, name, slug FROM equipments WHERE slug = ?");
        $stmt->execute([$first['slug']]);
        $bySlug = $stmt->fetch();
        $result['tests']['search_by_slug'] = [
            'status' => $bySlug ? 'OK' : 'ERRO',
            'slug_tested' => $first['slug'],
            'found' => $bySlug ? true : false,
            'message' => $bySlug ? "Busca por slug OK" : "Busca por slug FALHOU"
        ];

        // 5. Testar busca por ID
        $stmt = $db->prepare("SELECT id, name, slug FROM equipments WHERE id = ?");
        $stmt->execute([$first['id']]);
        $byId = $stmt->fetch();
        $result['tests']['search_by_id'] = [
            'status' => $byId ? 'OK' : 'ERRO',
            'id_tested' => $first['id'],
            'found' => $byId ? true : false,
            'message' => $byId ? "Busca por ID OK" : "Busca por ID FALHOU"
        ];
    } else {
        $result['tests']['first_equipment'] = [
            'status' => 'AVISO',
            'message' => 'Nenhum equipamento cadastrado'
        ];
    }

    // 6. Listar alguns slugs para testar
    $stmt = $db->query("SELECT id, name, slug, status FROM equipments WHERE status = 'active' LIMIT 5");
    $samples = $stmt->fetchAll();
    $result['tests']['sample_equipments'] = [
        'status' => 'OK',
        'count' => count($samples),
        'items' => array_map(function($e) {
            return [
                'id' => $e['id'],
                'name' => $e['name'],
                'slug' => $e['slug'],
                'test_url_slug' => '/produto.html?slug=' . $e['slug'],
                'test_url_id' => '/produto.html?id=' . $e['id']
            ];
        }, $samples)
    ];

    // 7. Verificar categorias
    $stmt = $db->query("SELECT COUNT(*) as total FROM categories");
    $catTotal = $stmt->fetch()['total'];
    $result['tests']['categories_count'] = [
        'status' => 'OK',
        'total' => $catTotal
    ];

    // Resumo
    $result['summary'] = 'Tudo OK! Use as URLs de teste acima para verificar a página de produto.';
    $result['api_base'] = '/api';
    $result['example_api_calls'] = [
        'list_all' => '/api/equipments.php',
        'by_id' => '/api/equipments.php?id=1',
        'by_slug' => '/api/equipments.php?slug=SLUG_AQUI'
    ];

} catch (Exception $e) {
    $result['tests']['error'] = [
        'status' => 'ERRO',
        'message' => $e->getMessage()
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
