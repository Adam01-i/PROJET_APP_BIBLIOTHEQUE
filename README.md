````markdown
# 📚 BiblioSphere

## Plateforme moderne de gestion de bibliothèque

<p align="center">

**BiblioSphere** est une application web complète de gestion de bibliothèque permettant de centraliser la gestion des livres, des catégories, des utilisateurs et des emprunts au sein d'une plateforme sécurisée et conteneurisée.

</p>

---

## 📌 Présentation

**BiblioSphere** est un système d'information dédié à la gestion numérique d'une bibliothèque.

L'application permet aux administrateurs de gérer le catalogue de livres, les catégories et les utilisateurs, tandis que les membres peuvent consulter le catalogue, emprunter des livres et gérer leurs propres emprunts.

Le projet a été conçu autour d'une **architecture client/API/base de données**, avec une séparation claire entre :

- l'interface utilisateur ;
- l'API REST ;
- la logique métier ;
- l'accès aux données ;
- la base de données ;
- les mécanismes d'authentification et d'autorisation.

L'ensemble de l'infrastructure peut être exécuté avec **Docker Compose**, permettant de disposer d'un environnement reproductible et facilement déployable.

---

# 🎯 Objectifs du projet

Les principaux objectifs de BiblioSphere sont :

- Numériser la gestion d'une bibliothèque.
- Centraliser les informations relatives aux livres.
- Gérer les utilisateurs et leurs rôles.
- Permettre l'emprunt et le retour des ouvrages.
- Conserver l'historique des opérations.
- Sécuriser les accès à l'API.
- Mettre en place une authentification basée sur JWT.
- Séparer les responsabilités dans le backend.
- Conteneuriser l'application avec Docker.
- Concevoir une architecture évolutive et maintenable.

---

# ✨ Fonctionnalités

## 👤 Gestion des utilisateurs

L'application distingue plusieurs types d'utilisateurs.

### Administrateur

L'administrateur peut :

- consulter le catalogue ;
- ajouter des livres ;
- modifier des livres ;
- supprimer des livres ;
- gérer les catégories ;
- consulter les emprunts ;
- effectuer certaines opérations administratives ;
- consulter les statistiques ;
- accéder aux informations nécessaires à la supervision du système.

### Membre

Le membre peut :

- consulter le catalogue ;
- consulter les détails d'un livre ;
- emprunter un livre disponible ;
- consulter ses emprunts ;
- retourner ses propres emprunts ;
- consulter son profil.

---

# 🔐 Authentification et sécurité

BiblioSphere utilise une authentification basée sur des **JSON Web Tokens (JWT)**.

Le processus général est :

```text
Utilisateur
     │
     │ Identifiants
     ▼
POST /api/auth/login
     │
     ▼
AuthController
     │
     ▼
AuthService
     │
     ▼
UserRepository
     │
     ▼
MySQL
     │
     ▼
JWT généré
     │
     ▼
Client
````

Lorsqu'un utilisateur accède à une route protégée, le token est transmis dans l'en-tête :

```http
Authorization: Bearer <token>
```

Le backend vérifie alors :

1. la présence du token ;
2. sa validité ;
3. sa signature ;
4. sa date d'expiration ;
5. l'identité de l'utilisateur ;
6. son rôle lorsque cela est nécessaire.

---

# 🛡️ Autorisation par rôle

Deux rôles principaux sont utilisés :

```text
admin
membre
```

Le contrôle des rôles est centralisé dans :

```text
middlewares/
└── RoleMiddleware.php
```

Cela permet d'éviter de recopier les mêmes contrôles dans chaque contrôleur.

Exemple :

```text
GET    /api/books              Public
GET    /api/books/{id}         Public

POST   /api/books              Admin
PUT    /api/books/{id}         Admin
DELETE /api/books/{id}         Admin

POST   /api/loans/borrow       Authentifié
POST   /api/loans/return       Authentifié
GET    /api/loans              Authentifié
```

---

# 🏗️ Architecture générale

```text
                         ┌──────────────────────┐
                         │       UTILISATEUR    │
                         │      Navigateur Web  │
                         └──────────┬───────────┘
                                    │
                                    │ HTTP
                                    ▼
                         ┌──────────────────────┐
                         │       FRONTEND       │
                         │       Nginx          │
                         │                      │
                         │ HTML / CSS / JS      │
                         └──────────┬───────────┘
                                    │
                                    │ Fetch / AJAX
                                    ▼
                         ┌──────────────────────┐
                         │        API REST      │
                         │      PHP + Apache    │
                         │                      │
                         │ Controllers          │
                         │ Services             │
                         │ Repositories         │
                         │ Middlewares          │
                         └──────────┬───────────┘
                                    │
                                    │ PDO
                                    ▼
                         ┌──────────────────────┐
                         │        MySQL         │
                         │                      │
                         │ users                │
                         │ categories           │
                         │ books                │
                         │ loans                │
                         │ activity_logs        │
                         └──────────────────────┘
