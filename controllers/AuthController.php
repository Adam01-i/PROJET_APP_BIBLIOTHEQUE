<?php
// ============================================================
//  controllers/AuthController.php
//  Endpoints : POST /api/auth/login, POST /api/auth/register,
//              GET  /api/auth/me
// ============================================================

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
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
     * POST /api/auth/login
     * Body attendu : { "email": "...", "password": "..." }
     */
    public function login(): void
    {
        $data = $this->getJsonBody();
        if ($data === null || empty($data['email']) || empty($data['password'])) {
            $this->sendResponse([
                'success' => false,
                'message' => 'Email et mot de passe requis.'
            ], 400);
        }

        // $_SERVER['REMOTE_ADDR'] = adresse IP du client qui fait la requête
        $ip     = $_SERVER['REMOTE_ADDR'] ?? null;
        $result = $this->authService->login($data['email'], $data['password'], $ip);

        if ($result['success']) {
            $this->sendResponse($result, 200);
        } else {
            // 401 plutôt que 400 : c'est une question d'identité,
            // pas de format de requête mal formé.
            $this->sendResponse($result, 401);
        }
    }

    /**
     * POST /api/auth/register
     * Body attendu : { "full_name": "...", "email": "...", "password": "..." }
     */
    public function register(): void
    {
        $data = $this->getJsonBody();
        if ($data === null || empty($data['full_name']) || empty($data['email']) || empty($data['password'])) {
            $this->sendResponse([
                'success' => false,
                'message' => 'Nom complet, email et mot de passe requis.'
            ], 400);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(['success' => false, 'message' => 'Email invalide.'], 422);
        }

        $result = $this->authService->register($data['full_name'], $data['email'], $data['password']);

        if ($result['success']) {
            $this->sendResponse($result, 201);
        } else {
            $this->sendResponse($result, 422);
        }
    }

    /**
     * GET /api/auth/me
     * Retourne les informations de l'utilisateur authentifié.
     * Route protégée : passe par AuthMiddleware avant d'arriver ici.
     */
    public function me(): void
    {
        $payload = AuthMiddleware::handle(); // exit si non authentifié

        $this->sendResponse([
            'success' => true,
            'user'    => [
                'id'    => $payload['sub'],
                'email' => $payload['email'],
                'role'  => $payload['role'],
            ]
        ], 200);
    }
}