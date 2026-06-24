<?php
// ============================================================
//  services/BookService.php
//  Logique métier des livres : validation + délégation au repository
// ============================================================

require_once __DIR__ . '/../repositories/BookRepository.php';
require_once __DIR__ . '/../repositories/CategoryRepository.php';

class BookService
{
    private BookRepository $books;
    private CategoryRepository $categories;

    public function __construct()
    {
        $this->books      = new BookRepository();
        $this->categories = new CategoryRepository();
    }

    public function getAll(array $filters = []): array
    {
        return $this->books->findAll($filters);
    }

    public function getOne(int $id): ?array
    {
        return $this->books->find($id);
    }

    /**
     * Valide et crée un livre.
     * @return array ['success' => bool, 'id' => ?int, 'errors' => ?array]
     */
    public function create(array $data): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $id = $this->books->create($this->sanitize($data));
        return ['success' => true, 'id' => $id];
    }

    public function update(int $id, array $data): array
    {
        if ($this->books->find($id) === null) {
            return ['success' => false, 'message' => 'Livre introuvable.'];
        }

        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $this->books->update($id, $this->sanitize($data));
        return ['success' => true];
    }

    public function delete(int $id): array
    {
        if ($this->books->find($id) === null) {
            return ['success' => false, 'message' => 'Livre introuvable.'];
        }

        $this->books->delete($id);
        return ['success' => true];
    }

    /**
     * Valide les champs d'un livre.
     * @return array Liste des messages d'erreur (vide si tout est valide)
     */
    private function validate(array $data): array
    {
        $errors = [];

        foreach (['title', 'author', 'year'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[] = "Le champ '{$field}' est obligatoire.";
            }
        }

        if (isset($data['year']) && !filter_var($data['year'], FILTER_VALIDATE_INT)) {
            $errors[] = "Le champ 'year' doit être un entier.";
        }

        // Si une catégorie est précisée, elle doit exister réellement —
        // sinon la FK échouerait silencieusement côté MySQL avec un
        // message d'erreur peu clair pour l'utilisateur final.
        if (!empty($data['category_id']) && $this->categories->find((int) $data['category_id']) === null) {
            $errors[] = "La catégorie spécifiée n'existe pas.";
        }

        return $errors;
    }

    /**
     * Nettoie les données avant insertion/mise à jour.
     */
    private function sanitize(array $data): array
    {
        return [
            'title'       => trim($data['title']),
            'author'      => trim($data['author']),
            'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'year'        => intval($data['year']),
            'available'   => isset($data['available']) ? (int) $data['available'] : 1,
        ];
    }
}