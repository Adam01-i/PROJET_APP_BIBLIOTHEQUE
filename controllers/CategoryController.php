<?php
// ============================================================
//  controllers/CategoryController.php
//  Endpoints : GET/POST /api/categories, PUT/DELETE /api/categories/{id}
// ============================================================

require_once __DIR__ . '/../repositories/CategoryRepository.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../middlewares/RoleMiddleware.php';

/**
 * Pas de "CategoryService" séparé : les catégories n'ont qu'une
 * validation triviale (nom non vide, pas de doublon). Créer un
 * service ici ajouterait une couche sans bénéfice réel — le repository
 * pattern n'est pas une religion, on l'applique où il apporte de la
 * valeur (books, loans) et on reste simple où ce n'est pas nécessaire.
 */
class CategoryController
{
    private CategoryRepository $repo;

    public function __construct()
    {
        $this->repo = new CategoryRepository();
    }

    private function sendResponse(array $data, int $statusCode = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private function getJsonBody(): ?array
    {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : null;
    }

    public function getAll(): void
    {
        $categories = $this->repo->findAll();
        $this->sendResponse(['success' => true, 'count' => count($categories), 'data' => $categories], 200);
    }

    public function getOne(int $id): void
    {
        $category = $this->repo->find($id);
        if ($category === null) {
            $this->sendResponse(['success' => false, 'message' => 'Catégorie introuvable.'], 404);
        }
        $this->sendResponse(['success' => true, 'data' => $category], 200);
    }

    public function create(): void
    {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::handle($payload, 'admin');

        $data = $this->getJsonBody();
        if ($data === null || empty($data['name'])) {
            $this->sendResponse(['success' => false, 'message' => "Le champ 'name' est obligatoire."], 422);
        }

        if ($this->repo->nameExists($data['name'])) {
            $this->sendResponse(['success' => false, 'message' => 'Cette catégorie existe déjà.'], 422);
        }

        $id = $this->repo->create($data);
        $this->sendResponse(['success' => true, 'id' => $id], 201);
    }

    public function update(int $id): void
    {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::handle($payload, 'admin');

        if ($this->repo->find($id) === null) {
            $this->sendResponse(['success' => false, 'message' => 'Catégorie introuvable.'], 404);
        }

        $data = $this->getJsonBody();
        if ($data === null || empty($data['name'])) {
            $this->sendResponse(['success' => false, 'message' => "Le champ 'name' est obligatoire."], 422);
        }

        $this->repo->update($id, $data);
        $this->sendResponse(['success' => true, 'message' => 'Catégorie mise à jour.'], 200);
    }

    public function delete(int $id): void
    {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::handle($payload, 'admin');

        if ($this->repo->find($id) === null) {
            $this->sendResponse(['success' => false, 'message' => 'Catégorie introuvable.'], 404);
        }

        $this->repo->delete($id);
        $this->sendResponse([
            'success' => true,
            'message' => 'Catégorie supprimée. Les livres associés sont passés en "Non classé".'
        ], 200);
    }

    public function options(): void
    {
        http_response_code(200);
        exit;
    }
}