# 🔧 Correctifs V3 — Sécurité des retours + robustesse frontend

## Diagnostic (résumé)

| # | Problème | Cause racine | Correction |
|---|---|---|---|
| 1 | Un membre pouvait voir "Retourner" sur le livre d'un autre | `app.js` ne vérifiait pas le propriétaire de l'emprunt | `buildBookCard` vérifie maintenant `loan.user_id === currentUser.id` |
| 2 | Le backend acceptait le retour de n'importe qui | `LoanService::returnBook()` ne filtrait pas par utilisateur | Nouvelle méthode `findActiveLoanForBookAndUser()` + vérification de rôle |
| 3 | Emprunts actifs récupérés seulement pour admin | `if (currentUser.role === 'admin')` dans l'ancien `app.js` | Chargés pour **tout** utilisateur connecté — le backend filtre déjà la portée |
| 4 | `/auth/me` ne renvoyait pas `full_name` | Oubli dans le payload de retour du contrôleur | `AuthController::me()` requête désormais `UserRepository::find()` au lieu de ne renvoyer que le JWT payload |
| 5 | Pas de déconnexion auto sur token expiré en cours de session | Chaque fonction gérait ses erreurs localement | `apiFetch()` centralisé détecte les 401 et déconnecte automatiquement |

## Fichiers à remplacer

```
backend/
├── repositories/LoanRepository.php   (remplace l'existant)
├── services/LoanService.php          (remplace l'existant)
├── controllers/LoanController.php    (remplace l'existant)
└── controllers/AuthController.php    (remplace l'existant)

frontend/
└── app.js                            (remplace l'existant)
```

`index.html` et `style.css` **ne changent pas** — aucune modification nécessaire, comme demandé.

## Le changement de sécurité le plus important

**Avant** : n'importe quel membre authentifié pouvait appeler `POST /api/loans/return` avec le `book_id` d'un livre emprunté par quelqu'un d'autre, et le faire "rendre" à sa place — perturbant le suivi réel des emprunts.

**Après** : `LoanController::returnBook()` transmet le rôle de l'appelant à `LoanService::returnBook()`, qui applique :
- **Membre** → ne peut retourner que SES propres emprunts actifs (`findActiveLoanForBookAndUser`)
- **Admin** → peut retourner n'importe quel emprunt actif (cas réel : retour physique à l'accueil, validé par le bibliothécaire)

C'est un bon exemple à citer en soutenance si on te demande "as-tu identifié des failles toi-même ?" — la garde frontend seule (cacher le bouton) n'aurait rien empêché côté `curl`/Postman ; il fallait le vrai contrôle serveur.

## apiFetch() — la nouvelle couche centralisée

Toute la logique réseau passe maintenant par une seule fonction :

```js
async function apiFetch(path, options = {}) {
    // ajoute Authorization si token présent
    // gère les erreurs réseau (serveur injoignable)
    // gère les réponses non-JSON (crash PHP renvoyant du HTML)
    // détecte les 401 -> déconnexion automatique + toast
    // retourne toujours { ok, status, data }
}
```

Avantage concret : si demain tu ajoutes une nouvelle fonctionnalité qui appelle l'API, tu n'as pas à réécrire la gestion d'erreur — `apiFetch()` s'en occupe uniformément.

## Tests effectués

J'ai vérifié :
- **Syntaxe JS** validée avec `node --check` (aucune erreur)
- **Toutes les fonctions `onclick=""`** du HTML sont bien des fonctions globales accessibles
- **La logique de décision Emprunter/Retourner** testée sur 5 scénarios isolés (non connecté, dispo, mon emprunt, emprunt d'un autre, admin) — tous corrects
- **Accolades PHP équilibrées** sur les 4 fichiers backend modifiés

Je n'ai pas pu exécuter le PHP réel (pas d'interpréteur disponible dans mon environnement) — teste la séquence ci-dessous avant de considérer le correctif validé.

## Comment tester le correctif de sécurité

```bash
docker compose up -d --build

# 1. Connecte Sophie (membre, id=2) et récupère son token
TOKEN_SOPHIE=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"sophie@bibliotheque.local","password":"membre123"}' | grep -oP '"token":\s*"([^"]+)"' | cut -d'"' -f4)

# 2. Connecte Lucas (membre, id=3) et récupère son token
TOKEN_LUCAS=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"lucas@bibliotheque.local","password":"membre123"}' | grep -oP '"token":\s*"([^"]+)"' | cut -d'"' -f4)

# 3. Sophie emprunte le livre #2 (1984)
curl -X POST http://localhost:8000/api/loans/borrow \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN_SOPHIE" \
  -d '{"book_id": 2}'

# 4. Lucas tente de RETOURNER le livre que SOPHIE a emprunté
#    -> doit échouer avec "Aucun emprunt actif trouvé pour ce livre à ton nom."
curl -X POST http://localhost:8000/api/loans/return \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN_LUCAS" \
  -d '{"book_id": 2}'

# 5. Sophie retourne SON propre emprunt -> doit réussir
curl -X POST http://localhost:8000/api/loans/return \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN_SOPHIE" \
  -d '{"book_id": 2}'
```

Si l'étape 4 réussissait (au lieu d'échouer), le correctif ne serait pas appliqué correctement — vérifie que les bons fichiers ont été copiés.

## Vérifier /auth/me renvoie bien full_name maintenant

```bash
curl http://localhost:8000/api/auth/me -H "Authorization: Bearer $TOKEN_SOPHIE"
# doit contenir "full_name": "Sophie Membre", pas seulement id/email/role
```

## Prochaine étape

Une fois ce correctif validé, on peut passer au dashboard avec les statistiques (`LoanRepository::getStats()` est déjà prêt et inchangé par ce correctif), ou continuer les tests backend sur d'autres cas limites si tu préfères avancer prudemment.