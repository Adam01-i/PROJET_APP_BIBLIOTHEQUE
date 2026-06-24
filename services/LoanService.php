<?php
// ============================================================
//  services/LoanService.php
//  Logique métier des emprunts : emprunter, retourner
// ============================================================

require_once __DIR__ . '/../repositories/LoanRepository.php';
require_once __DIR__ . '/../repositories/BookRepository.php';
require_once __DIR__ . '/../repositories/ActivityLogRepository.php';

/**
 * Classe LoanService
 *
 * C'est ici qu'on voit le bénéfice concret de séparer Repository
 * et Service. "Emprunter un livre" implique TROIS opérations qui
 * doivent réussir ou échouer ENSEMBLE :
 *   1. Vérifier que le livre est disponible (lecture)
 *   2. Créer la ligne dans "loans" (écriture)
 *   3. Passer "available" à 0 dans "books" (écriture)
 * + 4. Logger l'action
 *
 * Aucun repository ne devrait connaître les 3 autres repositories
 * — un BookRepository qui modifierait directement "loans" violerait
 * la responsabilité unique. C'est le rôle du SERVICE d'orchestrer
 * plusieurs repositories pour appliquer une règle métier complète.
 */
class LoanService
{
    private LoanRepository $loans;
    private BookRepository $books;
    private ActivityLogRepository $logs;

    /** Durée d'emprunt par défaut, en jours. */
    private const DEFAULT_LOAN_DAYS = 14;

    public function __construct()
    {
        $this->loans = new LoanRepository();
        $this->books = new BookRepository();
        $this->logs  = new ActivityLogRepository();
    }

    /**
     * Emprunte un livre pour un utilisateur.
     *
     * @param int $bookId
     * @param int $userId
     * @return array ['success' => bool, 'message' => string, 'loan_id' => ?int]
     */
    public function borrowBook(int $bookId, int $userId): array
    {
        $book = $this->books->find($bookId);

        if ($book === null) {
            return ['success' => false, 'message' => 'Livre introuvable.'];
        }

        // Double vérification de cohérence : le flag "available" ET
        // l'absence d'emprunt actif doivent être alignés. S'ils ne le
        // sont pas (ex: incident antérieur), on se fie à l'emprunt
        // actif réel plutôt qu'au flag, qui pourrait être désynchronisé.
        if (!$book['available'] || $this->loans->hasActiveLoan($bookId)) {
            return ['success' => false, 'message' => 'Ce livre est déjà emprunté.'];
        }

        $dueAt = date('Y-m-d', strtotime('+' . self::DEFAULT_LOAN_DAYS . ' days'));

        $loanId = $this->loans->create([
            'book_id' => $bookId,
            'user_id' => $userId,
            'due_at'  => $dueAt,
        ]);

        // Si cette étape échouait après la création du prêt, on aurait
        // un livre marqué disponible avec un emprunt actif simultané —
        // incohérence à surveiller. Une vraie transaction SQL
        // (BEGIN/COMMIT/ROLLBACK) éliminerait ce risque ; limitation
        // assumée de cette V2, à corriger si le projet évolue encore.
        $this->books->setAvailability($bookId, false);

        $this->logs->log(
            $userId,
            'loan_created',
            "Emprunt du livre #{$bookId} ({$book['title']}), retour prévu le {$dueAt}"
        );

        return [
            'success' => true,
            'message' => "Livre emprunté avec succès. Retour prévu le {$dueAt}.",
            'loan_id' => $loanId,
            'due_at'  => $dueAt,
        ];
    }

    /**
     * Retourne un livre emprunté.
     *
     * @param int $bookId
     * @param int $userId ID de l'utilisateur qui effectue le retour (pour le log)
     */
    public function returnBook(int $bookId, int $userId): array
    {
        $loan = $this->loans->findActiveLoanForBook($bookId);

        if ($loan === null) {
            return ['success' => false, 'message' => 'Aucun emprunt actif trouvé pour ce livre.'];
        }

        $this->loans->markAsReturned($loan['id']);
        $this->books->setAvailability($bookId, true);

        $this->logs->log(
            $userId,
            'loan_returned',
            "Retour du livre #{$bookId} (emprunt #{$loan['id']})"
        );

        return ['success' => true, 'message' => 'Livre retourné avec succès.'];
    }

    /**
     * Liste les emprunts avec filtres (délégation directe au repository
     * — pas de règle métier supplémentaire nécessaire pour une simple lecture).
     */
    public function listLoans(array $filters = []): array
    {
        return $this->loans->findAll($filters);
    }

    /**
     * Statistiques pour le dashboard.
     */
    public function getStats(): array
    {
        return $this->loans->getStats();
    }
}