<?php
// ============================================================
//  PHASE 4 : config/database.php
//  Connexion à MySQL via PDO
// ============================================================

/**
 * Classe Database
 * Responsabilité unique : fournir une connexion PDO à MySQL.
 * Utilise le pattern Singleton pour éviter de multiples connexions.
 */
class Database
{
    // --- Paramètres de connexion ---
    // Modifie ces valeurs selon ton environnement local
    private string $host    = 'localhost';
    private string $dbName  = 'shop_db';
    private string $user    = 'adam';
    private string $pass    = '123';           // Ton mot de passe MySQL
    private string $charset = 'utf8mb4';

    // Instance PDO (null tant que connect() n'a pas été appelé)
    private ?PDO $conn = null;

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
            // En production, ne jamais afficher le message brut (sécurité)
            // Ici on le fait pour le développement
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()
            ]);
            exit; // Arrêt immédiat du script
        }

        return $this->conn;
    }
}