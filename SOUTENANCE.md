# PHASE 11 — Guide de Soutenance
# Projet SOA : API REST Bibliothèque — PHP/MySQL
# Master 1 Systèmes d'Information

================================================================
  PLAN DE PRÉSENTATION (15-20 minutes)
================================================================

SLIDE 1 — TITRE (1 min)
  • Titre : "Développement d'une API REST — Gestion de Bibliothèque"
  • Votre nom, filière, année
  • Photo de l'interface frontend

SLIDE 2 — PROBLÉMATIQUE (2 min)
  • "Comment exposer des données structurées de manière standard,
    accessible depuis n'importe quel client ?"
  • Introduire SOA et REST comme réponse
  • Montrer que votre API peut être consommée par un navigateur,
    une appli mobile, Postman…

SLIDE 3 — ARCHITECTURE GLOBALE (2 min)
  • Schéma en couches :
    Client → Router → Controller → Model → MySQL
  • Expliquer chaque flèche = une responsabilité isolée
  • Insister sur la séparation des préoccupations (SoC)

SLIDE 4 — BASE DE DONNÉES (2 min)
  • Table books : montrer les colonnes + types
  • Justifier : pourquoi TINYINT(1) pour available ?
    Pourquoi utf8mb4 ? Pourquoi UNSIGNED ?

SLIDE 5 — DÉMONSTRATION LIVE (5 min)
  • Ouvrir Postman ou le testeur intégré dans votre frontend
  • Dérouler les 5 opérations en live :
    1. GET /api/books          → liste les livres
    2. POST /api/books         → créer "Mon Livre de Test"
    3. GET /api/books/{newId}  → vérifier la création
    4. PUT /api/books/{newId}  → modifier le titre
    5. DELETE /api/books/{newId} → supprimer

SLIDE 6 — SÉCURITÉ & BONNES PRATIQUES (2 min)
  • Requêtes préparées PDO → anti-injection SQL
  • Échappement HTML côté frontend → anti-XSS
  • Validation entrées : côté JS (UX) ET côté PHP (sécurité)
  • Codes HTTP appropriés (ne pas tout mettre en 200)

SLIDE 7 — DIFFICULTÉS & SOLUTIONS (1 min)
  • CORS : problème et solution (headers Access-Control-Allow-*)
  • php://input pour lire le JSON des PUT/DELETE
  • Routage sans framework : regex + switch

SLIDE 8 — CONCLUSION & PERSPECTIVES (1 min)
  • Ce que vous avez maîtrisé
  • Extensions possibles : JWT auth, pagination, rate limiting,
    versioning d'API (/api/v1/books), déploiement Docker

================================================================
  50 QUESTIONS POSSIBLES + RÉPONSES DÉTAILLÉES
================================================================

---[ GROUPE 1 : REST & SOA ]---

Q1. Quelle est la différence entre SOA et REST ?
R: SOA est une approche architecturale générale pour structurer
   des applications en services interopérables. REST est un style
   d'architecture spécifique pour les API web, basé sur les
   principes HTTP. REST peut être considéré comme une implémentation
   légère de SOA. SOA utilise souvent SOAP/WSDL, REST utilise HTTP/JSON.

Q2. Citez les 6 contraintes de l'architecture REST.
R: 1) Client-Serveur : séparation frontend/backend
   2) Stateless : pas de session côté serveur, chaque requête est
      autonome (toutes les infos nécessaires sont dans la requête)
   3) Cacheable : les réponses peuvent être mises en cache
   4) Interface uniforme : verbes HTTP standardisés, URIs logiques
   5) Système en couches : proxy, load balancer transparents
   6) Code-on-demand (optionnel) : envoi de code exécutable

Q3. Qu'est-ce que le principe "stateless" et comment le respectez-vous ?
R: Stateless signifie que le serveur ne garde aucune information
   entre deux requêtes. Dans mon projet, il n'y a pas de session PHP,
   pas de cookie de session. Chaque requête contient toutes les
   informations nécessaires (ID du livre dans l'URL, données dans
   le body JSON). Le serveur traite chaque requête indépendamment.

Q4. Quelle est la différence entre PUT et PATCH ?
R: PUT = remplacement complet de la ressource. Le client envoie
   TOUS les champs, même ceux qui ne changent pas.
   PATCH = modification partielle. Le client n'envoie que les champs
   à modifier. Dans mon projet, j'utilise PUT car c'est plus simple
   et adapté à un formulaire qui envoie toujours tous les champs.

