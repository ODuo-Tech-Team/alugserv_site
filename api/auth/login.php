<?php
/**
 * API de Login - AlugServ
 * POST /api/auth/login.php
 */

require_once __DIR__ . '/../config.php';

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Método não permitido', 405);
}

$data = getRequestData();

// Validar campos
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    errorResponse('Usuário e senha são obrigatórios');
}

try {
    $db = getDB();

    // Buscar usuário
    $stmt = $db->prepare("
        SELECT id, username, email, name, password, role, status
        FROM users
        WHERE (username = ? OR email = ?) AND status = 'active'
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user) {
        logActivity('login_failed', 'auth', null, "Tentativa de login com usuário: {$username}");
        errorResponse('Credenciais inválidas', 401);
    }

    // Verificar senha
    if (!password_verify($password, $user['password'])) {
        logActivity('login_failed', 'auth', $user['id'], "Senha incorreta para: {$username}");
        errorResponse('Credenciais inválidas', 401);
    }

    // Criar sessão
    $token = createSession($user['id']);

    // Log de sucesso
    logActivity('login', 'auth', $user['id'], "Login realizado");

    // Resposta
    successResponse([
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role']
        ]
    ], 'Login realizado com sucesso');

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    errorResponse('Erro ao processar login', 500);
}
