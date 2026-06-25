<?php
// ============================================================
//  repositories/UserRepository.php  (V4 — ajout countByRole)
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

    public function emailExists(string $email): bool
    {
        $sql  = "SELECT COUNT(*) AS count FROM {$this->table} WHERE email = :email";
        $stmt = $this->query($sql, [':email' => $email]);
        return (int) $stmt->fetch()['count'] > 0;
    }

    /**
     * NOUVEAU (V4) — Compte les utilisateurs par rôle, pour le
     * dashboard admin ("X membres inscrits").
     */
    public function countByRole(string $role): int
    {
        $sql  = "SELECT COUNT(*) AS count FROM {$this->table} WHERE role = :role";
        $stmt = $this->query($sql, [':role' => $role]);
        return (int) $stmt->fetch()['count'];
    }
}