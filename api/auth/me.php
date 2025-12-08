<?php
/**
 * API de Verificação de Sessão - AlugServ
 * GET /api/auth/me.php
 */

require_once __DIR__ . '/../config.php';

// Apenas GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Método não permitido', 405);
}

$user = authenticate();

successResponse(['user' => $user], 'Usuário autenticado');
