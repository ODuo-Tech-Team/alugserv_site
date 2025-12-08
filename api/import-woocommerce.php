<?php
/**
 * Script de Importação WooCommerce -> MySQL
 *
 * Este script puxa todos os produtos e categorias do WooCommerce
 * e insere no banco de dados MySQL local.
 *
 * USAR APENAS UMA VEZ para migrar os dados!
 * Acesse: /api/import-woocommerce.php?key=CHAVE_SECRETA
 */

// Chave de segurança para executar o script
define('IMPORT_KEY', 'alugserv2024import');

// Verificar chave de segurança
if (!isset($_GET['key']) || $_GET['key'] !== IMPORT_KEY) {
    die(json_encode(['error' => 'Chave de importação inválida. Use: ?key=' . IMPORT_KEY]));
}

// Configurações do WooCommerce
define('WC_STORE_URL', 'https://alugserv.com.br');
define('WC_CONSUMER_KEY', 'ck_6824b7f6fd84201d603999e04bb863ba25f99625');
define('WC_CONSUMER_SECRET', 'cs_806da03b0c2507ecb9fd7d04d85f5eeb3d85f7e4');

// Incluir configuração do banco
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Desabilitar limite de tempo
set_time_limit(300);

$log = [];
$errors = [];

/**
 * Fazer requisição à API do WooCommerce
 */
function wc_request($endpoint, $params = []) {
    $url = WC_STORE_URL . '/wp-json/wc/v3/' . $endpoint;

    $params['consumer_key'] = WC_CONSUMER_KEY;
    $params['consumer_secret'] = WC_CONSUMER_SECRET;
    $params['per_page'] = 100;

    $url .= '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("cURL Error: $error");
    }

    if ($httpCode !== 200) {
        throw new Exception("HTTP Error $httpCode: $response");
    }

    return json_decode($response, true);
}

/**
 * Gerar slug
 */
