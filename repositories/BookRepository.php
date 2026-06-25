<?php
// ============================================================
//  repositories/BookRepository.php  (V4 — ajout getStats + getTopCategories)
// ============================================================

require_once __DIR__ . '/AbstractRepository.php';
require_once __DIR__ . '/RepositoryInterface.php';

class BookRepository extends AbstractRepository implements RepositoryInterface
{
    private string $table = 'books';

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

    public function delete(int $id): bool
    {
        $sql  = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->query($sql, [':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function setAvailability(int $id, bool $available): bool
    {
        $sql  = "UPDATE {$this->table} SET available = :available WHERE id = :id";
        $stmt = $this->query($sql, [
            ':available' => $available ? 1 : 0,
            ':id'        => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * NOUVEAU (V4) — Statistiques globales du catalogue pour le dashboard admin.
     * Une seule requête SQL avec agrégation conditionnelle (CASE WHEN) plutôt
     * que 3 requêtes séparées (total, dispo, empruntés) — plus efficace.
     */
    public function getStats(): array
    {
        $sql = "SELECT
                    COUNT(*) AS total_books,
                    SUM(CASE WHEN available = 1 THEN 1 ELSE 0 END) AS available_books,
                    SUM(CASE WHEN available = 0 THEN 1 ELSE 0 END) AS borrowed_books
                FROM {$this->table}";

        $stmt = $this->query($sql);
        $row  = $stmt->fetch();

        return [
            'total_books'     => (int) $row['total_books'],
            'available_books' => (int) $row['available_books'],
            'borrowed_books'  => (int) $row['borrowed_books'],
        ];
    }

    /**
     * NOUVEAU (V4) — Les catégories les plus représentées dans le
     * catalogue, pour un graphique ou une liste sur le dashboard admin.
     *
     * @param int $limit Nombre de catégories à retourner (top N)
     */
    public function getTopCategories(int $limit = 3): array
    {
        $sql = "SELECT c.name, COUNT(b.id) AS books_count
                FROM categories c
                JOIN books b ON b.category_id = c.id
                GROUP BY c.id, c.name
                ORDER BY books_count DESC
                LIMIT :limit";

        // LIMIT avec paramètre nommé nécessite bindValue() en INT explicite
        // (voir ActivityLogRepository::findRecent pour la même nécessité).
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}