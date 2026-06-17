<?php
// ============================================================
//  public/index.php — Point d'entrée unique de l'API
//  Toutes les requêtes HTTP arrivent ici (via .htaccess ou CLI)
// ============================================================

// Autoriser l'affichage des erreurs en développement
// En production : error_reporting(0) et log_errors = On
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Charger le routeur (qui chargera lui-même le contrôleur et le modèle)
require_once __DIR__ . '/../routes/router.php';

// Instancier le routeur et déclencher l'analyse de la requête
$router = new Router();
$router->dispatch();