Q5. Pourquoi utiliser JSON plutôt que XML ?
R: JSON est plus léger (moins de verbosité que XML), plus facile à
   parser en JavaScript (JSON.parse natif), meilleure lisibilité
   humaine. XML reste pertinent pour les systèmes legacy ou SOAP.
   Pour une API REST moderne, JSON est le standard de facto.

Q6. Qu'est-ce qu'une ressource en REST ?
R: Une ressource est toute entité exposée par l'API, identifiée
   par une URI unique. Dans mon projet, la ressource "livre" est
   accessible via /api/books. L'URI identifie "quoi", les méthodes
   HTTP indiquent "quelle action". Une ressource peut être un objet,
   une collection, un service.

Q7. Comment versionnez-vous une API REST ?
R: Plusieurs approches : URI versioning (/api/v1/books),
   header versioning (Accept: application/vnd.api+json;version=1),
   query parameter (?version=1). URI versioning est le plus courant
   car visible, cacheable et facile à tester. Dans mon projet, je
   pourrais ajouter /v1/ pour permettre une future version sans
   casser l'existant.

---[ GROUPE 2 : PHP & PDO ]---

Q8. Pourquoi PDO plutôt que mysqli ?
R: PDO est agnostique au SGBD : le même code fonctionne avec MySQL,
   PostgreSQL, SQLite. mysqli est spécifique à MySQL. PDO a une
   API objet plus moderne. Les deux supportent les requêtes préparées.
   En entreprise, PDO facilite les migrations de SGBD.

Q9. Qu'est-ce qu'une injection SQL et comment l'évitez-vous ?
R: Une injection SQL consiste à insérer du code SQL malveillant dans
   une entrée utilisateur. Exemple :
   SELECT * FROM books WHERE id = '1; DROP TABLE books;--'
   J'évite ça avec les requêtes préparées PDO : bindParam() lie la
   valeur comme donnée, pas comme code SQL. MySQL reçoit deux
   entités séparées et n'exécutera jamais le DROP TABLE.

Q10. Expliquez ATTR_EMULATE_PREPARES => false.
R: Par défaut, PDO simule les requêtes préparées côté PHP (émulation).
   Avec false, PDO utilise les vraies requêtes préparées MySQL.
   Avantage : protection réelle contre certaines injections que
   l'émulation pourrait laisser passer (notamment avec certains
   encodages). Légèrement plus lent mais plus sûr.

Q11. Qu'est-ce que le pattern Singleton ?
R: Le Singleton garantit qu'une classe n'a qu'une seule instance.
   Dans ma classe Database, si $this->conn n'est pas null, on réutilise
   la connexion existante. Cela évite d'ouvrir une nouvelle connexion
   MySQL à chaque appel, ce qui consommerait des ressources.

Q12. Pourquoi utiliser php://input plutôt que $_POST ?
R: $_POST ne parse que le Content-Type application/x-www-form-urlencoded
   et multipart/form-data. Quand un client REST envoie du JSON
   (Content-Type: application/json), PHP ne le parse pas dans $_POST.
   php://input est le flux brut de la requête, accessible pour tout
   Content-Type. On le décode ensuite avec json_decode().

Q13. Qu'est-ce que le DSN PDO ?
R: Data Source Name = chaîne d'identification de la base.
   Format : "driver:clé=valeur;clé=valeur"
   Exemple : "mysql:host=localhost;dbname=bibliotheque;charset=utf8mb4"
   Il dit à PDO : utilise le driver MySQL, connecte à localhost,
   base "bibliotheque", avec le charset utf8mb4.

Q14. Pourquoi JSON_UNESCAPED_UNICODE dans json_encode() ?
R: Par défaut, json_encode() échappe les caractères non-ASCII en
   séquences \uXXXX (ex: "é" devient "\u00e9"). Avec
   JSON_UNESCAPED_UNICODE, les accents restent tels quels. Le JSON
   est plus lisible et moins volumineux. Requis quand on expose des
   données en français ou tout autre langue avec accents.

---[ GROUPE 3 : MySQL ]---

Q15. Pourquoi ENGINE=InnoDB ?
R: InnoDB supporte les transactions ACID, les clés étrangères et
   le verrouillage au niveau ligne (meilleure concurrence que
   MyISAM qui verrouille la table entière). C'est le moteur par
   défaut MySQL 5.5+ et le choix standard pour les applications
   transactionnelles.

