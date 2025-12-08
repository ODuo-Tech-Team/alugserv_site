<?php
/**
 * Script para Limpar Descrições dos Equipamentos
 * Remove HTML desnecessário, CSS inline, scripts e deixa apenas texto relevante
 *
 * Acesse: /api/cleanup-descriptions.php?key=alugserv2024cleanup
 *
 * REMOVA ESTE ARQUIVO APÓS USAR!
 */

// Chave de segurança
define('CLEANUP_KEY', 'alugserv2024cleanup');

if (!isset($_GET['key']) || $_GET['key'] !== CLEANUP_KEY) {
    die(json_encode(['error' => 'Chave inválida. Use: ?key=' . CLEANUP_KEY]));
}

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(300);

/**
 * Limpar descrição HTML do WooCommerce
 */
function cleanDescription($html) {
    if (empty($html)) return '';

    // Remover scripts e styles
    $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

    // Remover comentários HTML
    $html = preg_replace('/<!--.*?-->/s', '', $html);

    // Remover tags específicas do WooCommerce/WordPress
    $html = preg_replace('/<div[^>]*class="[^"]*woocommerce[^"]*"[^>]*>.*?<\/div>/is', '', $html);
    $html = preg_replace('/<div[^>]*class="[^"]*wp-block[^"]*"[^>]*>/i', '', $html);
    $html = preg_replace('/<figure[^>]*>.*?<\/figure>/is', '', $html);

    // Remover atributos style inline
    $html = preg_replace('/\s*style\s*=\s*"[^"]*"/i', '', $html);
    $html = preg_replace('/\s*style\s*=\s*\'[^\']*\'/i', '', $html);

    // Remover classes CSS
    $html = preg_replace('/\s*class\s*=\s*"[^"]*"/i', '', $html);
    $html = preg_replace('/\s*class\s*=\s*\'[^\']*\'/i', '', $html);

    // Remover IDs
    $html = preg_replace('/\s*id\s*=\s*"[^"]*"/i', '', $html);

    // Remover data-* atributos
    $html = preg_replace('/\s*data-[a-z0-9-]+\s*=\s*"[^"]*"/i', '', $html);

    // Remover divs vazias
    $html = preg_replace('/<div[^>]*>\s*<\/div>/i', '', $html);

    // Remover spans vazios
    $html = preg_replace('/<span[^>]*>\s*<\/span>/i', '', $html);

    // Remover tags de imagem (as imagens estão na galeria)
    $html = preg_replace('/<img[^>]*>/i', '', $html);

    // Remover links para adicionar ao carrinho ou similares
    $html = preg_replace('/<a[^>]*add[_-]?to[_-]?cart[^>]*>.*?<\/a>/is', '', $html);
    $html = preg_replace('/<a[^>]*class="[^"]*button[^"]*"[^>]*>.*?<\/a>/is', '', $html);

    // Converter para texto simples mas mantendo estrutura básica
    $allowed_tags = '<p><br><ul><ol><li><h1><h2><h3><h4><h5><h6><strong><b><em><i><u>';
    $html = strip_tags($html, $allowed_tags);

    // Limpar espaços extras e quebras de linha
    $html = preg_replace('/\n\s*\n\s*\n/s', "\n\n", $html);
    $html = preg_replace('/[ \t]+/', ' ', $html);
    $html = preg_replace('/^\s+|\s+$/m', '', $html);

    // Remover linhas que são apenas "." ou "-" ou similares
    $html = preg_replace('/^[\.\-\*\s]+$/m', '', $html);

    // Remover múltiplas quebras de linha
    $html = preg_replace('/\n{3,}/', "\n\n", $html);

    // Trim final
    $html = trim($html);

    // Se ficou muito pequeno (menos de 10 chars), provavelmente era só lixo
    if (strlen(strip_tags($html)) < 10) {
        return '';
    }

    return $html;
}

/**
 * Extrair texto limpo (sem HTML)
 */
function extractPlainText($html) {
    $text = cleanDescription($html);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

try {
    $db = getDB();

    $results = [
        'cleaned' => 0,
        'skipped' => 0,
        'details' => []
    ];

    // Buscar todos os equipamentos
    $stmt = $db->query("SELECT id, name, description, short_description FROM equipments");
    $equipments = $stmt->fetchAll();

    foreach ($equipments as $equip) {
        $id = $equip['id'];
        $name = $equip['name'];

        $originalDesc = $equip['description'];
        $originalShort = $equip['short_description'];

        // Limpar descrição longa
        $cleanDesc = cleanDescription($originalDesc);

        // Limpar descrição curta (apenas texto)
        $cleanShort = extractPlainText($originalShort);
        if (empty($cleanShort) && !empty($cleanDesc)) {
            // Se não tem descrição curta, criar uma a partir da longa
            $cleanShort = extractPlainText($cleanDesc);
            if (strlen($cleanShort) > 200) {
                $cleanShort = substr($cleanShort, 0, 197) . '...';
            }
        }

        // Verificar se houve mudança
        $changed = false;
        if ($cleanDesc !== $originalDesc || $cleanShort !== $originalShort) {
            $changed = true;
        }

        if ($changed) {
            // Atualizar no banco
            $updateStmt = $db->prepare("
                UPDATE equipments
                SET description = ?, short_description = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$cleanDesc, $cleanShort, $id]);

            $results['cleaned']++;
            $results['details'][] = [
                'id' => $id,
                'name' => $name,
                'desc_before_len' => strlen($originalDesc),
                'desc_after_len' => strlen($cleanDesc),
                'short_before_len' => strlen($originalShort),
                'short_after_len' => strlen($cleanShort)
            ];
        } else {
            $results['skipped']++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Limpeza concluída!",
        'summary' => [
            'total' => count($equipments),
            'cleaned' => $results['cleaned'],
            'skipped' => $results['skipped']
        ],
        'details' => $results['details']
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
