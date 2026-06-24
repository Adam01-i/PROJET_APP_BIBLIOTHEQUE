<?php
// ============================================================
//  middlewares/AuthMiddleware.php
//  Vérifie qu'une requête porte un JWT valide avant de laisser
//  passer vers le contrôleur
// ============================================================

require_once __DIR__ . '/../services/AuthService.php';

/**
 * Classe AuthMiddleware
 *
 * Un "middleware" est un code qui s'exécute ENTRE la réception
 * de la requête et son traitement par le contrôleur — un peu
 * comme un videur à l'entrée d'une boîte de nuit : il vérifie
 * les papiers avant de laisser entrer, mais ne sert pas les
 * boissons lui-même (ça, c'est le travail du contrôleur).
 *
 * Pourquoi un middleware et pas juste un `if` dans chaque
 * contrôleur ? Parce que copier-coller la même vérification JWT
 * dans BookController, LoanController, UserController... viole
 * le principe DRY et surtout : un seul oubli dans un contrôleur
 * = une route non protégée. Centraliser dans le routeur garantit
 * qu'aucune route protégée ne peut "oublier" cette vérification.
 */
class AuthMiddleware
{
    /**
     * Vérifie l'authentification. Si invalide, envoie une réponse
     * 401 et arrête l'exécution (exit). Si valide, retourne le
     * payload du token (contient sub/email/role) pour que le
     * contrôleur sache QUI fait la requête.
     *
     * @return array Le payload du token (jamais null — on exit avant)
     */
    public static function handle(): array
    {
        $authService = new AuthService();
        $payload     = $authService->getAuthenticatedUser();

        if ($payload === null) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401); // Unauthorized
            echo json_encode([
                'success' => false,
                'message' => 'Authentification requise. En-tête "Authorization: Bearer <token>" manquant ou invalide.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        return $payload;
    }
}