Q16. Qu'est-ce que utf8mb4 et pourquoi pas utf8 ?
R: L'encodage "utf8" de MySQL n'est en réalité qu'un sous-ensemble
   UTF-8 sur 3 octets max. Il ne supporte pas les caractères sur
   4 octets comme les emojis (U+10000+). utf8mb4 est le vrai UTF-8
   complet. Depuis MySQL 8, utf8mb4 est le charset par défaut.

Q17. Pourquoi UNSIGNED pour l'ID ?
R: Un INT signé va de -2 milliards à +2 milliards. Un ID ne peut
   pas être négatif, donc on "perd" la moitié de la plage. UNSIGNED
   (0 à +4 milliards) double la capacité sans coût mémoire.

Q18. Qu'est-ce que CURRENT_TIMESTAMP comme valeur par défaut ?
R: MySQL insère automatiquement la date et l'heure courantes lors
   d'un INSERT si aucune valeur n'est fournie pour created_at. Cela
   évite de gérer la date côté PHP. Le fuseau horaire utilisé est
   celui configuré dans MySQL (system_time_zone).

Q19. Quelle est la différence entre CHAR et VARCHAR ?
R: CHAR(n) alloue toujours n caractères (espace fixe, rapide pour
   des valeurs de longueur constante). VARCHAR(n) alloue l'espace
   nécessaire + 1-2 octets de longueur (plus économe pour les
   valeurs de longueur variable). Pour title et author, VARCHAR est
   approprié car les longueurs varient.

---[ GROUPE 4 : Architecture & Code ]---

Q20. Pourquoi séparer Controller et Model ?
R: Principe de séparation des préoccupations (SoC). Le Model ne
   sait rien du HTTP (pas de header, pas de codes de statut). Le
   Controller ne sait rien de SQL. Chaque couche a une responsabilité
   unique. Si on change de SGBD, seul le Model change. Si on change
   le format de réponse, seul le Controller change.

Q21. Expliquez le rôle du fichier .htaccess.
R: Sans .htaccess, Apache rechercherait des fichiers physiques
   correspondant aux URIs (/api/books → fichier "api/books" inexistant).
   RewriteEngine On active la réécriture. RewriteCond !-f !-d exclut
   les vrais fichiers. RewriteRule ^(.*)$ index.php [QSA,L] redirige
   tout vers index.php qui dispatche via le routeur.

Q22. Pourquoi un point d'entrée unique (index.php) ?
R: Pattern Front Controller : toutes les requêtes passent par un
   seul point d'entrée. Avantages : une seule place pour les
   inclusions, l'initialisation globale, la gestion des erreurs,
   la sécurité. Sans ça, chaque endpoint aurait ses propres includes.

Q23. Comment votre routeur gère-t-il les paramètres d'URL ?
R: Via preg_match() avec des groupes de capture :
   preg_match('#^/api/books/(\d+)$#', $uri, $matches)
   \d+ capture un ou plusieurs chiffres dans $matches[1].
   $matches[0] = URI complète, $matches[1] = l'ID capturé.

Q24. Quelle est la différence entre 400, 404, 422 et 500 ?
R: 400 Bad Request = requête syntaxiquement incorrecte (JSON invalide)
   404 Not Found = ressource demandée inexistante (ID inconnu)
   422 Unprocessable = requête bien formée mais données invalides
       (champ manquant, type incorrect)
   500 Internal Server Error = erreur serveur (PDOException)

Q25. Pourquoi valider les données côté PHP aussi si déjà validées en JS ?
R: La validation JavaScript est une aide à l'utilisateur (UX),
   mais peut être contournée (Postman, curl, modification du JS).
   La validation PHP est la vraie barrière de sécurité. Règle d'or :
   "Never trust user input." Toujours valider côté serveur.

---[ GROUPE 5 : Sécurité ]---

Q26. Qu'est-ce qu'une attaque XSS et comment la prévenez-vous ?
R: Cross-Site Scripting = injection de code JavaScript malveillant
   dans les données affichées. Si je stocke "<script>alert(1)</script>"
   comme titre et l'affiche sans échappement, le script s'exécute.
   Ma fonction escapeHtml() convertit < en &lt; > en &gt; etc.,
   neutralisant tout code injecté.

