<?php
/**
 * Script para Resetar Senha do Admin
 * Acesse: /api/reset-admin.php?key=alugserv2024reset
 *
 * REMOVA ESTE ARQUIVO APÓS USAR!
 */

// Chave de segurança
define('RESET_KEY', 'alugserv2024reset');

if (!isset($_GET['key']) || $_GET['key'] !== RESET_KEY) {
    die(json_encode(['error' => 'Chave inválida']));
}

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();

    // Nova senha
    $newPassword = 'admin123';
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Verificar se usuário admin existe
    $stmt = $db->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin) {
        // Atualizar senha
        $stmt = $db->prepare("UPDATE users SET password = ?, status = 'active' WHERE username = 'admin'");
        $stmt->execute([$newHash]);

        echo json_encode([
            'success' => true,
            'message' => 'Senha do admin resetada com sucesso!',
            'credentials' => [
                'username' => 'admin',
                'password' => $newPassword
            ],
            'action' => 'DELETE este arquivo após usar: /api/reset-admin.php'
        ], JSON_PRETTY_PRINT);
    } else {
        // Criar usuário admin
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, name, role, status)
            VALUES ('admin', 'admin@alugserv.com.br', ?, 'Administrador', 'admin', 'active')
        ");
        $stmt->execute([$newHash]);

        echo json_encode([
            'success' => true,
            'message' => 'Usuário admin criado com sucesso!',
            'credentials' => [
                'username' => 'admin',
                'password' => $newPassword
            ],
            'action' => 'DELETE este arquivo após usar: /api/reset-admin.php'
        ], JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