```

---

# 🐳 Architecture Docker

L'application peut être exécutée avec trois composants principaux :

```text
                    Docker Network
┌────────────────────────────────────────────────────┐
│                                                    │
│  ┌──────────────┐       ┌──────────────┐           │
│  │   FRONTEND   │──────►│     API      │           │
│  │     Nginx    │ HTTP  │ PHP + Apache │           │
│  │              │       │              │           │
│  │    :8082     │       │    :8000     │           │
│  └──────────────┘       └──────┬───────┘           │
│                                │                   │
│                                │ PDO               │
│                                ▼                   │
│                         ┌──────────────┐           │
│                         │    MySQL     │           │
│                         │              │           │
│                         │    :3306     │           │
│                         └──────────────┘           │
│                                                    │
└────────────────────────────────────────────────────┘
```

## Services

| Service  | Technologie      |   Port |
| -------- | ---------------- | -----: |
| Frontend | Nginx            | `8082` |
| API      | PHP 8.3 + Apache | `8000` |
| Database | MySQL 8.4        | `3306` |

---

# 🔄 Cycle d'une requête

Prenons l'exemple de l'ajout d'un livre.

```text
1. L'utilisateur remplit le formulaire
                │
                ▼
2. JavaScript envoie POST /api/books
                │
                ▼
3. Router PHP identifie la route
                │
                ▼
4. BookController traite la requête
                │
                ▼
5. BookService applique la logique métier
                │
                ▼
6. BookRepository exécute la requête SQL
                │
                ▼
7. MySQL enregistre les données
                │
                ▼
8. L'API retourne une réponse JSON
                │
                ▼
9. Le frontend actualise l'interface
```

Cette séparation permet de maintenir une architecture claire et évolutive.

---

# 🧱 Architecture logicielle du backend

Le backend suit une organisation inspirée du modèle **Repository / Service / Controller**.

```text
backend/
│
├── config/
│   ├── database.php
│   └── jwt.php
│
├── controllers/
│   ├── AuthController.php
│   ├── BookController.php
│   ├── CategoryController.php
│   ├── DashboardController.php
│   └── LoanController.php
│
├── middlewares/
│   ├── AuthMiddleware.php
│   └── RoleMiddleware.php
│
├── models/
│   └── Book.php
│
├── repositories/
│   ├── AbstractRepository.php
│   ├── ActivityLogRepository.php
│   ├── BookRepository.php
│   ├── CategoryRepository.php
│   ├── LoanRepository.php
│   ├── RepositoryInterface.php
│   └── UserRepository.php
│
├── services/
│   ├── ActivityLogService.php
│   ├── AuthService.php
│   ├── BookService.php
│   ├── DashboardService.php
│   ├── JwtService.php
│   └── LoanService.php
│
└── routes/
    └── router.php
```

---

# 🧩 Repository Pattern

Les repositories sont responsables de l'accès aux données.

Exemples :

```text
BookRepository
CategoryRepository
UserRepository
LoanRepository
ActivityLogRepository
```

Le principe est simple :

> Le Repository fait l'accès aux données, mais ne contient pas la logique métier.

Cela permet d'éviter de mélanger SQL, validation et règles fonctionnelles.

---

# ⚙️ Services

Les services regroupent la logique métier.

Par exemple :

```text
LoanService
```

peut orchestrer plusieurs opérations :

```text
Emprunt
   │
   ├── création du prêt
   │
   ├── modification de la disponibilité du livre
   │
   └── création d'une trace dans activity_logs
```

Cette approche évite de placer toute la logique dans les contrôleurs.

---

# 🛡️ Middlewares

Deux middlewares principaux sont utilisés.

## AuthMiddleware

Responsable de la vérification du JWT.

## RoleMiddleware

Responsable du contrôle des permissions selon le rôle.

Architecture :

```text
Request
   │
   ▼
AuthMiddleware
   │
   ▼
