<?php
// ============================================================
//  controllers/LoanController.php  (V3 — correctif sécurité retour)
// ============================================================

require_once __DIR__ . '/../services/LoanService.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../middlewares/RoleMiddleware.php';

class LoanController
{
    private LoanService $service;

    public function __construct()
    {
        $this->service = new LoanService();
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

    public function borrow(): void
    {
        $payload = AuthMiddleware::handle();

        $data = $this->getJsonBody();
        if ($data === null || empty($data['book_id'])) {
            $this->sendResponse(['success' => false, 'message' => 'book_id requis.'], 400);
        }

        $result = $this->service->borrowBook((int) $data['book_id'], (int) $payload['sub']);
        $this->sendResponse($result, $result['success'] ? 201 : 409);
    }

    /**
     * POST /api/loans/return
     * CORRECTIF V3 : transmet désormais le rôle de l'appelant au
     * service, pour que celui-ci applique la bonne règle de propriété
     * (membre = ses propres emprunts uniquement, admin = tous).
     */
    public function returnBook(): void
    {
        $payload = AuthMiddleware::handle();

        $data = $this->getJsonBody();
        if ($data === null || empty($data['book_id'])) {
            $this->sendResponse(['success' => false, 'message' => 'book_id requis.'], 400);
        }

        $result = $this->service->returnBook(
            (int) $data['book_id'],
            (int) $payload['sub'],
            $payload['role'] ?? 'membre'
        );

        $this->sendResponse($result, $result['success'] ? 200 : 409);
    }

    public function getAll(): void
    {
        $payload = AuthMiddleware::handle();

        $filters = [
            'status'  => $_GET['status']  ?? '',
            'book_id' => $_GET['book_id'] ?? '',
            'user_id' => $payload['role'] === 'admin'
                ? ($_GET['user_id'] ?? '')
                : $payload['sub'],
        ];

        $loans = $this->service->listLoans($filters);
        $this->sendResponse(['success' => true, 'count' => count($loans), 'data' => $loans], 200);
    }

    public function options(): void
    {
        http_response_code(200);
        exit;
    }
}