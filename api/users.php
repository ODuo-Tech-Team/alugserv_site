<?php
/**
 * API de Usuários - AlugServ
 *
 * GET    /api/users.php           - Listar todos (requer admin)
 * GET    /api/users.php?id=1      - Buscar por ID (requer admin)
 * POST   /api/users.php           - Criar (requer admin)
 * PUT    /api/users.php?id=1      - Atualizar (requer admin)
 * DELETE /api/users.php?id=1      - Deletar (requer admin)
 */

require_once 'config.php';

$method = getRequestMethod();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    switch ($method) {
        case 'GET':
            requireAdmin();
            if ($id) {
                getUser($id);
            } else {
                listUsers();
            }
            break;

        case 'POST':
            requireAdmin();
            createUser();
            break;

        case 'PUT':
            requireAdmin();
            if (!$id) errorResponse('ID é obrigatório');
            updateUser($id);
            break;

        case 'DELETE':
            requireAdmin();
            if (!$id) errorResponse('ID é obrigatório');
            deleteUser($id);
            break;

        default:
            errorResponse('Método não permitido', 405);
    }
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    errorResponse('Erro interno do servidor', 500);
}

// ===== FUNÇÕES =====

/**
 * Formatar usuário para resposta (sem senha)
 */
function formatUser($row) {
    return [
        'id' => (int)$row['id'],
        'username' => $row['username'],
        'email' => $row['email'],
        'name' => $row['name'],
        'role' => $row['role'],
        'status' => $row['status'],
        'last_login' => $row['last_login'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

/**
 * Listar todos os usuários
 */
function listUsers() {
    $db = getDB();
    $status = $_GET['status'] ?? null;
    $role = $_GET['role'] ?? null;

    $where = [];
    $params = [];

    if ($status) {
        $where[] = "status = ?";
        $params[] = $status;
    }

    if ($role) {
        $where[] = "role = ?";
        $params[] = $role;
    }

    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

    $stmt = $db->prepare("
        SELECT * FROM users
        {$whereClause}
        ORDER BY name ASC
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $formattedUsers = array_map('formatUser', $users);

    successResponse(['users' => $formattedUsers]);
}

/**
 * Buscar usuário por ID
 */
function getUser($id) {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        errorResponse('Usuário não encontrado', 404);
    }

    successResponse(['user' => formatUser($user)]);
}

/**
 * Criar usuário
 */
function createUser() {
    $db = getDB();
    $data = getRequestData();

    // Validar campos obrigatórios
    $username = sanitize($data['username'] ?? '');
    $email = sanitize($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $name = sanitize($data['name'] ?? '');

    if (empty($username)) {
        errorResponse('Username é obrigatório');
    }
    if (empty($email)) {
        errorResponse('Email é obrigatório');
    }
    if (empty($password)) {
        errorResponse('Senha é obrigatória');
    }
    if (strlen($password) < 6) {
        errorResponse('Senha deve ter pelo menos 6 caracteres');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        errorResponse('Email inválido');
    }

    // Verificar username único
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        errorResponse('Username já existe');
    }

    // Verificar email único
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        errorResponse('Email já cadastrado');
    }

    // Hash da senha
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Inserir
    $stmt = $db->prepare("
        INSERT INTO users (username, email, password, name, role, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $username,
        $email,
        $passwordHash,
        $name ?: $username,
        $data['role'] ?? 'editor',
        $data['status'] ?? 'active'
    ]);

    $userId = $db->lastInsertId();
    logActivity('create', 'user', $userId, "Usuário criado: {$username}");

    successResponse(['id' => $userId], 'Usuário criado com sucesso');
}

/**
 * Atualizar usuário
 */
function updateUser($id) {
    $db = getDB();
    $data = getRequestData();

    // Verificar se existe
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        errorResponse('Usuário não encontrado', 404);
    }

    // Preparar dados
    $username = sanitize($data['username'] ?? $user['username']);
    $email = sanitize($data['email'] ?? $user['email']);
    $name = sanitize($data['name'] ?? $user['name']);

    // Validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        errorResponse('Email inválido');
    }

    // Verificar username único (se mudou)
    if ($username !== $user['username']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            errorResponse('Username já existe');
        }
    }

    // Verificar email único (se mudou)
    if ($email !== $user['email']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            errorResponse('Email já cadastrado');
        }
    }

    // Preparar password (apenas se fornecido)
    $passwordHash = $user['password'];
    if (!empty($data['password'])) {
        if (strlen($data['password']) < 6) {
            errorResponse('Senha deve ter pelo menos 6 caracteres');
        }
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    // Atualizar
    $stmt = $db->prepare("
        UPDATE users SET
            username = ?,
            email = ?,
            password = ?,
            name = ?,
            role = ?,
            status = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $username,
        $email,
        $passwordHash,
        $name,
        $data['role'] ?? $user['role'],
        $data['status'] ?? $user['status'],
        $id
    ]);

    logActivity('update', 'user', $id, "Usuário atualizado: {$username}");

    successResponse([], 'Usuário atualizado com sucesso');
}

/**
 * Deletar usuário
 */
function deleteUser($id) {
    $db = getDB();

    // Verificar se existe
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        errorResponse('Usuário não encontrado', 404);
    }

    // Não permitir deletar a si mesmo
    $currentUser = authenticate();
    if ($currentUser['id'] == $id) {
        errorResponse('Você não pode deletar sua própria conta');
    }

    // Não permitir deletar o último admin
    if ($user['role'] === 'admin') {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'");
        $stmt->execute();
        $adminCount = $stmt->fetch()['count'];

        if ($adminCount <= 1) {
            errorResponse('Não é possível deletar o último administrador');
        }
    }

    // Deletar sessões do usuário
    $stmt = $db->prepare("DELETE FROM sessions WHERE user_id = ?");
    $stmt->execute([$id]);

    // Deletar usuário
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    logActivity('delete', 'user', $id, "Usuário deletado: {$user['username']}");

    successResponse([], 'Usuário deletado com sucesso');
}
