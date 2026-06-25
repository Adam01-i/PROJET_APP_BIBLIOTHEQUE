<?php
// ============================================================
//  services/DashboardService.php
//  Agrège les statistiques pour le dashboard, différemment
//  selon le rôle de l'utilisateur connecté.
// ============================================================

require_once __DIR__ . '/../repositories/BookRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/LoanRepository.php';

/**
 * Classe DashboardService
 *
 * Pourquoi un service dédié plutôt que d'éparpiller ces agrégations
 * dans BookService/LoanService ? Parce que "construire le dashboard"
 * est une responsabilité à part entière qui combine PLUSIEURS
 * domaines (livres + utilisateurs + emprunts) dans une seule vue —
 * exactement le type de cas où un service orchestrateur a du sens,
 * pour ne pas forcer BookService à connaître les emprunts ou les
 * utilisateurs alors que sa responsabilité est seulement les livres.
 */
class DashboardService
{
    private BookRepository $books;
    private UserRepository $users;
    private LoanRepository $loans;

    public function __construct()
    {
        $this->books = new BookRepository();
        $this->users = new UserRepository();
        $this->loans = new LoanRepository();
    }

    /**
     * Construit les statistiques adaptées au rôle de l'appelant.
     *
     * @param int    $userId ID de l'utilisateur connecté
     * @param string $role   'admin' ou 'membre'
     */
    public function getStatsFor(int $userId, string $role): array
    {
        return $role === 'admin'
            ? $this->getAdminStats()
            : $this->getMemberStats($userId);
    }

    /**
     * Vision globale : tout le catalogue, tous les emprunts,
     * tous les utilisateurs. Réservée à un admin.
     */
    private function getAdminStats(): array
    {
        $bookStats = $this->books->getStats();
        $loanStats = $this->loans->getStats();

        return [
            'scope' => 'admin',
            'books' => [
                'total'     => $bookStats['total_books'],
                'available' => $bookStats['available_books'],
                'borrowed'  => $bookStats['borrowed_books'],
            ],
            'loans' => [
                'total'   => $loanStats['total_loans'],
                'active'  => $loanStats['active_loans'],
                'overdue' => $loanStats['overdue_loans'],
            ],
            'members_count'  => $this->users->countByRole('membre'),
            'top_categories' => $this->books->getTopCategories(3),
        ];
    }

    /**
     * Vision personnelle : uniquement les emprunts DE cet utilisateur.
     * Un membre ne voit jamais les statistiques des autres.
     */
    private function getMemberStats(int $userId): array
    {
        $myLoans       = $this->loans->findAll(['user_id' => $userId]);
        $myActiveLoans = array_filter($myLoans, fn($l) => $l['returned_at'] === null);

        $today = date('Y-m-d');
        $myOverdueLoans = array_filter(
            $myActiveLoans,
            fn($l) => $l['due_at'] < $today
        );

        return [
            'scope' => 'membre',
            'my_loans' => [
                'total_ever' => count($myLoans),
                'active'     => count($myActiveLoans),
                'overdue'    => count($myOverdueLoans),
            ],
            // On renvoie aussi le détail des emprunts actifs, pour que
            // le frontend puisse afficher "tu as emprunté X, retour le Y"
            // sans requête supplémentaire.
            'active_loans_detail' => array_values(array_map(fn($l) => [
                'book_title' => $l['book_title'],
                'due_at'     => $l['due_at'],
                'overdue'    => $l['due_at'] < $today,
            ], $myActiveLoans)),
        ];
    }
}