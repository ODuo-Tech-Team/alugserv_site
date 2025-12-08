<?php
/**
 * Configurações de conexão - AlugServ
 *
 * IMPORTANTE: Preencha as credenciais abaixo antes de usar
 */

// ===== WOOCOMMERCE REST API =====
// Gere as credenciais em: WooCommerce > Settings > Advanced > REST API
define('WC_STORE_URL', 'https://alugserv.com.br'); // URL da loja WooCommerce
define('WC_CONSUMER_KEY', 'ck_6824b7f6fd84201d603999e04bb863ba25f99625'); // Consumer Key
define('WC_CONSUMER_SECRET', 'cs_806da03b0c2507ecb9fd7d04d85f5eeb3d85f7e4'); // Consumer Secret
define('WC_API_VERSION', 'wc/v3'); // Versão da API

// ===== MYSQL (OPCIONAL - PARA CACHE) =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'alugserv_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ===== CONFIGURAÇÕES GERAIS =====
define('CACHE_ENABLED', false); // Habilitar cache MySQL
define('CACHE_DURATION', 3600); // Duração do cache em segundos (1 hora)
define('PRODUCTS_PER_PAGE', 12); // Produtos por página

// ===== CORS HEADERS =====
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== FUNÇÕES AUXILIARES =====

/**
 * Conexão com MySQL (para cache)
 */
function getDBConnection() {
    if (!CACHE_ENABLED) return null;

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}

/**
 * Fazer requisição à API WooCommerce
 */
function woocommerceRequest($endpoint, $params = []) {
    $url = WC_STORE_URL . '/wp-json/' . WC_API_VERSION . '/' . $endpoint;

    // Adicionar parâmetros de autenticação
    $params['consumer_key'] = WC_CONSUMER_KEY;
    $params['consumer_secret'] = WC_CONSUMER_SECRET;

    // Construir URL com parâmetros
    $url .= '?' . http_build_query($params);

    // Inicializar cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => true, 'message' => $error];
    }

    if ($httpCode !== 200) {
        return ['error' => true, 'message' => 'HTTP Error: ' . $httpCode, 'code' => $httpCode];
    }

    return json_decode($response, true);
}

/**
 * Resposta JSON padronizada
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Resposta de erro
 */
function errorResponse($message, $statusCode = 400) {
    jsonResponse(['error' => true, 'message' => $message], $statusCode);
}

/**
 * Sanitizar entrada
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}
