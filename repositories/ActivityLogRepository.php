<?php
// ============================================================
//  repositories/ActivityLogRepository.php
//  Accès aux données de la table "activity_logs"
// ============================================================

require_once __DIR__ . '/AbstractRepository.php';

/**
 * N'implémente PAS RepositoryInterface : les logs ne se modifient
 * jamais (pas d'update()) et ne se suppriment jamais individuellement
 * (pas de delete() — un log est une trace permanente). L'interface
 * complète n'aurait pas de sens ici, donc on s'en écarte volontairement
 * plutôt que d'implémenter des méthodes vides qui mentiraient sur
 * leur propre comportement.
 */
class ActivityLogRepository extends AbstractRepository
{
    private string $table = 'activity_logs';

    /**
     * Enregistre un événement.
     * @param int|null $userId    null si l'utilisateur n'est pas identifié
     * @param string   $action    ex: 'login', 'loan_created', 'book_deleted'
     * @param string   $details   description lisible de l'événement
     * @param string|null $ip     adresse IP de la requête
     */
    public function log(?int $userId, string $action, string $details, ?string $ip = null): int
    {
        $sql = "INSERT INTO {$this->table} (user_id, action, details, ip_address)
                VALUES (:user_id, :action, :details, :ip_address)";

        $this->query($sql, [
            ':user_id'    => $userId,
            ':action'     => $action,
            ':details'    => $details,
            ':ip_address' => $ip,
        ]);

        return $this->lastInsertId();
    }

    /**
     * Liste les logs les plus récents, avec filtres optionnels.
     */
    public function findRecent(int $limit = 50, array $filters = []): array
    {
        $sql    = "SELECT l.*, u.full_name AS user_name
                    FROM {$this->table} l
                    LEFT JOIN users u ON u.id = l.user_id";
        $where  = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[]           = "l.action = :action";
            $params[':action'] = $filters['action'];
        }

        if (!empty($filters['user_id'])) {
            $where[]            = "l.user_id = :user_id";
            $params[':user_id'] = (int) $filters['user_id'];
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT :limit";

        // LIMIT avec un paramètre nommé nécessite bindValue() en INT explicite —
        // PDO traite sinon le placeholder comme une chaîne, ce que MySQL refuse
        // dans une clause LIMIT.
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}