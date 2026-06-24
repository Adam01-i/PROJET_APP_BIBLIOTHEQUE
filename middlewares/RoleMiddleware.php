<?php
// ============================================================
//  middlewares/RoleMiddleware.php
//  Vérifie que l'utilisateur authentifié a le rôle requis
// ============================================================

/**
 * Classe RoleMiddleware
 *
 * S'utilise APRÈS AuthMiddleware (qui garantit qu'on a déjà un
 * payload valide). Celui-ci ajoute une deuxième barrière :
 * "tu es bien connecté, mais as-tu le DROIT de faire ça ?"
 *
 * Exemple : un membre authentifié peut consulter les livres
 * (AuthMiddleware suffit), mais seul un admin peut en supprimer
 * un (il faut en plus RoleMiddleware::handle($payload, 'admin')).
 */
class RoleMiddleware
{
    /**
     * @param array  $payload      Le payload JWT (retourné par AuthMiddleware)
     * @param string ...$allowedRoles  Un ou plusieurs rôles autorisés
     */
    public static function handle(array $payload, string ...$allowedRoles): void
    {
        $userRole = $payload['role'] ?? null;

        if (!in_array($userRole, $allowedRoles, true)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403); // Forbidden — authentifié, mais pas autorisé
            echo json_encode([
                'success' => false,
                'message' => 'Accès refusé : rôle insuffisant pour cette action.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}