<?php
// ============================================================
//  controllers/AuthController.php  (V3 — correctif full_name dans /me)
// ============================================================

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';

class AuthController
{
    private AuthService $authService;
    private UserRepository $users;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->users       = new UserRepository();
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

    public function login(): void
    {
        $data = $this->getJsonBody();
        if ($data === null || empty($data['email']) || empty($data['password'])) {
            $this->sendResponse(['success' => false, 'message' => 'Email et mot de passe requis.'], 400);
        }

        $ip     = $_SERVER['REMOTE_ADDR'] ?? null;
        $result = $this->authService->login($data['email'], $data['password'], $ip);

        $this->sendResponse($result, $result['success'] ? 200 : 401);
    }

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
        $this->sendResponse($result, $result['success'] ? 201 : 422);
    }

    /**
     * GET /api/auth/me
     * CORRECTIF V3 : le payload du JWT ne contient que sub/email/role
     * (par conception — on garde le token léger). Mais le frontend a
     * besoin de full_name pour l'afficher de façon cohérente avec ce
     * que /auth/login retournait déjà. Plutôt que d'alourdir le JWT
     * avec des données qui peuvent changer (un changement de nom ne
     * devrait pas nécessiter un nouveau token), on fait une requête
     * supplémentaire ICI vers la base pour obtenir l'état actuel et
     * complet de l'utilisateur. Conséquence acceptée : /auth/me coûte
     * une requête SQL, contrairement à la vérification JWT seule.
     */
    public function me(): void
    {
        $payload = AuthMiddleware::handle();

        $user = $this->users->find((int) $payload['sub']);

        if ($user === null) {
            // Le token est valide mais l'utilisateur a été supprimé
            // depuis — cas limite rare mais à gérer proprement plutôt
            // que de renvoyer des données incohérentes.
            $this->sendResponse(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        $this->sendResponse([
            'success' => true,
            'user'    => [
                'id'        => $user['id'],
                'full_name' => $user['full_name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
            ]
        ], 200);
    }
}