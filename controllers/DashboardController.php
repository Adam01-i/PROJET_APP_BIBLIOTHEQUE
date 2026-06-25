<?php
// ============================================================
//  controllers/DashboardController.php
//  Endpoint : GET /api/dashboard/stats
// ============================================================

require_once __DIR__ . '/../services/DashboardService.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';

class DashboardController
{
    private DashboardService $service;

    public function __construct()
    {
        $this->service = new DashboardService();
    }

    private function sendResponse(array $data, int $statusCode = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * GET /api/dashboard/stats
     * Protégé : authentification requise. Le CONTENU de la réponse
     * change selon le rôle (géré par DashboardService), mais la route
     * elle-même est accessible à tout utilisateur connecté — un membre
     * a parfaitement le droit de voir SES statistiques.
     */
    public function stats(): void
    {
        $payload = AuthMiddleware::handle();

        $stats = $this->service->getStatsFor((int) $payload['sub'], $payload['role'] ?? 'membre');

        $this->sendResponse(['success' => true, 'data' => $stats], 200);
    }
}