<?php
/**
 * API de Logout - AlugServ
 * POST /api/auth/logout.php
 */

require_once __DIR__ . '/../config.php';

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Método não permitido', 405);
}

$token = getBearerToken();

if ($token) {
    destroySession($token);
    logActivity('logout', 'auth', null, "Logout realizado");
}

successResponse([], 'Logout realizado com sucesso');