function makeSlug($string) {
    $slug = mb_strtolower($string, 'UTF-8');
    $slug = preg_replace('/[áàãâä]/u', 'a', $slug);
    $slug = preg_replace('/[éèêë]/u', 'e', $slug);
    $slug = preg_replace('/[íìîï]/u', 'i', $slug);
    $slug = preg_replace('/[óòõôö]/u', 'o', $slug);
    $slug = preg_replace('/[úùûü]/u', 'u', $slug);
    $slug = preg_replace('/[ç]/u', 'c', $slug);
    $slug = preg_replace('/[ñ]/u', 'n', $slug);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

try {
    $db = getDB();

    $log[] = "=== INICIANDO IMPORTAÇÃO DO WOOCOMMERCE ===";
    $log[] = "Data: " . date('Y-m-d H:i:s');
    $log[] = "";

    // ========================================
    // PASSO 1: Importar Categorias
    // ========================================
    $log[] = "--- IMPORTANDO CATEGORIAS ---";

    $wcCategories = wc_request('products/categories', ['per_page' => 100]);
    $log[] = "Encontradas " . count($wcCategories) . " categorias no WooCommerce";

    // Mapeamento de ID WooCommerce -> ID Local
    $categoryMap = [];

    // Primeiro, buscar categorias existentes
    $stmt = $db->query("SELECT id, slug FROM categories");
    $existingCategories = [];
    while ($row = $stmt->fetch()) {
        $existingCategories[$row['slug']] = $row['id'];
    }

    foreach ($wcCategories as $wcCat) {
        $slug = $wcCat['slug'] ?: makeSlug($wcCat['name']);

        // Verificar se já existe
        if (isset($existingCategories[$slug])) {
            $categoryMap[$wcCat['id']] = $existingCategories[$slug];
            $log[] = "  [EXISTE] Categoria: {$wcCat['name']} (slug: $slug)";
            continue;
        }

        // Inserir nova categoria
        try {
            $stmt = $db->prepare("
                INSERT INTO categories (name, slug, description, image, sort_order, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $wcCat['name'],
                $slug,
                strip_tags($wcCat['description'] ?? ''),
                $wcCat['image']['src'] ?? null,
                $wcCat['menu_order'] ?? 0
            ]);

            $newId = $db->lastInsertId();
            $categoryMap[$wcCat['id']] = $newId;
            $log[] = "  [NOVO] Categoria: {$wcCat['name']} (ID: $newId)";
        } catch (Exception $e) {
            $errors[] = "Erro ao inserir categoria {$wcCat['name']}: " . $e->getMessage();
        }
    }

    $log[] = "";
    $log[] = "Total de categorias mapeadas: " . count($categoryMap);
    $log[] = "";

    // ========================================
    // PASSO 2: Importar Produtos
    // ========================================
    $log[] = "--- IMPORTANDO PRODUTOS ---";

    $page = 1;
    $totalProducts = 0;
    $insertedProducts = 0;
    $updatedProducts = 0;

    do {
        $wcProducts = wc_request('products', ['page' => $page, 'per_page' => 100]);
        $count = count($wcProducts);
        $totalProducts += $count;

        $log[] = "Página $page: $count produtos";

        foreach ($wcProducts as $wcProd) {
            $slug = $wcProd['slug'] ?: makeSlug($wcProd['name']);

            // Determinar categoria
            $categoryId = null;
            if (!empty($wcProd['categories'])) {
                $wcCatId = $wcProd['categories'][0]['id'];
                $categoryId = $categoryMap[$wcCatId] ?? null;
            }

            // Se não tiver categoria, usar a primeira disponível
            if (!$categoryId) {
                $stmt = $db->query("SELECT id FROM categories LIMIT 1");
                $row = $stmt->fetch();
                $categoryId = $row ? $row['id'] : 1;
            }

            // Imagem principal
            $mainImage = null;
            $gallery = [];
            if (!empty($wcProd['images'])) {
                $mainImage = $wcProd['images'][0]['src'] ?? null;
                foreach ($wcProd['images'] as $img) {
                    if ($img['src']) {
                        $gallery[] = $img['src'];
                    }
                }
            }

            // Preço
            $price = null;
            if (!empty($wcProd['price']) && $wcProd['price'] !== '') {
                $price = (float) $wcProd['price'];
            } elseif (!empty($wcProd['regular_price']) && $wcProd['regular_price'] !== '') {
                $price = (float) $wcProd['regular_price'];
            }

            // Especificações (atributos)
            $specs = [];
            if (!empty($wcProd['attributes'])) {
                foreach ($wcProd['attributes'] as $attr) {
                    if (!empty($attr['options'])) {
                        $specs[$attr['name']] = implode(', ', $attr['options']);
                    }
                }
            }

            // Verificar se produto já existe
            $stmt = $db->prepare("SELECT id FROM equipments WHERE slug = ?");
            $stmt->execute([$slug]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Atualizar produto existente
                try {
                    $stmt = $db->prepare("
                        UPDATE equipments SET
                            name = ?,
                            description = ?,
                            short_description = ?,
                            category_id = ?,
                            image = ?,
                            gallery = ?,
                            price = ?,
                            sku = ?,
                            specs = ?,
                            stock_status = ?,
                            featured = ?,
                            status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $wcProd['name'],
                        $wcProd['description'] ?? '',
                        $wcProd['short_description'] ?? '',
                        $categoryId,
                        $mainImage,
                        json_encode($gallery),
                        $price,
                        $wcProd['sku'] ?? null,
                        json_encode($specs),
                        $wcProd['stock_status'] === 'instock' ? 'available' : 'unavailable',
                        $wcProd['featured'] ? 1 : 0,
                        $wcProd['status'] === 'publish' ? 'active' : 'inactive',
                        $existing['id']
                    ]);
                    $updatedProducts++;
                    $log[] = "  [ATUALIZADO] {$wcProd['name']}";
                } catch (Exception $e) {
                    $errors[] = "Erro ao atualizar {$wcProd['name']}: " . $e->getMessage();
                }
            } else {
                // Inserir novo produto
                try {
                    // Garantir slug único
                    $finalSlug = $slug;
                    $counter = 1;
                    do {
                        $stmt = $db->prepare("SELECT id FROM equipments WHERE slug = ?");
                        $stmt->execute([$finalSlug]);
                        if ($stmt->fetch()) {
                            $finalSlug = $slug . '-' . $counter;
                            $counter++;
                        } else {
                            break;
                        }
                    } while ($counter < 100);

                    $stmt = $db->prepare("
                        INSERT INTO equipments (
                            name, slug, description, short_description, category_id,
                            image, gallery, price, sku, specs, stock_status,
                            featured, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $wcProd['name'],
                        $finalSlug,
                        $wcProd['description'] ?? '',
                        $wcProd['short_description'] ?? '',
                        $categoryId,
                        $mainImage,
                        json_encode($gallery),
                        $price,
                        $wcProd['sku'] ?? null,
                        json_encode($specs),
                        $wcProd['stock_status'] === 'instock' ? 'available' : 'unavailable',
                        $wcProd['featured'] ? 1 : 0,
                        $wcProd['status'] === 'publish' ? 'active' : 'inactive'
                    ]);
                    $insertedProducts++;
                    $log[] = "  [NOVO] {$wcProd['name']}";
                } catch (Exception $e) {
                    $errors[] = "Erro ao inserir {$wcProd['name']}: " . $e->getMessage();
                }
            }
        }

        $page++;
    } while ($count === 100); // Continua se houver mais páginas

    $log[] = "";
    $log[] = "=== RESUMO DA IMPORTAÇÃO ===";
    $log[] = "Total de produtos no WooCommerce: $totalProducts";
    $log[] = "Produtos inseridos: $insertedProducts";
    $log[] = "Produtos atualizados: $updatedProducts";
    $log[] = "Erros: " . count($errors);
    $log[] = "";
    $log[] = "Importação concluída em: " . date('Y-m-d H:i:s');

    // Retornar resultado
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_products' => $totalProducts,
            'inserted' => $insertedProducts,
            'updated' => $updatedProducts,
            'categories_mapped' => count($categoryMap),
            'errors_count' => count($errors)
        ],
        'log' => $log,
        'errors' => $errors
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
        'errors' => $errors
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
