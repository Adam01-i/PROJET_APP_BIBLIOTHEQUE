<?php
// ============================================================
//  controllers/LoanController.php
//  Endpoints :
//    POST /api/loans/borrow   (emprunter)
//    POST /api/loans/return   (retourner)
//    GET  /api/loans          (lister, avec filtres)
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

    /**
     * POST /api/loans/borrow
     * Body : { "book_id": 6 }
     * Protégé : tout utilisateur authentifié (admin ou membre)
     * peut emprunter pour lui-même.
     */
    public function borrow(): void
    {
        $payload = AuthMiddleware::handle();

        $data = $this->getJsonBody();
        if ($data === null || empty($data['book_id'])) {
            $this->sendResponse(['success' => false, 'message' => 'book_id requis.'], 400);
        }

        // $payload['sub'] = l'ID de l'utilisateur authentifié, extrait
        // du token JWT — on ne fait jamais confiance à un user_id
        // envoyé dans le body : ce serait laisser n'importe qui
        // emprunter "au nom" de quelqu'un d'autre.
        $result = $this->service->borrowBook((int) $data['book_id'], (int) $payload['sub']);

        $this->sendResponse($result, $result['success'] ? 201 : 409);
    }

    /**
     * POST /api/loans/return
     * Body : { "book_id": 6 }
     * Protégé : un membre peut retourner un livre qu'il a emprunté ;
     * un admin peut retourner n'importe quel livre (ex: retour en
     * personne à l'accueil, traité par le bibliothécaire admin).
     */
    public function returnBook(): void
    {
        $payload = AuthMiddleware::handle();

        $data = $this->getJsonBody();
        if ($data === null || empty($data['book_id'])) {
            $this->sendResponse(['success' => false, 'message' => 'book_id requis.'], 400);
        }

        $result = $this->service->returnBook((int) $data['book_id'], (int) $payload['sub']);

        $this->sendResponse($result, $result['success'] ? 200 : 409);
    }

    /**
     * GET /api/loans
     * Query params : ?status=active|returned, ?user_id=2, ?book_id=6
     * Protégé : un membre ne voit que SES propres emprunts ; un admin
     * voit tout (filtre user_id ignoré pour un membre, forcé à son
     * propre ID).
     */
    public function getAll(): void
    {
        $payload = AuthMiddleware::handle();

        $filters = [
            'status'  => $_GET['status']  ?? '',
            'book_id' => $_GET['book_id'] ?? '',
            // Un membre ne peut JAMAIS lister les emprunts d'un autre :
            // on ignore le user_id de la query string pour lui et on
            // force le sien, peu importe ce qu'il a mis dans l'URL.
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