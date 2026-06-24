<?php
// ============================================================
//  controllers/BookController.php  (V2 — refactorisé)
//  Le contrôleur ne fait plus QUE du HTTP : il délègue toute
//  la logique métier et la validation à BookService.
// ============================================================

require_once __DIR__ . '/../services/BookService.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../middlewares/RoleMiddleware.php';

class BookController
{
    private BookService $service;

    public function __construct()
    {
        $this->service = new BookService();
    }

    private function sendResponse(array $data, int $statusCode = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private function getJsonBody(): ?array
    {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : null;
    }

    /**
     * GET /api/books — Public, pas d'authentification requise :
     * consulter le catalogue ne nécessite pas de compte.
     */
    public function getAll(): void
    {
        $filters = [
            'category_id' => $_GET['category_id'] ?? '',
            'available'   => $_GET['available']   ?? '',
            'search'      => $_GET['search']      ?? '',
        ];

        $books = $this->service->getAll($filters);
        $this->sendResponse(['success' => true, 'count' => count($books), 'data' => $books], 200);
    }

    public function getOne(int $id): void
    {
        if ($id <= 0) {
            $this->sendResponse(['success' => false, 'message' => 'ID invalide.'], 400);
        }

        $book = $this->service->getOne($id);

        if ($book === null) {
            $this->sendResponse(['success' => false, 'message' => 'Livre introuvable.'], 404);
        }

        $this->sendResponse(['success' => true, 'data' => $book], 200);
    }

    /**
     * POST /api/books — Protégé : seul un admin peut ajouter un livre.
     */
    public function create(): void
    {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::handle($payload, 'admin');

        $data = $this->getJsonBody();
        if ($data === null) {
            $this->sendResponse(['success' => false, 'message' => 'JSON invalide.'], 400);
        }

        $result = $this->service->create($data);

        if ($result['success']) {
            $this->sendResponse($result, 201);
        } else {
            $this->sendResponse($result, 422);
        }
    }

    /**
     * PUT /api/books/{id} — Protégé : admin uniquement.
     */
    public function update(int $id): void
    {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::handle($payload, 'admin');

        $data = $this->getJsonBody();
        if ($data === null) {
            $this->sendResponse(['success' => false, 'message' => 'JSON invalide.'], 400);
        }

        $result = $this->service->update($id, $data);

        if ($result['success']) {
            $this->sendResponse($result, 200);
        } elseif (isset($result['message']) && str_contains($result['message'], 'introuvable')) {
            $this->sendResponse($result, 404);
        } else {
            $this->sendResponse($result, 422);
        }
    }

    /**
     * DELETE /api/books/{id} — Protégé : admin uniquement.
     */
    public function delete(int $id): void
    {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::handle($payload, 'admin');

        $result = $this->service->delete($id);

        if ($result['success']) {
            $this->sendResponse(['success' => true, 'message' => 'Livre supprimé avec succès.'], 200);
        } else {
            $this->sendResponse($result, 404);
        }
    }

    public function options(): void
    {
        http_response_code(200);
        exit;
    }
}