<?php
// ============================================================
//  repositories/LoanRepository.php  (V3 — correctif sécurité retour)
// ============================================================

require_once __DIR__ . '/AbstractRepository.php';
require_once __DIR__ . '/RepositoryInterface.php';

class LoanRepository extends AbstractRepository implements RepositoryInterface
{
    private string $table = 'loans';

    public function find(int $id): ?array
    {
        $sql = "SELECT l.*, b.title AS book_title, u.full_name AS borrower_name
                FROM {$this->table} l
                JOIN books b ON b.id = l.book_id
                JOIN users u ON u.id = l.user_id
                WHERE l.id = :id LIMIT 1";

        $stmt = $this->query($sql, [':id' => $id]);
        $loan = $stmt->fetch();
        return $loan ?: null;
    }

    public function findAll(array $filters = []): array
    {
        $sql = "SELECT l.*, b.title AS book_title, u.full_name AS borrower_name
                FROM {$this->table} l
                JOIN books b ON b.id = l.book_id
                JOIN users u ON u.id = l.user_id";
        $where  = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]            = "l.user_id = :user_id";
            $params[':user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['book_id'])) {
            $where[]            = "l.book_id = :book_id";
            $params[':book_id'] = (int) $filters['book_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = $filters['status'] === 'active'
                ? "l.returned_at IS NULL"
                : "l.returned_at IS NOT NULL";
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY l.borrowed_at DESC";

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (book_id, user_id, due_at)
                VALUES (:book_id, :user_id, :due_at)";

        $this->query($sql, [
            ':book_id' => $data['book_id'],
            ':user_id' => $data['user_id'],
            ':due_at'  => $data['due_at'],
        ]);

        return $this->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql  = "UPDATE {$this->table} SET due_at = :due_at WHERE id = :id";
        $stmt = $this->query($sql, [':due_at' => $data['due_at'], ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $sql  = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->query($sql, [':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function markAsReturned(int $loanId): bool
    {
        $sql  = "UPDATE {$this->table} SET returned_at = NOW() WHERE id = :id AND returned_at IS NULL";
        $stmt = $this->query($sql, [':id' => $loanId]);
        return $stmt->rowCount() > 0;
    }

    public function hasActiveLoan(int $bookId): bool
    {
        $sql  = "SELECT COUNT(*) AS count FROM {$this->table}
                  WHERE book_id = :book_id AND returned_at IS NULL";
        $stmt = $this->query($sql, [':book_id' => $bookId]);
        return (int) $stmt->fetch()['count'] > 0;
    }

    /**
     * Trouve l'emprunt actif d'un livre donné, peu importe qui l'a
     * emprunté. Utilisée en interne par LoanService UNIQUEMENT pour
     * vérifier l'existence d'un emprunt — JAMAIS pour autoriser un
     * retour sans vérifier le propriétaire (voir findActiveLoanForBookAndUser
     * ci-dessous, qui est la méthode à utiliser pour ça).
     */
    public function findActiveLoanForBook(int $bookId): ?array
    {
        $sql  = "SELECT * FROM {$this->table}
                  WHERE book_id = :book_id AND returned_at IS NULL
                  LIMIT 1";
        $stmt = $this->query($sql, [':book_id' => $bookId]);
        $loan = $stmt->fetch();
        return $loan ?: null;
    }

    /**
     * NOUVEAU (correctif V3) — Trouve l'emprunt actif d'un livre,
     * UNIQUEMENT s'il appartient à l'utilisateur donné.
     *
     * Pourquoi cette méthode est indispensable :
     * findActiveLoanForBook() seule ne suffit pas pour autoriser un
     * retour, car elle ne vérifie pas QUI a emprunté. Sans ce filtre
     * supplémentaire, n'importe quel membre authentifié pourrait
     * appeler POST /api/loans/return avec le book_id de n'importe
     * quel autre livre emprunté par quelqu'un d'autre, et le faire
     * "rendre" à sa place — un membre malveillant pourrait ainsi
     * perturber le suivi des emprunts d'autrui.
     */
    public function findActiveLoanForBookAndUser(int $bookId, int $userId): ?array
    {
        $sql  = "SELECT * FROM {$this->table}
                  WHERE book_id = :book_id AND user_id = :user_id AND returned_at IS NULL
                  LIMIT 1";
        $stmt = $this->query($sql, [':book_id' => $bookId, ':user_id' => $userId]);
        $loan = $stmt->fetch();
        return $loan ?: null;
    }

    public function getStats(): array
    {
        $sql = "SELECT
                    COUNT(*) AS total_loans,
                    SUM(CASE WHEN returned_at IS NULL THEN 1 ELSE 0 END) AS active_loans,
                    SUM(CASE WHEN returned_at IS NULL AND due_at < CURDATE() THEN 1 ELSE 0 END) AS overdue_loans
                FROM {$this->table}";

        $stmt = $this->query($sql);
        $row  = $stmt->fetch();

        return [
            'total_loans'   => (int) $row['total_loans'],
            'active_loans'  => (int) $row['active_loans'],
            'overdue_loans' => (int) $row['overdue_loans'],
        ];
    }
}