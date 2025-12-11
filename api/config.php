<?php
/**
 * Configurações de conexão - AlugServ
 *
 * Sistema com MySQL - Carrega variáveis do .env
 */

// ===== CARREGAR VARIÁVEIS DE AMBIENTE =====
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}

// ===== CONFIGURAÇÕES DO BANCO DE DADOS =====
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'alugserv_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// ===== CONFIGURAÇÕES GERAIS =====
define('ITEMS_PER_PAGE', 12);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Detectar base path automaticamente
$basePath = '';
if (isset($_SERVER['REQUEST_URI'])) {
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    // Remove /api se existir
    $basePath = str_replace('/api', '', $scriptPath);
    // Garantir que termina com /
    $basePath = rtrim($basePath, '/') . '/';
}
define('UPLOAD_URL', $basePath . 'uploads/');

define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('SESSION_DURATION', 86400 * 7); // 7 dias

// ===== CORS HEADERS =====
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== CONEXÃO COM O BANCO DE DADOS =====

$db = null;

/**
 * Obter conexão com o banco de dados
 */
function getDB() {
    global $db;

    if ($db === null) {
        try {
            $db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            errorResponse('Erro de conexão com o banco de dados', 500);
        }
    }

    return $db;
}

// ===== FUNÇÕES DE RESPOSTA =====

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
    jsonResponse(['success' => false, 'error' => true, 'message' => $message], $statusCode);
}

/**
 * Resposta de sucesso
 */
function successResponse($data = [], $message = 'Operação realizada com sucesso') {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

// ===== FUNÇÕES DE AUTENTICAÇÃO =====

/**
 * Gerar token de sessão
 */
function generateToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Obter token do header Authorization
 */
function getBearerToken() {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * Verificar autenticação e retornar usuário
 */
function authenticate() {
    $token = getBearerToken();

    if (!$token) {
        errorResponse('Token de autenticação não fornecido', 401);
    }

    $db = getDB();

    // Buscar sessão válida
    $stmt = $db->prepare("
        SELECT s.*, u.id as user_id, u.username, u.email, u.name, u.role, u.status
        FROM sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.token = ? AND s.expires_at > NOW() AND u.status = 'active'
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch();

    if (!$session) {
        errorResponse('Sessão inválida ou expirada', 401);
    }

    return [
        'id' => $session['user_id'],
        'username' => $session['username'],
        'email' => $session['email'],
        'name' => $session['name'],
        'role' => $session['role']
    ];
}

/**
 * Verificar se usuário é admin
 */
function requireAdmin() {
    $user = authenticate();

    if ($user['role'] !== 'admin') {
        errorResponse('Acesso não autorizado', 403);
    }

    return $user;
}

/**
 * Criar sessão para usuário
 */
function createSession($userId) {
    $db = getDB();
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);

    // Remover sessões antigas do usuário
    $stmt = $db->prepare("DELETE FROM sessions WHERE user_id = ? OR expires_at < NOW()");
    $stmt->execute([$userId]);

    // Criar nova sessão
    $stmt = $db->prepare("
        INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $token,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $expiresAt
    ]);

    // Atualizar último login
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$userId]);

    return $token;
}

/**
 * Destruir sessão
 */
function destroySession($token) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM sessions WHERE token = ?");
    $stmt->execute([$token]);
}

// ===== FUNÇÕES AUXILIARES =====

/**
 * Sanitizar entrada
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)));
}

/**
 * Gerar slug a partir de string
 */
function generateSlug($string) {
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

/**
 * Obter dados do request (JSON ou form-data)
 */
function getRequestData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }

    return array_merge($_POST, $_GET);
}

/**
 * Obter método HTTP (suporte a PUT/DELETE via _method)
 */
function getRequestMethod() {
    $method = $_SERVER['REQUEST_METHOD'];

    // Suporte a _method em POST (para FormData com PUT/DELETE)
    if ($method === 'POST') {
        if (isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        } elseif (isset($_GET['_method'])) {
            $method = strtoupper($_GET['_method']);
        }
    }

    return $method;
}

/**
 * Upload de arquivo
 */
function uploadFile($file, $subdir = '') {
    error_log("=== UPLOAD DEBUG ===");
    error_log("File info: " . print_r($file, true));
    error_log("Subdir: " . $subdir);

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        error_log("Arquivo não foi enviado ou não é válido");
        return null;
    }

    // Verificar tamanho
    if ($file['size'] > MAX_FILE_SIZE) {
        error_log("Arquivo muito grande: " . $file['size']);
        throw new Exception('Arquivo muito grande. Máximo: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
    }

    // Verificar extensão
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    error_log("Extensão: " . $ext);
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        error_log("Extensão não permitida: " . $ext);
        throw new Exception('Tipo de arquivo não permitido');
    }

    // Criar diretório se não existir
    $uploadPath = UPLOAD_DIR . $subdir;
    error_log("Upload path: " . $uploadPath);
    if (!is_dir($uploadPath)) {
        error_log("Criando diretório: " . $uploadPath);
        mkdir($uploadPath, 0755, true);
    }

    // Gerar nome único
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $uploadPath . '/' . $filename;
    error_log("Filepath: " . $filepath);

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        error_log("Erro ao mover arquivo de " . $file['tmp_name'] . " para " . $filepath);
        throw new Exception('Erro ao salvar arquivo');
    }

    $url = UPLOAD_URL . $subdir . '/' . $filename;
    error_log("URL gerada: " . $url);
    error_log("=== FIM UPLOAD DEBUG ===");

    return $url;
}

/**
 * Deletar arquivo
 */
function deleteFile($path) {
    if (empty($path)) return;

    $fullPath = __DIR__ . '/..' . $path;
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
}

/**
 * Registrar atividade
 */
function logActivity($action, $entityType, $entityId = null, $description = null) {
    try {
        $db = getDB();
        $user = null;

        try {
            $token = getBearerToken();
            if ($token) {
                $stmt = $db->prepare("SELECT user_id FROM sessions WHERE token = ?");
                $stmt->execute([$token]);
                $session = $stmt->fetch();
                $user = $session['user_id'] ?? null;
            }
        } catch (Exception $e) {}

        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, action, entity_type, entity_id, description, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user,
            $action,
            $entityType,
            $entityId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * Paginação
 */
function paginate($query, $params, $page = 1, $perPage = ITEMS_PER_PAGE) {
    $db = getDB();
    $page = max(1, (int)$page);
    $perPage = min(100, max(1, (int)$perPage));
    $offset = ($page - 1) * $perPage;

    // Contar total
    $countQuery = preg_replace('/SELECT .+ FROM/i', 'SELECT COUNT(*) as total FROM', $query);
    $countQuery = preg_replace('/ORDER BY .+$/i', '', $countQuery);
    $countQuery = preg_replace('/LIMIT .+$/i', '', $countQuery);

    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $total = (int)$stmt->fetch()['total'];

    // Buscar itens
    $query .= " LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    return [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage),
            'has_more' => ($page * $perPage) < $total
        ]
    ];
}
