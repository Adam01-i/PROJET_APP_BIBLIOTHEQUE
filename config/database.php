<?php
// ============================================================
//  config/database.php
//  Connexion à MySQL via PDO
//  Corrigé pour fonctionner en Docker ET en local sans modification
// ============================================================

/**
 * Classe Database
 * Responsabilité unique : fournir une connexion PDO à MySQL.
 * Utilise le pattern Singleton pour éviter de multiples connexions.
 *
 * IMPORTANT — Pourquoi des variables d'environnement ?
 * En dur, le host était "localhost", ce qui ne fonctionne QUE si
 * PHP et MySQL tournent sur la même machine physique/conteneur.
 * En Docker, chaque service (mysql, api, frontend) tourne dans son
 * propre conteneur isolé : "localhost" depuis le conteneur "api"
 * désigne le conteneur "api" lui-même, pas le conteneur MySQL.
 * Docker Compose résout les noms de service via son DNS interne :
 * il faut donc utiliser "mysql" (le nom du service) comme host.
 *
 * getenv() avec une valeur par défaut permet au même code de
 * fonctionner que tu sois en Docker (variables injectées par
 * docker-compose.yml) ou en local sans Docker (valeurs par défaut
 * ci-dessous, à adapter à ton environnement local si besoin).
 */
class Database
{
    // --- Paramètres de connexion ---
    // getenv('X') ?: 'fallback' : utilise la variable d'env si elle existe,
    // sinon utilise la valeur par défaut (utile hors Docker)
    private string $host    = '';
    private string $dbName  = '';
    private string $user    = '';
    private string $pass    = '';
    private string $charset = 'utf8mb4';

    // Instance PDO (null tant que connect() n'a pas été appelé)
    private ?PDO $conn = null;

    public function __construct()
    {
        // Lecture des variables d'environnement injectées par Docker Compose.
        // En local sans Docker, ces variables n'existent pas → on retombe
        // sur les valeurs par défaut après le ?:
        $this->host   = getenv('DB_HOST')     ?: 'localhost';
        $this->dbName = getenv('DB_NAME')     ?: 'shop_db';
        $this->user   = getenv('DB_USER')     ?: 'adam';
        $this->pass   = getenv('DB_PASSWORD') ?: '123';
        $this->charset= getenv('DB_CHARSET')  ?: 'utf8mb4';
    }

    /**
     * Retourne la connexion PDO active.
     * Si elle n'existe pas encore, elle est créée.
     *
     * @return PDO
     */
    public function connect(): PDO
    {
        // Si déjà connecté, on réutilise la même instance (Singleton)
        if ($this->conn !== null) {
            return $this->conn;
        }

        // DSN = Data Source Name : chaîne qui identifie la base
        // Format : driver:host=...;dbname=...;charset=...
        $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset={$this->charset}";

        try {
            // Instanciation de PDO avec les options recommandées
            $this->conn = new PDO($dsn, $this->user, $this->pass, [
                // Lance une exception à chaque erreur SQL → plus sûr que les codes d'erreur
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Retourne les résultats sous forme de tableau associatif par défaut
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Désactive l'émulation des requêtes préparées → sécurité renforcée
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // En production, ne jamais afficher le message brut (sécurité) :
            // il révèle host/dbname/structure interne à un attaquant potentiel.
            $isProd = (getenv('APP_ENV') === 'production');

            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => $isProd
                    ? 'Erreur de connexion à la base de données.'
                    : 'Erreur de connexion à la base de données : ' . $e->getMessage()
            ]);
            exit; // Arrêt immédiat du script
        }

        return $this->conn;
    }
}