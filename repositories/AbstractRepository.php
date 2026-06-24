<?php
// ============================================================
//  repositories/AbstractRepository.php
//  Base commune : connexion PDO partagée par tous les repositories
// ============================================================

require_once __DIR__ . '/../config/database.php';

/**
 * Classe abstraite AbstractRepository
 *
 * "abstract" en PHP signifie qu'on ne peut JAMAIS faire
 * `new AbstractRepository()` directement — elle n'existe que
 * pour être héritée (extends). C'est intentionnel : cette classe
 * seule n'a pas de sens, elle n'a pas de table associée.
 *
 * Elle évite que chaque repository (BookRepository, UserRepository...)
 * réécrive le même code de connexion PDO. Principe DRY
 * (Don't Repeat Yourself).
 */
abstract class AbstractRepository
{
    protected PDO $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    /**
     * Exécute une requête préparée et retourne le PDOStatement.
     * Centralise la gestion des erreurs PDO pour tous les repositories.
     *
     * @param string $sql    Requête SQL avec placeholders nommés
     * @param array  $params Paramètres à lier (ex: [':id' => 5])
     * @return PDOStatement
     * @throws PDOException si la requête échoue
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retourne l'ID du dernier enregistrement inséré.
     * Utilisé par toutes les méthodes create() des repositories enfants.
     */
    protected function lastInsertId(): int
    {
        return (int) $this->db->lastInsertId();
    }
}