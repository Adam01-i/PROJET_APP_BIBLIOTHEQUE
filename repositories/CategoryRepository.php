<?php
// ============================================================
//  repositories/CategoryRepository.php
//  Accès aux données de la table "categories"
// ============================================================

require_once __DIR__ . '/AbstractRepository.php';
require_once __DIR__ . '/RepositoryInterface.php';

class CategoryRepository extends AbstractRepository implements RepositoryInterface
{
    private string $table = 'categories';

    public function find(int $id): ?array
    {
        $sql  = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->query($sql, [':id' => $id]);
        $cat  = $stmt->fetch();
        return $cat ?: null;
    }

    /**
     * Liste les catégories avec le nombre de livres associés
     * (utile pour le dashboard et pour éviter de supprimer une
     * catégorie encore utilisée sans le savoir).
     */
    public function findAll(array $filters = []): array
    {
        $sql = "SELECT c.*, COUNT(b.id) AS books_count
                FROM {$this->table} c
                LEFT JOIN books b ON b.category_id = c.id
                GROUP BY c.id
                ORDER BY c.name ASC";

        $stmt = $this->query($sql);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (name, description) VALUES (:name, :description)";

        $this->query($sql, [
            ':name'        => $data['name'],
            ':description' => $data['description'] ?? null,
        ]);

        return $this->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET name = :name, description = :description WHERE id = :id";

        $stmt = $this->query($sql, [
            ':name'        => $data['name'],
            ':description' => $data['description'] ?? null,
            ':id'          => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime une catégorie.
     * Les livres associés ne sont PAS supprimés : leur category_id
     * passe à NULL automatiquement (ON DELETE SET NULL défini en BDD).
     */
    public function delete(int $id): bool
    {
        $sql  = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->query($sql, [':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function nameExists(string $name): bool
    {
        $sql  = "SELECT COUNT(*) AS count FROM {$this->table} WHERE name = :name";
        $stmt = $this->query($sql, [':name' => $name]);
        return (int) $stmt->fetch()['count'] > 0;
    }
}