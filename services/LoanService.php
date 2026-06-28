<?php
// ============================================================
//  services/LoanService.php  (V3 — correctif sécurité retour)
// ============================================================

require_once __DIR__ . '/../repositories/LoanRepository.php';
require_once __DIR__ . '/../repositories/BookRepository.php';
require_once __DIR__ . '/../repositories/ActivityLogRepository.php';

class LoanService
{
    private LoanRepository $loans;
    private BookRepository $books;
    private ActivityLogRepository $logs;

    private const DEFAULT_LOAN_DAYS = 14;

    public function __construct()
    {
        $this->loans = new LoanRepository();
        $this->books = new BookRepository();
        $this->logs = new ActivityLogRepository();
    }

    public function borrowBook(int $bookId, int $userId, string $userRole = 'membre'): array
    {
        // Règle métier clé : un admin ne peut pas emprunter
        if ($userRole === 'admin') {
            return [
                'success' => false,
                'message' => "Un administrateur ne peut pas emprunter de livre."
            ];
        }

        $book = $this->books->find($bookId);

        if ($book === null) {
            return ['success' => false, 'message' => 'Livre introuvable.'];
        }

        if (!$book['available'] || $this->loans->hasActiveLoan($bookId)) {
            return ['success' => false, 'message' => 'Ce livre est déjà emprunté.'];
        }

        $dueAt = date('Y-m-d', strtotime('+' . self::DEFAULT_LOAN_DAYS . ' days'));

        $loanId = $this->loans->create([
            'book_id' => $bookId,
            'user_id' => $userId,
            'due_at' => $dueAt,
        ]);

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
            'due_at' => $dueAt,
        ];
    }

    /**
     * Retourne un livre emprunté.
     *
     * CORRECTIF V3 — Contrôle de propriété :
     * Un membre ne peut retourner QUE son propre emprunt actif.
     * Un admin peut retourner n'importe quel emprunt actif (cas
     * d'usage réel : un membre rend le livre physiquement à l'accueil,
     * et c'est l'admin/bibliothécaire qui valide le retour dans le
     * système à sa place).
     *
     * @param int    $bookId
     * @param int    $requesterId   ID de l'utilisateur qui FAIT la requête
     * @param string $requesterRole Rôle de cet utilisateur ('admin'|'membre')
     */
    public function returnBook(int $bookId, int $requesterId, string $requesterRole = 'membre'): array
    {
        if ($requesterRole === 'admin') {
            // Un admin peut traiter le retour de n'importe quel emprunt actif.
            $loan = $this->loans->findActiveLoanForBook($bookId);
        } else {
            // Un membre ne peut retourner QUE ce qu'il a lui-même emprunté.
            $loan = $this->loans->findActiveLoanForBookAndUser($bookId, $requesterId);
        }

        if ($loan === null) {
            // Message volontairement générique : on ne révèle pas si le
            // livre est emprunté par quelqu'un d'autre (ce qui aiderait
            // un membre à savoir qu'il existe un emprunt actif qui n'est
            // pas le sien, information qui ne lui appartient pas).
            return [
                'success' => false,
                'message' => 'Aucun emprunt actif trouvé pour ce livre à ton nom.'
            ];
        }

        $this->loans->markAsReturned($loan['id']);
        $this->books->setAvailability($bookId, true);

        $this->logs->log(
            $requesterId,
            'loan_returned',
            "Retour du livre #{$bookId} (emprunt #{$loan['id']})"
        );

        return ['success' => true, 'message' => 'Livre retourné avec succès.'];
    }

    public function listLoans(array $filters = []): array
    {
        return $this->loans->findAll($filters);
    }

    public function getStats(): array
    {
        return $this->loans->getStats();
    }
}