<?php
// ============================================================
//  PHASE 6 : controllers/BookController.php
//  Contrôleur REST — reçoit la requête HTTP, appelle le modèle,
//  retourne une réponse JSON avec le bon code HTTP
// ============================================================

require_once __DIR__ . '/../models/Book.php';

/**
 * Classe BookController
 *
 * Fait le lien entre le routeur (qui décode l'URL/méthode HTTP)
 * et le modèle Book (qui accède à MySQL).
 *
 * Chaque méthode publique correspond à un endpoint REST :
 *   getAll()    → GET    /api/books
 *   getOne()    → GET    /api/books/{id}
 *   create()    → POST   /api/books
 *   update()    → PUT    /api/books/{id}
 *   delete()    → DELETE /api/books/{id}
 */
class BookController
{
    private Book $model;

    public function __construct()
    {
        $this->model = new Book();
    }

    // --------------------------------------------------------
    //  Méthode utilitaire : envoyer une réponse JSON
    // --------------------------------------------------------
    /**
     * Définit les headers, le code HTTP, et encode la réponse en JSON.
     *
     * @param array $data        Données à sérialiser
     * @param int   $statusCode  Code HTTP (200, 201, 400, 404, 500…)
     */
    private function sendResponse(array $data, int $statusCode = 200): void
    {
        // Header indispensable : indique au client que la réponse est du JSON
        header('Content-Type: application/json; charset=utf-8');

        // Autoriser les requêtes Cross-Origin (pour le frontend servi séparément)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        // Définit le code de statut HTTP (ex: 201 Created, 404 Not Found)
        http_response_code($statusCode);

        // JSON_UNESCAPED_UNICODE = ne pas échapper les caractères accentués
        // JSON_PRETTY_PRINT      = formatage lisible (retirer en production)
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit; // Stopper l'exécution après l'envoi de la réponse
    }

    // --------------------------------------------------------
    //  Méthode utilitaire : valider les données d'entrée
    // --------------------------------------------------------
    /**
     * Vérifie que les champs obligatoires sont présents et non vides.
     *
     * @param array $data   Données à valider
     * @param array $fields Champs obligatoires
     * @return array        ['valid' => bool, 'errors' => array]
     */
    private function validate(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                $errors[] = "Le champ '{$field}' est obligatoire.";
            }
        }

        // Validation supplémentaire : year doit être un entier valide
        if (isset($data['year']) && !filter_var($data['year'], FILTER_VALIDATE_INT)) {
            $errors[] = "Le champ 'year' doit être un entier.";
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    // --------------------------------------------------------
    //  GET /api/books — Récupérer tous les livres
    // --------------------------------------------------------
    /**
     * Supporte des query params optionnels :
     *   ?genre=Fantasy
     *   ?available=1
     *   ?search=Orwell
     */
    public function getAll(): void
    {
        // Récupération des paramètres GET (filtres)
        $filters = [
            'genre'     => $_GET['genre']     ?? '',
            'available' => $_GET['available'] ?? '',
            'search'    => $_GET['search']    ?? '',
        ];

        $result = $this->model->read($filters);

        if ($result['success']) {
            // 200 OK = requête réussie, ressources retournées
            $this->sendResponse($result, 200);
        } else {
            // 500 Internal Server Error = problème côté serveur
            $this->sendResponse($result, 500);
        }
    }

    // --------------------------------------------------------
    //  GET /api/books/{id} — Récupérer un livre par ID
    // --------------------------------------------------------
    public function getOne(int $id): void
    {
        // Validation de base : l'ID doit être un entier positif
        if ($id <= 0) {
            $this->sendResponse(['success' => false, 'message' => 'ID invalide.'], 400);
        }

        $result = $this->model->readOne($id);

        if ($result['success']) {
            $this->sendResponse($result, 200);
        } else {
            // 404 Not Found = la ressource demandée n'existe pas
            $this->sendResponse($result, 404);
        }
    }

    // --------------------------------------------------------
    //  POST /api/books — Créer un nouveau livre
    // --------------------------------------------------------
    public function create(): void
    {
        // Lire le corps de la requête HTTP (JSON envoyé par le client)
        // php://input = flux brut de la requête (contrairement à $_POST qui ne gère pas JSON)
        $rawBody = file_get_contents('php://input');
        $data    = json_decode($rawBody, true); // true = tableau associatif

        // Vérifier que le JSON est valide
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $this->sendResponse([
                'success' => false,
                'message' => 'Corps de la requête invalide. Envoyez du JSON valide.'
            ], 400);
        }

        // Validation des champs obligatoires
        $validation = $this->validate($data, ['title', 'author', 'genre', 'year']);
        if (!$validation['valid']) {
            // 422 Unprocessable Entity = données syntaxiquement OK mais sémantiquement invalides
            $this->sendResponse([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors'  => $validation['errors']
            ], 422);
        }

        // Nettoyage : trim() supprime les espaces, intval() convertit en entier
        $cleanData = [
            'title'     => trim($data['title']),
            'author'    => trim($data['author']),
            'genre'     => trim($data['genre']),
            'year'      => intval($data['year']),
            'available' => isset($data['available']) ? (int)$data['available'] : 1,
        ];

        $result = $this->model->create($cleanData);

        if ($result['success']) {
            // 201 Created = ressource créée avec succès
            $this->sendResponse($result, 201);
        } else {
            $this->sendResponse($result, 500);
        }
    }

    // --------------------------------------------------------
    //  PUT /api/books/{id} — Mettre à jour un livre
    // --------------------------------------------------------
    public function update(int $id): void
    {
        if ($id <= 0) {
            $this->sendResponse(['success' => false, 'message' => 'ID invalide.'], 400);
        }

        $rawBody = file_get_contents('php://input');
        $data    = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $this->sendResponse([
                'success' => false,
                'message' => 'Corps de la requête invalide.'
            ], 400);
        }

        $validation = $this->validate($data, ['title', 'author', 'genre', 'year']);
        if (!$validation['valid']) {
            $this->sendResponse([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors'  => $validation['errors']
            ], 422);
        }

        $cleanData = [
            'title'     => trim($data['title']),
            'author'    => trim($data['author']),
            'genre'     => trim($data['genre']),
            'year'      => intval($data['year']),
            'available' => isset($data['available']) ? (int)$data['available'] : 1,
        ];

        $result = $this->model->update($id, $cleanData);

        if ($result['success']) {
            // 200 OK = mise à jour réussie
            $this->sendResponse($result, 200);
        } elseif (str_contains($result['message'], 'introuvable')) {
            $this->sendResponse($result, 404);
        } else {
            $this->sendResponse($result, 500);
        }
    }

    // --------------------------------------------------------
    //  DELETE /api/books/{id} — Supprimer un livre
    // --------------------------------------------------------
    public function delete(int $id): void
    {
        if ($id <= 0) {
            $this->sendResponse(['success' => false, 'message' => 'ID invalide.'], 400);
        }

        $result = $this->model->delete($id);

        if ($result['success']) {
            // 200 OK avec message de confirmation
            $this->sendResponse($result, 200);
        } elseif (str_contains($result['message'], 'introuvable')) {
            $this->sendResponse($result, 404);
        } else {
            $this->sendResponse($result, 500);
        }
    }

    // --------------------------------------------------------
    //  OPTIONS — Pré-vérification CORS (preflight)
    // --------------------------------------------------------
    /**
     * Les navigateurs envoient une requête OPTIONS avant POST/PUT/DELETE
     * pour vérifier les permissions CORS. On répond simplement 200.
     */
    public function options(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        http_response_code(200);
        exit;
    }
}