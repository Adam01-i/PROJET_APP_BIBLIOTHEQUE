<?php
// ============================================================
//  PHASE 5 : models/Book.php
//  Modèle : toute la logique d'accès aux données (table books)
// ============================================================

// On inclut la configuration de la base de données
require_once __DIR__ . '/../config/database.php';

/**
 * Classe Book — Modèle de la ressource "livre"
 *
 * Contient 5 méthodes correspondant aux opérations CRUD :
 *   - create()   → INSERT
 *   - read()     → SELECT (tous)
 *   - readOne()  → SELECT (un seul)
 *   - update()   → UPDATE
 *   - delete()   → DELETE
 *
 * Chaque méthode utilise des requêtes préparées PDO
 * pour se protéger contre les injections SQL.
 */
class Book
{
    private PDO $db;            // Connexion PDO
    private string $table = 'books'; // Nom de la table

    /**
     * Constructeur : récupère la connexion PDO depuis Database
     */
    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    // --------------------------------------------------------
    //  CREATE — Insérer un nouveau livre
    // --------------------------------------------------------
    /**
     * @param array $data Données du livre (title, author, genre, year, available)
     * @return array Résultat avec succès/échec et id inséré
     */
    public function create(array $data): array
    {
        // Requête préparée : les :placeholders seront remplacés par bindParam
        $sql = "INSERT INTO {$this->table} (title, author, genre, year, available)
                VALUES (:title, :author, :genre, :year, :available)";

        try {
            $stmt = $this->db->prepare($sql);

            // bindParam lie la variable PHP à un placeholder de la requête
            // PDO::PARAM_STR = type chaîne, PDO::PARAM_INT = type entier
            $stmt->bindParam(':title',     $data['title'],     PDO::PARAM_STR);
            $stmt->bindParam(':author',    $data['author'],    PDO::PARAM_STR);
            $stmt->bindParam(':genre',     $data['genre'],     PDO::PARAM_STR);
            $stmt->bindParam(':year',      $data['year'],      PDO::PARAM_INT);
            $stmt->bindParam(':available', $data['available'], PDO::PARAM_INT);

            $stmt->execute();

            return [
                'success' => true,
                'id'      => (int) $this->db->lastInsertId(), // ID auto-généré
                'message' => 'Livre créé avec succès.'
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // --------------------------------------------------------
    //  READ — Lire tous les livres (avec filtres optionnels)
    // --------------------------------------------------------
    /**
     * @param array $filters Filtres optionnels : genre, available, search
     * @return array Liste des livres
     */
    public function read(array $filters = []): array
    {
        $sql    = "SELECT * FROM {$this->table}";
        $params = [];
        $where  = [];

        // Filtre par genre
        if (!empty($filters['genre'])) {
            $where[]          = "genre = :genre";
            $params[':genre'] = $filters['genre'];
        }

        // Filtre par disponibilité
        if (isset($filters['available']) && $filters['available'] !== '') {
            $where[]              = "available = :available";
            $params[':available'] = (int) $filters['available'];
        }

        // Recherche textuelle sur titre ou auteur
        if (!empty($filters['search'])) {
            $where[]           = "(title LIKE :search OR author LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // Construction dynamique de la clause WHERE
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY created_at DESC"; // Tri par date décroissante

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $books = $stmt->fetchAll(); // Tableau de tableaux associatifs

            return [
                'success' => true,
                'count'   => count($books),
                'data'    => $books
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // --------------------------------------------------------
    //  READ ONE — Lire un livre par son ID
    // --------------------------------------------------------
    /**
     * @param int $id Identifiant du livre
     * @return array Le livre ou un message d'erreur
     */
    public function readOne(int $id): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // fetch() retourne une seule ligne (ou false si rien trouvé)
            $book = $stmt->fetch();

            if (!$book) {
                return ['success' => false, 'message' => "Livre avec l'ID {$id} introuvable."];
            }

            return ['success' => true, 'data' => $book];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // --------------------------------------------------------
    //  UPDATE — Mettre à jour un livre existant
    // --------------------------------------------------------
    /**
     * @param int   $id   Identifiant du livre à modifier
     * @param array $data Nouvelles valeurs
     * @return array Résultat de l'opération
     */
    public function update(int $id, array $data): array
    {
        // Vérifier que le livre existe avant de modifier
        $check = $this->readOne($id);
        if (!$check['success']) {
            return $check; // Retourne l'erreur "introuvable"
        }

        $sql = "UPDATE {$this->table}
                SET title = :title, author = :author, genre = :genre,
                    year = :year, available = :available
                WHERE id = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':title',     $data['title'],     PDO::PARAM_STR);
            $stmt->bindParam(':author',    $data['author'],    PDO::PARAM_STR);
            $stmt->bindParam(':genre',     $data['genre'],     PDO::PARAM_STR);
            $stmt->bindParam(':year',      $data['year'],      PDO::PARAM_INT);
            $stmt->bindParam(':available', $data['available'], PDO::PARAM_INT);
            $stmt->bindParam(':id',        $id,                PDO::PARAM_INT);
            $stmt->execute();

            // rowCount() = nombre de lignes affectées par la requête
            $affected = $stmt->rowCount();

            return [
                'success'       => true,
                'message'       => 'Livre mis à jour avec succès.',
                'rows_affected' => $affected
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // --------------------------------------------------------
    //  DELETE — Supprimer un livre
    // --------------------------------------------------------
    /**
     * @param int $id Identifiant du livre à supprimer
     * @return array Résultat de l'opération
     */
    public function delete(int $id): array
    {
        // Vérifier que le livre existe avant de supprimer
        $check = $this->readOne($id);
        if (!$check['success']) {
            return $check;
        }

        $sql = "DELETE FROM {$this->table} WHERE id = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'success' => true,
                'message' => "Livre ID {$id} supprimé avec succès."
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}