RoleMiddleware
   │
   ▼
Controller
   │
   ▼
Service
   │
   ▼
Repository
```

---

# 🗄️ Architecture de la base de données

La version finale du système repose notamment sur les tables suivantes :

```text
users
categories
books
loans
activity_logs
```

## Relations principales

```text
users
  │
  │ 1
  │
  │ N
  ▼
loans
  │
  │ N
  │
  │ 1
  ▼
books
  │
  │ N
  │
  │ 1
  ▼
categories
```

Les opérations importantes peuvent également être enregistrées dans :

```text
activity_logs
```

---

# 📚 Gestion des livres

Chaque livre possède notamment des informations permettant de gérer :

* son titre ;
* son auteur ;
* son année ;
* sa catégorie ;
* sa disponibilité.

La disponibilité permet de déterminer si un livre peut être emprunté.

---

# 🔄 Gestion des emprunts

Lorsqu'un membre emprunte un livre :

```text
Livre disponible
       │
       ▼
Création du prêt
       │
       ▼
Livre marqué indisponible
       │
       ▼
Action enregistrée
```

Lors du retour :

```text
Emprunt actif
       │
       ▼
Validation du propriétaire
       │
       ▼
Emprunt clôturé
       │
       ▼
Livre disponible
       │
       ▼
Action enregistrée
```

---

# 🔒 Sécurité des emprunts

Une correction importante a été apportée au système.

Un membre ne peut pas retourner le livre emprunté par un autre membre.

Le contrôle est réalisé **côté serveur**, et pas uniquement dans l'interface.

```text
Utilisateur A
     │
     │ tente de retourner
     ▼
Livre emprunté par B
     │
     ▼
LoanService
     │
     ▼
Vérification user_id
     │
     ▼
❌ Accès refusé
```

L'interface masque également les actions qui ne sont pas pertinentes, mais le backend reste la véritable barrière de sécurité.

---

# 📊 Journalisation

Les opérations importantes peuvent être enregistrées dans :

```text
activity_logs
```

Les informations peuvent notamment permettre de conserver :

* l'utilisateur ;
* l'action ;
* la date ;
* l'adresse IP ;
* le contexte de l'opération.

Cette fonctionnalité permet d'améliorer la traçabilité du système.

---

# 🌐 API REST

L'application expose une API REST.

## Authentification

```http
POST /api/auth/login
POST /api/auth/register
GET  /api/auth/me
```

## Livres

```http
GET    /api/books
GET    /api/books/{id}
POST   /api/books
PUT    /api/books/{id}
DELETE /api/books/{id}
```

## Catégories

```http
GET    /api/categories
POST   /api/categories
PUT    /api/categories/{id}
DELETE /api/categories/{id}
```

## Emprunts

```http
GET  /api/loans
POST /api/loans/borrow
POST /api/loans/return
```

---

# 🖥️ Interface utilisateur

L'interface frontend est développée avec :

* HTML5 ;
* CSS3 ;
* JavaScript ;
* Fetch API.

Le frontend communique avec le backend exclusivement à travers l'API REST.

---

# 📸 Captures d'écran

## 🏠 Accueil

![Page d'accueil](docs/screenshots/accueil.png)

---

## 🔐 Connexion

![Page de connexion](docs/screenshots/connexion.png)

---

## 📚 Catalogue

![Catalogue des livres](docs/screenshots/catalogue.png)

---

## 📊 Dashboard

![Dashboard](docs/screenshots/dashboard.png)

---

## 📖 Gestion des livres

![Gestion des livres](docs/screenshots/gestion-livres.png)

---

## 🔄 Gestion des emprunts

![Gestion des emprunts](docs/screenshots/emprunts.png)

---

# 🚀 Installation

## Prérequis

Avant de commencer, installer :

* Docker
* Docker Compose
* Git

Vérifier Docker :

```bash
docker --version
docker compose version
```

---

# 📦 Installation avec Docker

Cloner le projet :

```bash
git clone <URL_DU_REPOSITORY>
cd PROJET_APP_BIBLIOTHEQUE
```

Construire et démarrer les services :

```bash
docker compose up -d --build
```

Vérifier les conteneurs :

```bash
docker compose ps
```

---

# 🌐 Accès à l'application

Frontend :

```text
http://localhost:8082
```

API :

```text
http://localhost:8000
```

---

# 🧪 Tests de l'API

## Connexion

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@bibliotheque.local","password":"admin123"}'
```

