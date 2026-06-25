<?php
// ============================================================
//  routes/router.php  (V4 — ajout route dashboard)
// ============================================================

require_once __DIR__ . '/../controllers/BookController.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/LoanController.php';
require_once __DIR__ . '/../controllers/CategoryController.php';
require_once __DIR__ . '/../controllers/DashboardController.php';

class Router
{
    private string $method;
    private string $uri;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $path         = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->uri    = rtrim($path, '/');
    }

    public function dispatch(): void
    {
        if ($this->method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // ============================================================
        //  DASHBOARD
        // ============================================================
        if ($this->uri === '/api/dashboard/stats' && $this->method === 'GET') {
            (new DashboardController())->stats();
            return;
        }

        // ============================================================
        //  AUTH
        // ============================================================
        if ($this->uri === '/api/auth/login' && $this->method === 'POST') {
            (new AuthController())->login();
            return;
        }

        if ($this->uri === '/api/auth/register' && $this->method === 'POST') {
            (new AuthController())->register();
            return;
        }

        if ($this->uri === '/api/auth/me' && $this->method === 'GET') {
            (new AuthController())->me();
            return;
        }

        // ============================================================
        //  LOANS
        // ============================================================
        if ($this->uri === '/api/loans/borrow' && $this->method === 'POST') {
            (new LoanController())->borrow();
            return;
        }

        if ($this->uri === '/api/loans/return' && $this->method === 'POST') {
            (new LoanController())->returnBook();
            return;
        }

        if ($this->uri === '/api/loans' && $this->method === 'GET') {
            (new LoanController())->getAll();
            return;
        }

        // ============================================================
        //  CATEGORIES
        // ============================================================
        if ($this->uri === '/api/categories') {
            $controller = new CategoryController();
            switch ($this->method) {
                case 'GET':  $controller->getAll(); return;
                case 'POST': $controller->create();  return;
                default:     $this->methodNotAllowed(); return;
            }
        }

        if (preg_match('#^/api/categories/(\d+)$#', $this->uri, $matches)) {
            $id         = (int) $matches[1];
            $controller = new CategoryController();
            switch ($this->method) {
                case 'GET':    $controller->getOne($id);  return;
                case 'PUT':    $controller->update($id);  return;
                case 'DELETE': $controller->delete($id);  return;
                default:       $this->methodNotAllowed(); return;
            }
        }

        // ============================================================
        //  BOOKS
        // ============================================================
        if ($this->uri === '/api/books') {
            $controller = new BookController();
            switch ($this->method) {
                case 'GET':  $controller->getAll(); return;
                case 'POST': $controller->create();  return;
                default:     $this->methodNotAllowed(); return;
            }
        }

        if (preg_match('#^/api/books/(\d+)$#', $this->uri, $matches)) {
            $id         = (int) $matches[1];
            $controller = new BookController();
            switch ($this->method) {
                case 'GET':    $controller->getOne($id);  return;
                case 'PUT':    $controller->update($id);  return;
                case 'DELETE': $controller->delete($id);  return;
                default:       $this->methodNotAllowed(); return;
            }
        }

        // ============================================================
        //  RACINE
        // ============================================================
        if ($this->uri === '' || $this->uri === '/') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'   => true,
                'message'   => 'API Bibliothèque V4 active 🚀',
                'endpoints' => [
                    'POST /api/auth/login',
                    'POST /api/auth/register',
                    'GET  /api/auth/me',
                    'GET  /api/dashboard/stats (auth)',
                    'GET  /api/books',
                    'GET  /api/books/{id}',
                    'POST /api/books          (admin)',
                    'PUT  /api/books/{id}     (admin)',
                    'DELETE /api/books/{id}   (admin)',
                    'GET  /api/categories',
                    'POST /api/categories     (admin)',
                    'GET  /api/loans          (auth)',
                    'POST /api/loans/borrow   (auth)',
                    'POST /api/loans/return   (auth)',
                ]
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->notFound();
    }

    private function notFound(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Route '{$this->uri}' introuvable."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function methodNotAllowed(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => "Méthode '{$this->method}' non autorisée sur '{$this->uri}'."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}