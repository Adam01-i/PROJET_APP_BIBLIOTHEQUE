<?php
// ============================================================
//  repositories/BookRepository.php
//  Accès aux données de la table "books" — SQL pur, rien d'autre
// ============================================================

require_once __DIR__ . '/AbstractRepository.php';
require_once __DIR__ . '/RepositoryInterface.php';

/**
 * Classe BookRepository
 *
 * Différence avec l'ancien models/Book.php :
 * - Plus de validation ici (c'était dans Book::create() avant,
 *   indirectement via le contrôleur). La validation appartient
 *   maintenant à BookService.
 * - Le champ "genre" (VARCHAR libre) devient une jointure vers
 *   "categories" : on récupère category_name via LEFT JOIN.
 * - readOne() est renommé find() pour respecter RepositoryInterface
 *   (cohérence de vocabulaire entre tous les repositories).
 */
class BookRepository extends AbstractRepository implements RepositoryInterface
{
    private string $table = 'books';

    /**
     * Trouve un livre par son ID, avec le nom de sa catégorie.
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT b.*, c.name AS category_name
                FROM {$this->table} b
                LEFT JOIN categories c ON c.id = b.category_id
                WHERE b.id = :id
                LIMIT 1";

        $stmt = $this->query($sql, [':id' => $id]);
        $book = $stmt->fetch();

        return $book ?: null;
    }

    /**
     * Liste tous les livres, avec filtres optionnels.
     *
     * @param array $filters Clés possibles : category_id, available, search
     */
    public function findAll(array $filters = []): array
    {
        $sql    = "SELECT b.*, c.name AS category_name
                    FROM {$this->table} b
                    LEFT JOIN categories c ON c.id = b.category_id";
        $where  = [];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[]                = "b.category_id = :category_id";
            $params[':category_id'] = (int) $filters['category_id'];
        }

        if (isset($filters['available']) && $filters['available'] !== '') {
            $where[]              = "b.available = :available";
            $params[':available'] = (int) $filters['available'];
        }

        if (!empty($filters['search'])) {
            $where[]           = "(b.title LIKE :search OR b.author LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY b.created_at DESC";

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insère un nouveau livre.
     * @return int ID du livre créé
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (title, author, category_id, year, available)
                VALUES (:title, :author, :category_id, :year, :available)";

        $this->query($sql, [
            ':title'       => $data['title'],
            ':author'      => $data['author'],
            ':category_id' => $data['category_id'] ?? null,
            ':year'        => $data['year'],
            ':available'   => $data['available'] ?? 1,
        ]);

        return $this->lastInsertId();
    }

    /**
     * Met à jour un livre existant.
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                SET title = :title, author = :author, category_id = :category_id,
                    year = :year, available = :available
                WHERE id = :id";

        $stmt = $this->query($sql, [
            ':title'       => $data['title'],
            ':author'      => $data['author'],
            ':category_id' => $data['category_id'] ?? null,
            ':year'        => $data['year'],
            ':available'   => $data['available'] ?? 1,
            ':id'          => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime un livre.
     * Les emprunts liés (loans) sont supprimés en cascade par la BDD
     * (ON DELETE CASCADE défini dans le schéma SQL).
     */
    public function delete(int $id): bool
    {
        $sql  = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->query($sql, [':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Met à jour uniquement le champ "available" d'un livre.
     * Méthode dédiée car appelée fréquemment par LoanService
     * (emprunter = available 1→0, retourner = available 0→1)
     * sans avoir besoin de repasser title/author/etc.
     */
    public function setAvailability(int $id, bool $available): bool
    {
        $sql  = "UPDATE {$this->table} SET available = :available WHERE id = :id";
        $stmt = $this->query($sql, [
            ':available' => $available ? 1 : 0,
            ':id'        => $id,
        ]);
        return $stmt->rowCount() > 0;
    }
}