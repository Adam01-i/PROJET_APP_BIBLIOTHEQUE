<?php
// ============================================================
//  config/jwt.php
//  Paramètres JWT (JSON Web Token)
// ============================================================

/**
 * Classe JwtConfig
 *
 * Centralise les paramètres JWT pour que JwtService (services/)
 * n'ait jamais à connaître la clé secrète ou la durée d'expiration
 * en dur dans son code — même principe que Database avec DB_HOST.
 */
class JwtConfig
{
    /**
     * Clé secrète utilisée pour signer les tokens.
     *
     * ⚠️ CRITIQUE : en production, cette clé doit être longue,
     * aléatoire, et JAMAIS commitée dans Git. Génère-en une avec :
     *   openssl rand -base64 32
     * Puis mets-la dans .env sous JWT_SECRET, jamais en dur ici.
     *
     * Le fallback ci-dessous n'est utilisable QU'EN DÉVELOPPEMENT.
     */
    public static function secret(): string
    {
        return getenv('JWT_SECRET') ?: 'CHANGE_MOI_EN_PRODUCTION_cle_dev_uniquement';
    }

    /**
     * Durée de validité d'un token, en secondes.
     * 3600 = 1 heure. Au-delà, l'utilisateur doit se reconnecter
     * (ou utiliser un refresh token, non implémenté dans cette V2).
     */
    public static function expirySeconds(): int
    {
        return (int) (getenv('JWT_EXPIRY') ?: 3600);
    }

    /**
     * Algorithme de signature. HS256 = HMAC-SHA256, le standard
     * pour les API REST simples (clé symétrique partagée, pas de
     * paire de clés publique/privée nécessaire).
     */
    public static function algorithm(): string
    {
        return 'HS256';
    }
}