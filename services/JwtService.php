<?php
// ============================================================
//  services/JwtService.php
//  Génération et vérification de tokens JWT — implémentation
//  manuelle (HS256), sans dépendance Composer externe.
// ============================================================

require_once __DIR__ . '/../config/jwt.php';

/**
 * Classe JwtService
 *
 * Pourquoi écrire le JWT à la main plutôt qu'utiliser une librairie ?
 * La librairie standard (firebase/php-jwt) est la référence en
 * production, mais elle nécessite Composer — un gestionnaire de
 * paquets PHP qui n'est pas encore configuré dans ce projet
 * (pas de composer.json, pas de vendor/). Plutôt que d'ajouter cette
 * dépendance silencieusement, je préfère une implémentation manuelle
 * minimale et entièrement lisible : c'est aussi excellent
 * pédagogiquement pour une soutenance, où "j'ai compris comment
 * fonctionne un JWT" vaut largement plus qu'"j'ai importé une lib".
 *
 * Structure d'un JWT : trois parties séparées par des points
 *   header.payload.signature
 * Chaque partie est encodée en Base64URL (variante de Base64 sans
 * caractères + / = qui posent problème dans une URL).
 */
class JwtService
{
    /**
     * Génère un token JWT signé pour un utilisateur donné.
     *
     * @param array $claims Données à encoder dans le token
     *                       (ex: ['sub' => $userId, 'role' => 'admin'])
     * @return string Le token complet : header.payload.signature
     */
    public static function generate(array $claims): string
    {
        // ---- 1. HEADER ----
        // Décrit l'algorithme utilisé pour signer le token.
        $header = [
            'typ' => 'JWT',
            'alg' => JwtConfig::algorithm(),
        ];

        // ---- 2. PAYLOAD ----
        // Les données utiles + métadonnées temporelles standard JWT :
        // iat (issued at)  = horodatage de création
        // exp (expiration) = horodatage après lequel le token est invalide
        $now     = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + JwtConfig::expirySeconds(),
        ]);

        $headerEncoded  = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        // ---- 3. SIGNATURE ----
        // HMAC-SHA256 de "header.payload", signé avec la clé secrète.
        // Cette signature garantit que le token n'a pas été modifié :
        // si quelqu'un change le payload (ex: 'role' => 'admin') sans
        // connaître la clé secrète, la signature recalculée côté
        // serveur ne correspondra plus → token rejeté.
        $signature = hash_hmac(
            'sha256',
            "{$headerEncoded}.{$payloadEncoded}",
            JwtConfig::secret(),
            true // sortie binaire brute, pas hexadécimale
        );
        $signatureEncoded = self::base64UrlEncode($signature);

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    /**
     * Vérifie un token JWT et retourne son payload si valide.
     *
     * @param string $token Le token complet à vérifier
     * @return array|null Le payload décodé, ou null si invalide/expiré
     */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null; // structure invalide : pas 3 parties
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Recalcule la signature attendue à partir du header+payload reçus
        $expectedSignature = hash_hmac(
            'sha256',
            "{$headerEncoded}.{$payloadEncoded}",
            JwtConfig::secret(),
            true
        );
        $expectedSignatureEncoded = self::base64UrlEncode($expectedSignature);

        // hash_equals() plutôt que === : comparaison à temps constant,
        // qui empêche une attaque par mesure de timing (timing attack)
        // où un attaquant déduirait la signature correcte caractère
        // par caractère en mesurant le temps de réponse du serveur.
        if (!hash_equals($expectedSignatureEncoded, $signatureEncoded)) {
            return null; // signature invalide : token falsifié ou clé différente
        }

        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        if (!is_array($payload)) {
            return null;
        }

        // Vérifie l'expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return null; // token expiré
        }

        return $payload;
    }

    /**
     * Encode une chaîne en Base64URL (RFC 4648 §5).
     * Différence avec base64_encode() standard :
     *   + devient -, / devient _, le padding = est supprimé.
     * Nécessaire car + / = ont une signification spéciale dans les
     * URLs et les headers HTTP.
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Décode une chaîne Base64URL vers sa forme Base64 standard,
     * en réintroduisant le padding nécessaire.
     */
    private static function base64UrlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}