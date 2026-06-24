# 🎤 Script de Présentation Orale — 15/20 min
### Projet SOA : API REST Bibliothèque (PHP + MySQL)

> Lis ce document une fois en entier avant la soutenance. Le minutage est indicatif — adapte selon les questions du jury en cours de route.

---

## ⏱️ Découpage du temps (total ~17 min)

| Partie | Durée | Contenu |
|---|---|---|
| 1. Introduction | 1 min | Qui, quoi, contexte |
| 2. Problématique & SOA | 2 min | Pourquoi REST, pourquoi sans framework |
| 3. Architecture globale | 3 min | Le schéma en couches |
| 4. Base de données | 1.5 min | Table books |
| 5. Démo live | 6 min | Le cœur de la présentation |
| 6. Sécurité | 1.5 min | PDO, XSS, validation |
| 7. Difficultés & conclusion | 2 min | Bilan |

---

## 1. Introduction (1 min)

> *« Bonjour, je m'appelle [ton nom], étudiant en Master 1 Systèmes d'Information. Je vais vous présenter mon projet d'Architecture Orientée Services : une API REST développée en PHP natif, sans framework, connectée à une base MySQL, pour gérer le catalogue d'une bibliothèque. »*

Points à dire :
- Le sujet choisi (bibliothèque) et pourquoi (assez riche pour illustrer tout le CRUD).
- L'environnement technique en une phrase : PHP 8, MySQL 8, PDO, Ubuntu 24.04, pas de framework.

---

## 2. Problématique & pourquoi REST/SOA (2 min)

> *« La question de départ était : comment exposer des données pour qu'elles soient consommables par n'importe quel client — un navigateur, une application mobile, ou un autre service — de manière standardisée ? C'est exactement ce que répond l'architecture REST, une déclinaison légère du paradigme SOA. »*

À mentionner (sans tout détailler, le jury peut creuser après) :
- **SOA** = services faiblement couplés, interface bien définie.
- **REST** = 6 contraintes : client-serveur, stateless, cacheable, interface uniforme, couches, code-on-demand (optionnel).
- *Phrase clé à retenir* : **« Stateless veut dire que le serveur ne garde aucune information entre deux requêtes — chaque requête doit contenir tout ce qu'il faut pour être traitée. »**

---

## 3. Architecture globale (3 min)

Dessine ou montre ce schéma (à reproduire sur un slide ou au tableau) :

```
Client HTTP (navigateur / Postman)
        ↓ requête (méthode + URI + JSON)
public/index.php        ← point d'entrée unique (Front Controller)
        ↓
routes/router.php       ← analyse l'URI et la méthode, dispatche
        ↓
controllers/BookController.php   ← valide, nettoie, orchestre
        ↓
models/Book.php          ← exécute le SQL via PDO
        ↓
MySQL (table books)
        ↑ résultat
controllers/BookController.php   ← formate en JSON + code HTTP
        ↑
Client HTTP (réponse JSON)
```

> *« J'ai séparé le projet en 4 couches distinctes : config, modèle, contrôleur, routeur. Chaque couche a une seule responsabilité — c'est le principe de séparation des préoccupations. Le modèle ne connaît que SQL, le contrôleur ne connaît que HTTP, le routeur ne fait que dispatcher. »*

Arborescence à montrer en une phrase :
```
project/
├── config/database.php       (connexion PDO)
├── models/Book.php           (CRUD SQL)
├── controllers/BookController.php (logique REST)
├── routes/router.php         (dispatch)
├── public/index.php          (entrée unique)
├── frontend/ (html/css/js)
└── database/schema.sql
```

---

## 4. Base de données (1.5 min)

> *« J'utilise une seule table, books, volontairement simple pour rester centré sur l'architecture plutôt que sur la complexité du modèle de données. »*

Colonnes à citer rapidement : `id` (PK auto-increment), `title`, `author`, `genre`, `year`, `available` (booléen 0/1), `created_at` (timestamp automatique).

Justification technique à avoir en tête si on te demande :
- **InnoDB** → transactions + clés étrangères possibles.
- **utf8mb4** → vrai UTF-8 complet (accents français, emojis).
- **UNSIGNED** sur l'id → double la plage de valeurs possibles.

---

## 5. Démonstration live (6 min — LE CŒUR DE LA SOUTENANCE)

> *« Je vais maintenant vous montrer l'API en action, en testant chacune des 5 opérations REST. »*

**Avant de commencer** : vérifie que ton serveur tourne (`php -S localhost:8000` dans `public/`) et que MySQL est démarré.

