<?php
// ============================================================
//  services/AuthService.php
//  Logique métier d'authentification : login, inscription,
//  génération de tokens JWT
// ============================================================

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/ActivityLogRepository.php';
require_once __DIR__ . '/JwtService.php';

/**
 * Classe AuthService
 *
 * Pourquoi ce code n'est-il pas dans AuthController directement ?
 * Parce que "vérifier un mot de passe", "générer un token", et
 * "logger la tentative" sont des règles métier réutilisables —
 * demain, si on ajoute une route "renouveler le token" ou une
 * CLI d'administration, ces mêmes règles seront réutilisées sans
 * dépendre du contexte HTTP du contrôleur.
 */
class AuthService
{
    private UserRepository $users;
    private ActivityLogRepository $logs;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->logs  = new ActivityLogRepository();
    }

    /**
     * Tente une connexion. Retourne un token JWT si succès.
     *
     * @param string $email
     * @param string $password Mot de passe en clair (saisi par l'utilisateur)
     * @param string|null $ip  Adresse IP de la requête, pour le log
     * @return array ['success' => bool, 'token' => ?string, 'user' => ?array, 'message' => ?string]
     */
    public function login(string $email, string $password, ?string $ip = null): array
    {
        $user = $this->users->findByEmailWithPassword($email);

        // On vérifie l'existence ET le mot de passe dans le même bloc,
        // avec le même message d'erreur générique dans les deux cas.
        // Pourquoi ? Si on répondait "email inconnu" vs "mot de passe
        // incorrect" différemment, un attaquant pourrait déduire quels
        // emails existent dans la base (énumération de comptes).
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->logs->log(
                $user['id'] ?? null,
                'login_failed',
                "Tentative de connexion échouée pour {$email}",
                $ip
            );
            return ['success' => false, 'message' => 'Email ou mot de passe incorrect.'];
        }

        if (!$user['is_active']) {
            $this->logs->log($user['id'], 'login_blocked', 'Compte désactivé', $ip);
            return ['success' => false, 'message' => 'Ce compte a été désactivé.'];
        }

        // Le token embarque les informations nécessaires pour
        // identifier l'utilisateur et son rôle SANS requête SQL
        // supplémentaire à chaque appel API (c'est tout l'intérêt
        // du "stateless" : le token contient déjà ce qu'il faut).
        $token = JwtService::generate([
            'sub'   => $user['id'],       // "subject" = à qui appartient ce token
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);

        $this->logs->log($user['id'], 'login_success', 'Connexion réussie', $ip);

        return [
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'        => $user['id'],
                'full_name' => $user['full_name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
            ],
        ];
    }

    /**
     * Inscrit un nouvel utilisateur (rôle "membre" par défaut —
     * un membre ne peut pas s'auto-promouvoir admin à l'inscription).
     */
    public function register(string $fullName, string $email, string $password): array
    {
        if ($this->users->emailExists($email)) {
            return ['success' => false, 'message' => 'Cet email est déjà utilisé.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
        }

        // PASSWORD_BCRYPT : algorithme de hachage adaptatif, conçu
        // spécifiquement pour les mots de passe (contrairement à
        // md5/sha1 qui sont rapides — donc faciles à casser par
        // force brute — bcrypt est volontairement lent).
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $userId = $this->users->create([
            'full_name'     => $fullName,
            'email'         => $email,
            'password_hash' => $hash,
            'role'          => 'membre',
        ]);

        $this->logs->log($userId, 'user_registered', "Inscription de {$email}");

        return ['success' => true, 'id' => $userId];
    }

    /**
     * Extrait et vérifie le token JWT depuis le header Authorization.
     * Utilisé par AuthMiddleware avant chaque route protégée.
     *
     * @return array|null Le payload du token si valide, sinon null
     */
    public function getAuthenticatedUser(): ?array
    {
        $header = '';

        // 1) Cas classique
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        }
        // 2) Fallback parfois utilisé par Apache
        elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        // 3) Fallback robuste : lire directement les headers Apache
        elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();

            // Les clés peuvent varier en casse selon l'environnement
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $header = $value;
                    break;
                }
            }
        }

        // Format attendu : Bearer <token>
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            return null;
        }

        return JwtService::verify($matches[1]);
    }
}