Une réponse contenant un JWT doit être retournée.

---

## Vérification de l'utilisateur connecté

```bash
curl http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer <TOKEN>"
```

---

## Liste des livres

```bash
curl http://localhost:8000/api/books
```

---

## Emprunter un livre

```bash
curl -X POST http://localhost:8000/api/loans/borrow \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"book_id":1}'
```

---

## Retourner un livre

```bash
curl -X POST http://localhost:8000/api/loans/return \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"book_id":1}'
```

---

# 🧪 Validation et robustesse

Plusieurs contrôles ont été réalisés au cours du développement :

* vérification de la syntaxe JavaScript ;
* vérification des routes API ;
* tests d'authentification ;
* tests d'autorisation ;
* tests des rôles ;
* tests d'emprunt ;
* tests de retour ;
* vérification du propriétaire d'un emprunt ;
* gestion des tokens expirés ;
* gestion des erreurs réseau ;
* vérification des réponses JSON ;
* vérification de la structure backend.

---

# 🐳 Persistance des données

La base MySQL utilise un volume Docker afin de garantir la persistance des données.

```text
Container MySQL
       │
       ▼
Volume Docker
       │
       ▼
Données persistantes
```

Ainsi, la suppression ou le redémarrage du conteneur ne provoque pas automatiquement la perte des données.

---

# 📁 Structure du projet

```text
PROJET_APP_BIBLIOTHEQUE/
│
├── config/
├── controllers/
├── database/
├── frontend/
├── middlewares/
├── models/
├── repositories/
├── routes/
├── services/
│
├── public/
│
├── Dockerfile
├── docker-compose.yml
├── README.md
└── ...
```

---

# 🧠 Choix architecturaux

Plusieurs choix ont été réalisés afin d'obtenir une architecture plus maintenable.

### Séparation Frontend / Backend

Le frontend ne communique pas directement avec MySQL.

```text
Frontend → API → Database
```

Cette séparation améliore la sécurité et permet de faire évoluer chaque couche indépendamment.

### Repository Pattern

L'accès aux données est centralisé dans les repositories.

### Services

Les règles métier sont séparées des contrôleurs.

### Middlewares

L'authentification et les autorisations sont centralisées.

### Docker

L'environnement est reproductible et portable.

### JWT

L'API utilise une authentification sans session serveur traditionnelle.

---

# ⚠️ Limites actuelles

Comme tout projet, BiblioSphere possède encore certaines possibilités d'amélioration.

Une amélioration importante concerne la gestion transactionnelle des opérations d'emprunt.

Une opération d'emprunt touche plusieurs ressources :

```text
loans
books
activity_logs
```

L'utilisation systématique de transactions SQL permettrait de garantir une cohérence encore plus forte en cas d'échec intermédiaire.

---

# 🚀 Perspectives d'évolution

Les évolutions possibles comprennent notamment :

* gestion de plusieurs exemplaires d'un même livre ;
* système de notifications ;
* notifications de retard ;
* système de réservation ;
* recherche avancée ;
* filtres multicritères ;
* pagination ;
* statistiques avancées ;
* export PDF ;
* export Excel ;
* gestion fine des permissions ;
* rôle bibliothécaire ;
* historique détaillé des opérations ;
* tests automatisés ;
* documentation Swagger/OpenAPI ;
* déploiement cloud ;
* CI/CD ;
* monitoring ;
* sauvegardes automatisées.

---

# 🎓 Apports pédagogiques

Ce projet permet de mettre en pratique plusieurs concepts étudiés en Master :

* conception de systèmes d'information ;
* architecture logicielle ;
* développement backend ;
* développement frontend ;
* API REST ;
* bases de données ;
* authentification ;
* sécurité applicative ;
* architecture modulaire ;
* conteneurisation ;
* Docker ;
* gestion des rôles ;
* journalisation ;
* conception et organisation du code.

Le projet constitue donc une mise en pratique concrète de plusieurs notions liées au développement d'applications d'entreprise et aux systèmes d'information.

---

# 🎤 Présentation du projet

BiblioSphere peut être présenté comme un système d'information permettant de moderniser la gestion d'une bibliothèque.

L'utilisateur interagit avec une interface web. Le frontend communique avec une API REST développée en PHP. L'API applique les règles métier, contrôle les autorisations et communique avec une base MySQL.

L'architecture Repository / Service / Controller permet de séparer les responsabilités et d'améliorer la maintenabilité du système.