Utilise soit le testeur d'API intégré dans ton frontend, soit Postman/curl. Déroule dans cet ordre :

1. **GET /api/books** → *« Voici la liste de tous les livres, avec un code 200 OK. »*
2. **POST /api/books** avec un nouveau livre → *« Je crée un livre, l'API répond 201 Created avec l'ID généré. »*
3. **GET /api/books/{id}** sur le livre créé → *« Je vérifie qu'il existe bien. »*
4. **PUT /api/books/{id}** → *« Je modifie son titre, réponse 200 OK. »*
5. **GET /api/books/99999** (ID inexistant) → *« Ici je montre la gestion d'erreur : 404 Not Found avec un message clair. »*
6. **DELETE /api/books/{id}** → *« Je supprime le livre de test, 200 OK. »*

Termine par le **frontend** : ouvre la page HTML, montre l'ajout/modification/suppression via l'interface, et explique en une phrase que ça communique avec l'API via `fetch()` en JavaScript.

> *« Tout ce que vous venez de voir dans l'interface passe par les mêmes 5 endpoints que je viens de tester en brut. »*

---

## 6. Sécurité (1.5 min)

> *« Trois protections principales ont été mises en place. »*

- **Injection SQL** → requêtes préparées PDO (`bindParam`) : *« la donnée est toujours traitée comme une valeur, jamais comme du code SQL. »*
- **XSS** → fonction `escapeHtml()` côté frontend avant tout affichage.
- **Validation à deux niveaux** → JavaScript pour l'expérience utilisateur, PHP pour la vraie sécurité : *« on ne fait jamais confiance à ce qui vient du client. »*

---

## 7. Difficultés rencontrées & conclusion (2 min)

Difficultés à mentionner (en choisis 2, pas besoin des 4) :
- **CORS** : le frontend et l'API communiquent par des origines différentes, j'ai dû ajouter les headers `Access-Control-Allow-*`.
- **Lecture du JSON** : `$_POST` ne fonctionne pas pour du JSON, j'ai dû utiliser `php://input` + `json_decode()`.
- **Routage sans framework** : j'ai construit le dispatch moi-même avec des expressions régulières (`preg_match`).

> *« Pour conclure, ce projet m'a permis de comprendre en profondeur les mécanismes qu'un framework cache habituellement : le routage, la validation, la connexion PDO, la construction des réponses HTTP. Les pistes d'évolution possibles seraient l'ajout d'une authentification JWT, de la pagination, et un versioning d'API en /api/v1/. Je vous remercie de votre attention et je suis prêt à répondre à vos questions. »*

---

## 🧠 Pense-bête : 10 questions les plus probables

1. **Pourquoi PDO et pas mysqli ?** → Portable entre SGBD, supporte nativement les requêtes préparées.
2. **C'est quoi le DSN ?** → `mysql:host=...;dbname=...;charset=...`, la chaîne d'identification de la connexion.
3. **Pourquoi pas de framework ?** → Choix pédagogique pour comprendre les mécanismes sous-jacents.
4. **Différence entre 404 et 422 ?** → 404 = ressource introuvable (mauvais ID), 422 = données mal formées (champ manquant).
5. **C'est quoi CORS ?** → Mécanisme du navigateur qui bloque les requêtes cross-origin sauf autorisation explicite via headers.
6. **Comment évitez-vous l'injection SQL ?** → Requêtes préparées : `bindParam()` sépare le code SQL de la donnée.
7. **Pourquoi php://input ?** → `$_POST` ne lit pas le JSON, seulement les formulaires classiques.
8. **C'est quoi le pattern Singleton dans Database ?** → Une seule connexion PDO réutilisée, pas de connexions multiples inutiles.
9. **Comment sécuriseriez-vous avec une authentification ?** → JWT : token signé envoyé dans le header Authorization, vérifié sans session serveur.
10. **Pourquoi stateless ?** → Chaque requête est autonome, pas de session PHP : ça facilite la scalabilité.

---

## ✅ Checklist avant d'entrer en salle

- [ ] Serveur PHP démarré (`php -S localhost:8000` dans `public/`)
- [ ] MySQL démarré et base importée (`mysql -u root -p < database/schema.sql`)
- [ ] Frontend testé une dernière fois (ajout/modif/suppr fonctionnent)
- [ ] Postman ou testeur intégré ouvert et prêt
- [ ] Ce script relu une fois à voix haute (minutage réel ≠ minutage lu en silence)