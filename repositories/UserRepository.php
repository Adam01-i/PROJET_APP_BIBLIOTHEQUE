<?php
// ============================================================
//  repositories/UserRepository.php
//  Accès aux données de la table "users"
// ============================================================

require_once __DIR__ . '/AbstractRepository.php';
require_once __DIR__ . '/RepositoryInterface.php';

class UserRepository extends AbstractRepository implements RepositoryInterface
{
    private string $table = 'users';

    public function find(int $id): ?array
    {
        $sql  = "SELECT id, full_name, email, role, is_active, created_at
                  FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->query($sql, [':id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Trouve un utilisateur par email, AVEC son password_hash.
     *
     * Pourquoi une méthode séparée de find() ?
     * find() ne retourne JAMAIS password_hash (sécurité : on ne
     * veut pas qu'un hash de mot de passe se retrouve accidentellement
     * dans une réponse JSON ailleurs dans le code). Seul AuthService,
     * au moment précis de vérifier un mot de passe, a besoin du hash.
     * On isole ce besoin dans une méthode explicitement nommée.
     */
    public function findByEmailWithPassword(string $email): ?array
    {
        $sql  = "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1";
        $stmt = $this->query($sql, [':email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findAll(array $filters = []): array
    {
        $sql    = "SELECT id, full_name, email, role, is_active, created_at FROM {$this->table}";
        $where  = [];
        $params = [];

        if (!empty($filters['role'])) {
            $where[]         = "role = :role";
            $params[':role'] = $filters['role'];
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Crée un utilisateur. $data['password_hash'] doit déjà être
     * haché (jamais de mot de passe en clair ici) — c'est la
     * responsabilité d'AuthService de hacher avant d'appeler create().
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (full_name, email, password_hash, role)
                VALUES (:full_name, :email, :password_hash, :role)";

        $this->query($sql, [
            ':full_name'     => $data['full_name'],
            ':email'         => $data['email'],
            ':password_hash' => $data['password_hash'],
            ':role'          => $data['role'] ?? 'membre',
        ]);

        return $this->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                SET full_name = :full_name, email = :email, role = :role, is_active = :is_active
                WHERE id = :id";

        $stmt = $this->query($sql, [
            ':full_name' => $data['full_name'],
            ':email'     => $data['email'],
            ':role'      => $data['role'],
            ':is_active' => $data['is_active'] ?? 1,
            ':id'        => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $sql  = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->query($sql, [':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Vérifie si un email existe déjà (utile pour la validation
     * d'inscription, avant de violer la contrainte UNIQUE en BDD).
     */
    public function emailExists(string $email): bool
    {
        $sql  = "SELECT COUNT(*) AS count FROM {$this->table} WHERE email = :email";
        $stmt = $this->query($sql, [':email' => $email]);
        return (int) $stmt->fetch()['count'] > 0;
    }
}