# BIBLIOTHEQUE-API

TERMINAL 1 : php -S localhost:8002 -t public
TERMINAL 2 : python3 -m http.server 8082
NAVIGATEUR : http://localhost:8082/frontend/



Architecture générale
┌──────────────────────────────────────────────┐
│                  Utilisateur                 │
│                 (Navigateur)                 │
└─────────────────┬────────────────────────────┘
                  │
                  │ HTTP :8082
                  ▼
┌──────────────────────────────────────────────┐
│            Container Frontend                │
│                  Nginx                       │
│                                              │
│  - index.html                                │
│  - app.js                                    │
│  - style.css                                 │
└─────────────────┬────────────────────────────┘
                  │
                  │ Requêtes AJAX / Fetch
                  │ HTTP :8000/api/books
                  ▼
┌──────────────────────────────────────────────┐
│              Container API PHP               │
│            Apache + PHP 8.3 + PDO            │
│                                              │
│  Controllers                                 │
│  Models                                      │
│  Routes                                      │
│  Config                                      │
└─────────────────┬────────────────────────────┘
                  │
                  │ PDO MySQL
                  │ Port 3306
                  ▼
┌──────────────────────────────────────────────┐
│             Container MySQL 8.4             │
│                                              │
│  Base : bibliotheque                         │
│                                              │
│  Tables :                                    │
│    - books                                   │
│                                              │
│  Volume Docker : mysql_data                  │
└──────────────────────────────────────────────┘



Architecture Docker Compose

                    Docker Network
┌───────────────────────────────────────────────────────────┐
│                                                           │
│  ┌──────────────┐       ┌──────────────┐                  │
│  │  Frontend    │──────►│     API      │                  │
│  │    Nginx     │ HTTP  │ PHP-Apache   │                  │
│  │   Port 8082  │       │ Port 8000    │                  │
│  └──────────────┘       └──────┬───────┘                  │
│                                │                          │
│                                │ PDO                      │
│                                ▼                          │
│                        ┌──────────────┐                  │
│                        │    MySQL     │                  │
│                        │   Port 3306  │                  │
│                        └──────────────┘                  │
│                                                           │
└───────────────────────────────────────────────────────────┘



1. Utilisateur clique sur "Ajouter un livre"
          │
          ▼
2. app.js envoie POST /api/books
          │
          ▼
3. API PHP reçoit la requête
          │
          ▼
4. Controller → Model → MySQL
          │
          ▼
5. MySQL enregistre le livre
          │
          ▼
6. API retourne un JSON
          │
          ▼
7. Frontend met à jour l'interface



Voici une explication orale que tu peux utiliser pendant la soutenance :

---

Bonjour à tous,

Je vais vous présenter l'architecture de notre application de gestion de bibliothèque développée sous forme d'API REST et déployée avec Docker.

L'architecture repose sur trois conteneurs Docker principaux qui communiquent entre eux à travers un réseau Docker privé.

Tout d'abord, à gauche, nous avons l'utilisateur qui accède à l'application via son navigateur web. Celui-ci se connecte au conteneur Frontend exposé sur le port 8082.

Le conteneur Frontend est basé sur Nginx et contient l'ensemble des fichiers statiques de l'application : HTML, CSS et JavaScript. Son rôle est uniquement d'afficher l'interface utilisateur et d'envoyer les requêtes vers l'API.

Lorsqu'un utilisateur effectue une action, par exemple l'ajout d'un livre, le fichier JavaScript app.js envoie une requête HTTP vers le conteneur API situé au centre de l'architecture.

Le conteneur API est basé sur PHP 8.3 et Apache. Il contient toute la logique métier de l'application organisée selon une architecture MVC simplifiée :

* Les Routes reçoivent les requêtes HTTP.
* Les Controllers traitent les demandes des utilisateurs.
* Les Models interagissent avec la base de données.
* Le fichier de configuration assure la connexion à MySQL via PDO.

Après traitement, l'API renvoie une réponse au format JSON au frontend.

Ensuite, pour stocker les données, l'API communique avec le conteneur MySQL à travers le port 3306. Ce conteneur héberge la base de données bibliotheque qui contient notamment la table books utilisée pour enregistrer les informations des livres.

Afin de garantir la persistance des données, même si les conteneurs sont supprimés ou redémarrés, nous utilisons un volume Docker nommé mysql_data. Ce volume conserve les données indépendamment du cycle de vie des conteneurs.

Le diagramme du bas illustre le cycle complet d'une requête :

1. L'utilisateur remplit le formulaire.
2. Le frontend envoie une requête POST vers l'API.
3. L'API reçoit et valide les données.
4. Le modèle exécute une requête SQL via PDO.
5. MySQL enregistre le livre.
6. L'API retourne une réponse JSON.
7. Le frontend met à jour automatiquement l'interface.

Cette architecture présente plusieurs avantages :

* Une forte séparation des responsabilités entre l'interface, la logique métier et les données.
* Une meilleure portabilité grâce à Docker.
* Une maintenance simplifiée puisque chaque service est isolé dans son propre conteneur.
* Une évolutivité facilitée car chaque composant peut être mis à jour ou remplacé indépendamment.
* Une persistance des données grâce aux volumes Docker.

En résumé, Docker nous permet de disposer d'un environnement reproductible, portable et facilement déployable tout en respectant les bonnes pratiques d'architecture des applications modernes basées sur les API REST.

---

Cette présentation prend environ **2 à 3 minutes à l'oral**, ce qui est idéal pour commenter le schéma pendant une soutenance de Master 1.