L'authentification JWT permet de sécuriser les routes nécessitant une connexion, tandis que les middlewares permettent de contrôler les droits selon le rôle de l'utilisateur.

Enfin, Docker permet d'isoler les différents composants de l'application et de faciliter son déploiement.

---

# 📌 Technologies utilisées

| Domaine          | Technologie                       |
| ---------------- | --------------------------------- |
| Frontend         | HTML5 / CSS3 / JavaScript         |
| Backend          | PHP 8.3                           |
| Serveur web      | Apache                            |
| API              | REST                              |
| Authentification | JWT                               |
| Base de données  | MySQL 8.4                         |
| Accès DB         | PDO                               |
| Conteneurisation | Docker                            |
| Orchestration    | Docker Compose                    |
| Architecture     | Repository / Service / Controller |
| Sécurité         | JWT + Middlewares + RBAC          |

---

# 👨‍💻 Auteur

**Adama Seck**

Master 1 — Systèmes d'Information

Projet académique de développement d'une plateforme de gestion de bibliothèque.

---

# 📄 Licence

Projet académique et pédagogique.

---

<p align="center">

**BiblioSphere — Moderniser la gestion de bibliothèque grâce aux technologies web modernes.**

</p>
```

---

# ⚠️ Mais je modifierais encore 3 choses

Ton README contient actuellement des informations provenant de **plusieurs étapes du développement**. Il faut donc éviter de conserver certaines informations historiques comme si elles décrivaient encore l'application finale.

Par exemple :

> `models/Book.php` ne sont plus utilisés

Ce genre de phrase est utile dans `V2 readme.md`, mais **pas dans la documentation finale**.

Même chose pour :

> « Je n'ai pas pu exécuter PHP dans mon environnement »

❌ À supprimer du README final.

Le README final doit parler de **ce que le projet est aujourd'hui**, pas de ce que l'IA n'arrivait pas à faire pendant son développement.

---

# ⭐ Et surtout : ton README peut devenir beaucoup plus impressionnant

Je te conseille même une page d'accueil comme ceci :

```text
                     📚 BIBLIOSPHERE

       Plateforme moderne de gestion de bibliothèque

 ┌──────────────────────────────────────────────────────┐
 │                                                      │
 │        📚 Catalogue                                  │
 │        👥 Utilisateurs                               │
 │        🔐 Authentification JWT                       │
 │        🔄 Emprunts                                   │
 │        📊 Dashboard                                  │
 │        🛡️ Gestion des rôles                          │
 │        🐳 Docker                                     │
 │                                                      │
 └──────────────────────────────────────────────────────┘

 PHP 8.3  •  MySQL 8.4  •  Docker  •  REST API  •  JWT
```

Puis immédiatement une **capture d'écran principale de l'application** :

```markdown
![BiblioSphere](docs/screenshots/dashboard.png)
```

Ensuite seulement la documentation.

Ça donnera beaucoup plus l'impression d'un **vrai produit logiciel** que d'un simple projet universitaire.

---

## 🔥 Et je te recommande une dernière chose

Vu que tu veux faire quelque chose de **"hyper méga complet et professionnel"**, ne te limite pas aux captures des pages.

Ajoute également :

* 📸 captures de toutes les interfaces importantes ;
* 🏗️ diagramme d'architecture ;
* 🗄️ diagramme de la base de données / ERD ;
* 🔄 diagramme du parcours d'un emprunt ;
* 🔐 schéma du processus JWT ;
* 🐳 architecture Docker ;
* 📡 documentation des endpoints API ;
* 🧪 exemples de requêtes/réponses JSON ;
* 📁 arbre du projet ;
* 📊 captures du dashboard ;
* 🛡️ démonstration du contrôle des rôles ;
* 🔒 démonstration de la protection contre le retour d'un emprunt appartenant à un autre membre ;
* 🎓 section dédiée aux compétences acquises.

**Là, tu obtiens une vraie documentation technique de niveau Master 1**, utilisable à la fois sur GitHub, pour une soutenance, dans ton portfolio et comme preuve de compétence en architecture logicielle.

Et avec tes fichiers actuels, **je peux aussi te faire une version encore plus poussée, basée sur le code réel du projet**, plutôt que de rester sur les informations présentes dans les anciens README : documentation de chaque dossier/classe, schéma exact des tables, toutes les routes réellement présentes, flux d'authentification, captures placées au bon endroit et README final cohérent avec **la V3 réellement installée**.
