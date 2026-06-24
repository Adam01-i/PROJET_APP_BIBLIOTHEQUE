<?php
// ============================================================
//  public/index.php — Point d'entrée unique de l'API
// ============================================================

// Affichage des erreurs en dev
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ------------------------------------------------------------
// CORS : à envoyer AVANT toute sortie
// ------------------------------------------------------------
header("Access-Control-Allow-Origin: http://localhost:8082");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Répondre immédiatement aux preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Charger le routeur
require_once __DIR__ . '/../routes/router.php';

// Router
$router = new Router();
$router->dispatch();