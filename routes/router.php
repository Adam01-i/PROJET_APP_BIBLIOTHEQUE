<?php
// ============================================================
//  PHASE 7 : routes/router.php
//  Routeur PHP — analyse l'URI et la méthode HTTP,
//  puis dispatche vers le bon contrôleur
// ============================================================

require_once __DIR__ . '/../controllers/BookController.php';

/**
 * Classe Router
 *
 * Principe de fonctionnement :
 *  1. Extraire la méthode HTTP (GET, POST, PUT, DELETE)
 *  2. Nettoyer et parser l'URI demandée
 *  3. Faire correspondre l'URI à un pattern
 *  4. Appeler la méthode du contrôleur appropriée
 */
class Router
{
    private string $method; // Méthode HTTP : GET, POST, PUT, DELETE, OPTIONS
    private string $uri;    // URI nettoyée, ex: /api/books ou /api/books/5

    public function __construct()
    {
        // $_SERVER['REQUEST_METHOD'] = méthode HTTP utilisée par le client
        $this->method = $_SERVER['REQUEST_METHOD'];

        // $_SERVER['REQUEST_URI'] peut contenir la query string (?foo=bar)
        // parse_url(..., PHP_URL_PATH) extrait uniquement le chemin
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Supprimer les slashes de fin pour normaliser (/api/books/ → /api/books)
        $this->uri = rtrim($path, '/');
    }

    /**
     * Point d'entrée du routeur : analyse l'URI et dispatche
     */
    public function dispatch(): void
    {
        $controller = new BookController();

        // ⭐ ROUTE HOME / API ROOT
        if ($this->uri === '' || $this->uri === '/') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'API Bibliothèque active 🚀',
                'endpoints' => [
                    'GET /api/books',
                    'POST /api/books',
                    'GET /api/books/{id}',
                    'PUT /api/books/{id}',
                    'DELETE /api/books/{id}'
                ]
            ]);
            exit;
        }

        // OPTIONS CORS
        if ($this->method === 'OPTIONS') {
            $controller->options();
            return;
        }
        
        // ---- Pattern 1 : /api/books (sans ID) ----
        // Correspond à GET /api/books et POST /api/books
        if ($this->uri === '/api/books') {
            switch ($this->method) {
                case 'GET':
                    $controller->getAll();
                    break;
                case 'POST':
                    $controller->create();
                    break;
                default:
                    $this->methodNotAllowed();
            }
            return;
        }

        // ---- Pattern 2 : /api/books/{id} (avec ID numérique) ----
        // preg_match() teste l'URI avec une expression régulière
        // \d+ = un ou plusieurs chiffres
        // Les parenthèses capturent l'ID dans $matches[1]
        if (preg_match('#^/api/books/(\d+)$#', $this->uri, $matches)) {
            $id = (int) $matches[1]; // Convertir l'ID en entier

            switch ($this->method) {
                case 'GET':
                    $controller->getOne($id);
                    break;
                case 'PUT':
                    $controller->update($id);
                    break;
                case 'DELETE':
                    $controller->delete($id);
                    break;
                default:
                    $this->methodNotAllowed();
            }
            return;
        }

        // ---- Aucun pattern trouvé ----
        $this->notFound();
    }

    /**
     * 404 Not Found — L'URI ne correspond à aucune route
     */
    private function notFound(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => "Route '{$this->uri}' introuvable.",
            'hint' => 'Routes disponibles : GET|POST /api/books | GET|PUT|DELETE /api/books/{id}'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 405 Method Not Allowed — L'URI existe mais la méthode HTTP ne convient pas
     */
    private function methodNotAllowed(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => "Méthode HTTP '{$this->method}' non autorisée sur '{$this->uri}'."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}