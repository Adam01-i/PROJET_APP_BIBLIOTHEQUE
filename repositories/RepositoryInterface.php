<?php
// ============================================================
//  repositories/RepositoryInterface.php
//  Contrat commun à tous les repositories
// ============================================================

/**
 * Interface RepositoryInterface
 *
 * Pourquoi une interface ?
 * Elle force chaque repository (BookRepository, UserRepository...)
 * à implémenter les mêmes méthodes de base. Avantages concrets :
 *
 * 1. Prévisibilité : n'importe quel développeur qui découvre le
 *    projet sait que TOUT repository a find(), findAll(), etc.
 * 2. Testabilité : on peut créer un FakeRepository qui implémente
 *    la même interface pour tester les services sans vraie BDD.
 * 3. Substituabilité : un service qui dépend de l'interface (pas
 *    d'une classe concrète) peut recevoir n'importe quelle
 *    implémentation sans être modifié.
 *
 * Différence Repository vs Model (ancien Book.php) :
 * - Le Repository fait UNIQUEMENT de l'accès aux données (SQL).
 * - Il ne valide rien, ne décide de rien, ne connaît pas HTTP.
 * - Toute la logique métier (règles, validation complexe,
 *   orchestration de plusieurs repositories) part dans un Service.
 */
interface RepositoryInterface
{
    /**
     * Récupère un enregistrement par son ID.
     * @return array|null  Tableau associatif, ou null si introuvable
     */
    public function find(int $id): ?array;

    /**
     * Récupère tous les enregistrements, avec filtres optionnels.
     * @param array $filters Filtres spécifiques à chaque repository
     * @return array Liste de tableaux associatifs
     */
    public function findAll(array $filters = []): array;

    /**
     * Crée un nouvel enregistrement.
     * @param array $data Données à insérer
     * @return int ID de l'enregistrement créé
     */
    public function create(array $data): int;

    /**
     * Met à jour un enregistrement existant.
     * @param int   $id   Identifiant de l'enregistrement
     * @param array $data Nouvelles valeurs
     * @return bool true si au moins une ligne a été modifiée
     */
    public function update(int $id, array $data): bool;

    /**
     * Supprime un enregistrement.
     * @param int $id Identifiant de l'enregistrement
     * @return bool true si la suppression a réussi
     */
    public function delete(int $id): bool;
}