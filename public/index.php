<?php
// ============================================================
//  public/index.php — Point d'entrée unique de l'API
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ------------------------------------------------------------
// CORS
// ------------------------------------------------------------
$allowedOrigins = [
    'http://localhost:8083',
    'http://127.0.0.1:8083',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
}

header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Préflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../routes/router.php';

$router = new Router();
$router->dispatch();