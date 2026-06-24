# 🗄️ Schéma V2 — Migration vers la plateforme complète

## Ce qui a changé par rapport au schéma V1

| V1 (actuel) | V2 (nouveau) |
|---|---|
| 1 table (`books`) | 5 tables (`users`, `categories`, `books`, `loans`, `activity_logs`) |
| `genre` en texte libre (VARCHAR) | `category_id` en clé étrangère vers `categories` |
| `available` = booléen sans historique | `available` + table `loans` = historique complet qui/quand/retour |
| Pas d'utilisateurs | `users` avec rôle `admin`/`membre`, mot de passe haché |
| Pas de traçabilité | `activity_logs` : qui a fait quoi, quand, depuis quelle IP |

## Pourquoi ces choix de conception

- **Exemplaire unique** (pas de table `copies`) : tu as confirmé que chaque livre est une copie physique unique. `available` reste donc un simple booléen sur `books`, mis à jour à chaque emprunt/retour. Si un jour tu veux plusieurs exemplaires du même titre, il faudra introduire une table `copies` intermédiaire — mais ce n'est pas nécessaire ici.
- **2 rôles via ENUM**, pas de table `roles` séparée : avec seulement `admin`/`membre` fixes, une table dédiée serait une couche de complexité sans bénéfice actuel. Si tu ajoutes un jour un rôle `bibliothécaire` avec des permissions différentes des deux autres, on migrera vers `roles` + `permissions`.
- **`activity_logs.user_id` nullable** : une tentative de connexion avec un email qui n'existe pas n'a pas d'utilisateur à associer, mais tu veux quand même la logger (utile pour détecter du brute-force plus tard).

## ⚠️ Avant d'exécuter ce script

Ce script commence par `DROP TABLE` sur l'ancienne structure — **toutes les données actuelles de `books` seront perdues** si tu l'exécutes directement sur ta base existante. Comme tu n'as que les 10 livres de test, ce n'est pas grave ici, mais c'est le réflexe à avoir avant toute migration : sauvegarder avant de droper.

```bash
# Sauvegarde de précaution (même si ce sont juste des données de test)
docker compose exec mysql mysqldump -u root -proot shop_db > backup_avant_v2.sql
```

## Comment appliquer le nouveau schéma

### Option A — reconstruire le conteneur MySQL à neuf (le plus simple)

```bash
# Remplace database/schema.sql par le nouveau fichier schema_v2.sql
# (renomme-le en schema.sql, ou pointe docker-compose.yml vers le nouveau nom)

docker compose down -v        # supprime le volume MySQL existant
docker compose up --build     # le script s'exécute automatiquement au premier démarrage
```

### Option B — appliquer à chaud sans tout reconstruire

```bash
docker compose exec -T mysql mysql -u root -proot shop_db < database/schema_v2.sql
```

## ⚠️ Étape obligatoire : générer les vrais mots de passe

Le fichier contient des placeholders `À_REMPLACER_PAR_LE_HASH_DE_...` à la place de vrais hash bcrypt — je n'avais pas d'interpréteur PHP disponible pour les générer et vérifier moi-même, et je préfère te laisser le faire correctement plutôt que de te donner un hash non vérifié qui casserait silencieusement l'authentification.

```bash
# Génère le hash pour le compte admin (mot de passe : admin123)
docker compose exec api php -r "echo password_hash('admin123', PASSWORD_BCRYPT), PHP_EOL;"

# Génère le hash pour les comptes membres (mot de passe : membre123)
docker compose exec api php -r "echo password_hash('membre123', PASSWORD_BCRYPT), PHP_EOL;"
```

Copie chaque résultat (une chaîne commençant par `$2y$10$...`) dans `database/schema_v2.sql`, à la place du placeholder correspondant, **avant** d'exécuter le script. Ou, plus simple : exécute le script tel quel d'abord, puis mets à jour les utilisateurs ensuite :

```sql
UPDATE users SET password_hash = '$2y$10$...(ton hash admin)...' WHERE email = 'admin@bibliotheque.local';
UPDATE users SET password_hash = '$2y$10$...(ton hash membre)...' WHERE role = 'membre';
```

## Vérifier que tout est en place

```bash
docker compose exec mysql mysql -u root -proot shop_db -e "
SELECT 'users' AS table_name, COUNT(*) AS rows FROM users
UNION SELECT 'categories', COUNT(*) FROM categories
UNION SELECT 'books', COUNT(*) FROM books
UNION SELECT 'loans', COUNT(*) FROM loans
UNION SELECT 'activity_logs', COUNT(*) FROM activity_logs;
"
```

Tu dois voir : `users=3`, `categories=5`, `books=10`, `loans=4`, `activity_logs=5`.

## Prochaine étape

Une fois cette base validée, on attaque le backend : nouveaux modèles (`User.php`, `Category.php`, `Loan.php`), le repository pattern, puis l'authentification JWT qui s'appuie directement sur la table `users` qu'on vient de créer. C'est cette table qui débloque tout le reste — dashboard avec stats par utilisateur, historique d'emprunts, rôles admin/membre dans les contrôleurs.