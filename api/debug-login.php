<?php
/**
 * Script de Diagnóstico de Login
 * Acesse: /api/debug-login.php
 *
 * REMOVA ESTE ARQUIVO APÓS RESOLVER O PROBLEMA!
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// 1. Verificar conexão com banco
try {
    $db = getDB();
    $result['checks']['database_connection'] = [
        'status' => 'OK',
        'message' => 'Conexão com banco estabelecida'
    ];
} catch (Exception $e) {
    $result['checks']['database_connection'] = [
        'status' => 'ERRO',
        'message' => $e->getMessage()
    ];
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Verificar se tabela users existe
try {
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    $exists = $stmt->fetch();
    $result['checks']['users_table'] = [
        'status' => $exists ? 'OK' : 'ERRO',
        'message' => $exists ? 'Tabela users existe' : 'Tabela users NÃO existe! Execute o schema.sql'
    ];
} catch (Exception $e) {
    $result['checks']['users_table'] = [
        'status' => 'ERRO',
        'message' => $e->getMessage()
    ];
}

// 3. Verificar se usuário admin existe
try {
    $stmt = $db->query("SELECT id, username, email, name, role, status, password FROM users WHERE username = 'admin'");
    $admin = $stmt->fetch();

    if ($admin) {
        $result['checks']['admin_user'] = [
            'status' => 'OK',
            'message' => 'Usuário admin encontrado',
            'data' => [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'name' => $admin['name'],
                'role' => $admin['role'],
                'status' => $admin['status'],
                'password_hash_length' => strlen($admin['password'])
            ]
        ];

        // 4. Testar senha admin123
        $testPassword = 'admin123';
        $passwordValid = password_verify($testPassword, $admin['password']);
        $result['checks']['password_test'] = [
            'status' => $passwordValid ? 'OK' : 'ERRO',
            'message' => $passwordValid
                ? "Senha 'admin123' está correta!"
                : "Senha 'admin123' NÃO confere com o hash armazenado",
            'stored_hash' => substr($admin['password'], 0, 20) . '...',
            'correct_hash_for_admin123' => password_hash('admin123', PASSWORD_DEFAULT)
        ];
    } else {
        $result['checks']['admin_user'] = [
            'status' => 'ERRO',
            'message' => 'Usuário admin NÃO encontrado! Execute o schema.sql'
        ];
    }
} catch (Exception $e) {
    $result['checks']['admin_user'] = [
        'status' => 'ERRO',
        'message' => $e->getMessage()
    ];
}

// 5. Verificar se tabela sessions existe
try {
    $stmt = $db->query("SHOW TABLES LIKE 'sessions'");
    $exists = $stmt->fetch();
    $result['checks']['sessions_table'] = [
        'status' => $exists ? 'OK' : 'ERRO',
        'message' => $exists ? 'Tabela sessions existe' : 'Tabela sessions NÃO existe!'
    ];
} catch (Exception $e) {
    $result['checks']['sessions_table'] = [
        'status' => 'ERRO',
        'message' => $e->getMessage()
    ];
}

// 6. Contar tabelas existentes
try {
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $result['checks']['all_tables'] = [
        'status' => count($tables) >= 5 ? 'OK' : 'AVISO',
        'message' => 'Tabelas encontradas: ' . count($tables),
        'tables' => $tables
    ];
} catch (Exception $e) {
    $result['checks']['all_tables'] = [
        'status' => 'ERRO',
        'message' => $e->getMessage()
    ];
}

// Resumo
$hasErrors = false;
foreach ($result['checks'] as $check) {
    if ($check['status'] === 'ERRO') {
        $hasErrors = true;
        break;
    }
}

$result['summary'] = $hasErrors
    ? 'Há erros que precisam ser corrigidos. Verifique os itens marcados como ERRO.'
    : 'Tudo parece OK! Se ainda não consegue logar, tente resetar a senha abaixo.';

// Link para resetar senha
$result['reset_password_link'] = '/api/reset-admin.php?key=alugserv2024reset';

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