Q27. Pourquoi ne pas afficher le message d'erreur PDO en production ?
R: Les messages d'erreur PDO révèlent la structure de la base, les
   noms de tables, le type de SGBD... informations précieuses pour
   un attaquant. En développement, on les affiche pour déboguer.
   En production : log en fichier (error_log), réponse générique
   "Erreur serveur" sans détails.

Q28. Qu'est-ce que CORS et pourquoi est-ce nécessaire ?
R: La politique Same-Origin du navigateur bloque les requêtes AJAX
   vers un domaine/port différent de la page. CORS (Cross-Origin
   Resource Sharing) définit des headers HTTP pour autoriser
   explicitement certaines origines. Access-Control-Allow-Origin: *
   autorise tout le monde. En production : restreindre à
   l'URL exacte du frontend.

Q29. Comment sécuriseriez-vous votre API avec une authentification ?
R: JWT (JSON Web Tokens) est la solution moderne pour les API REST.
   Le client s'authentifie (POST /api/auth/login), reçoit un token JWT
   signé. Il l'envoie ensuite dans le header Authorization: Bearer {token}
   à chaque requête protégée. Le serveur vérifie la signature du token
   sans base de données (stateless). Alternatives : OAuth 2.0, API keys.

---[ GROUPE 6 : JavaScript / Frontend ]---

Q30. Expliquez le mot-clé await dans votre JavaScript.
R: await suspend l'exécution de la fonction async jusqu'à ce que la
   Promise soit résolue. fetch() retourne une Promise (opération
   asynchrone réseau). Sans await, on continuerait avant d'avoir
   la réponse. Avec await, le code reste séquentiel et lisible.
   Nécessite que la fonction soit déclarée async.

Q31. Pourquoi utiliser fetch() plutôt que XMLHttpRequest ?
R: fetch() est l'API moderne (ES6+), basée sur les Promises.
   Code plus lisible, support natif async/await. XMLHttpRequest est
   l'ancienne API, callback-based, plus verbeux. Les deux font la
   même chose, mais fetch est le standard recommandé aujourd'hui.

Q32. Comment fonctionne URLSearchParams dans votre code ?
R: URLSearchParams construit la query string proprement :
   params.set('search', 'Hugo') → "search=Hugo"
   params.set('genre', 'Roman') → "search=Hugo&genre=Roman"
   Il encode automatiquement les caractères spéciaux (%20 pour
   espace, etc.). Plus sûr et lisible que la concaténation de chaînes.

Q33. Qu'est-ce que le Pattern Observer implicite dans votre app.js ?
R: Chaque action (création, modification, suppression) appelle
   loadBooks() pour recharger la liste. C'est un pattern simple
   de synchronisation : la vue se re-rend depuis la source de vérité
   (l'API). Une architecture plus avancée utiliserait un store
   réactif (Redux, Vuex) pour éviter les rechargements complets.

================================================================
  CONSEILS POUR LA SOUTENANCE
================================================================

AVANT :
  □ Tester l'application 30 min avant (serveur PHP démarré ?)
  □ Ouvrir Postman ou le testeur intégré en avance
  □ Avoir la base de données avec les données de test
  □ Préparer un PDF de présentation en backup si slides KO

PENDANT :
  □ Commencer par la DÉMO (accrocher le jury) avant les explications
  □ Expliquer chaque action que vous faites en live
  □ Si une question est trop précise : "Je n'ai pas eu le temps d'implémenter
    cela dans ce projet, mais voici comment je l'aborderais..."
  □ Montrer le code source quand vous parlez d'une notion technique

RÉPONSES AUX QUESTIONS :
  □ Reformuler la question avant de répondre (montre que vous avez compris)
  □ Utiliser des exemples concrets de VOTRE code
  □ Si vous ne savez pas : "Je ne me souviens pas exactement, mais
    logiquement je dirais que..." vaut mieux que le silence
  □ Ne pas inventer — les jurys savent quand on improvise

POINTS À VALORISER :
  □ "J'ai choisi de ne pas utiliser de framework pour bien comprendre
    les mécanismes sous-jacents" → initiative pédagogique
  □ "J'ai géré les erreurs à chaque niveau" → robustesse
  □ "La validation est faite côté JS ET côté PHP" → sécurité
  □ "Le frontend et l'API sont totalement découplés" → SOA réel
