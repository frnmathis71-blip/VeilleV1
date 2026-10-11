# Plateforme de veille technologique — conception

État du 11 octobre 2026. Ce document prépare le développement ; les fonctionnalités décrites comme proposées ne sont pas encore implémentées.

## 1. Audit du projet existant

- Starter kit Laravel Livewire, dépôt Git initialement sans modifications locales.
- Versions installées : Laravel 13.35.0, PHP CLI 8.4.25, Livewire 4.4.7, Flux 2.20.1, Fortify 1.41.0, Tailwind CSS 4.3.3. Composer autorise actuellement PHP `^8.3` ; aligner cette contrainte sur PHP 8.4 lors des fondations.
- Blade, composants Livewire dans les vues, Vite et Flux sont déjà en place. Conserver ces conventions et composants.
- SQLite en développement ; sessions, cache et queue configurés sur base de données. Tables techniques de queue et cache déjà prévues par les migrations.
- Fortify fournit connexion, limitation à cinq tentatives par minute par adresse e-mail/IP, réinitialisation du mot de passe, vérification d'e-mail, double authentification et passkeys.
- L'inscription publique est activée : la désactiver côté serveur et retirer ses liens. La page `/` affiche encore l'accueil Laravel ; la rediriger vers la connexion ou le tableau de bord selon la session.
- Tableau de bord protégé par `auth` et `verified`, mais composé uniquement de placeholders. Réglages de profil, sécurité et apparence existants.
- Modèle métier existant : `User` uniquement. Aucun collecteur, moteur d'analyse, bibliothèque, export ou historique métier.
- Seeder actuel créant un compte de démonstration : à remplacer par les référentiels métier, sans compte ni articles fictifs.
- Application actuellement en anglais, fuseau UTC et nom Laravel. Conserver les dates techniques en UTC, afficher en Europe/Paris et traduire les interfaces en français.
- Tests Pest, Pint, Larastan et workflow GitHub Actions avec PHP 8.4 déjà présents.
- Vérification initiale : `php artisan test` réussit, 34 tests et 83 assertions. Pint, Larastan, build frontend et fonctionnement MySQL n'ont pas été exécutés pendant cet audit.
- Aucun parseur RSS ni moteur PDF identifié dans les dépendances directes. Le choix et la compatibilité des bibliothèques seront vérifiés dans les phases concernées.

## 2. Architecture applicative proposée

Application privée à catalogue partagé et suivi personnel par utilisateur. Tous les comptes sont créés par commande, aucun accès public aux données de veille. Prévoir `is_admin` et une autorisation dédiée pour sources, paramètres globaux et lancements de collecte ; les décisions et exports restent propres à chaque utilisateur.

| Couche | Responsabilité |
|---|---|
| Modèles Eloquent | Relations, casts, scopes de recherche et contraintes métier |
| Composants Livewire + Blade/Flux | Navigation, filtres, formulaires, décisions, progression |
| Contrôleurs | Téléchargement des exports et points d'entrée HTTP qui le justifient |
| Services de collecte | Client HTTP sécurisé, parsing, normalisation, déduplication |
| Services d'analyse | Contrat `ArticleAnalyzer`, moteur local, adaptateur IA ultérieur |
| Jobs | Unités courtes, idempotentes, réessayables avec limites |
| Commandes Artisan | Création administrateur, lancement, traitement par lots, maintenance |
| Scheduler | Déclenchement des commandes sans logique métier dupliquée |

Les actions Livewire valident les entrées et les autorisations puis appellent les services. Le client HTTP sécurisé est l'unique voie de récupération des flux. L'interface ne lance pas une collecte longue pendant une requête web : elle crée une demande persistée, traitée par le prochain passage cron ou un worker disponible.

## 3. Base de données proposée

Clés primaires numériques, clés étrangères, dates UTC et timestamps Laravel. Les états utilisent des enums PHP adossés à des chaînes pour conserver la portabilité SQLite/MySQL. Les champs JSON contiennent des configurations ou diagnostics, jamais des relations à interroger normalement.

| Table | Champs principaux et contraintes |
|---|---|
| `users` existante | Ajouter `is_admin` ; conserver sécurité Fortify et index unique e-mail |
| `rss_sources` | `name`, `url` texte, `url_hash` unique SHA-256, `active`, `language`, `reliability` 0–100, `last_attempt_at`, `last_success_at`, `last_error`, `etag`, `last_modified`, `next_fetch_at` |
| `articles` | `rss_source_id`, `title`, `url`, `canonical_url_hash` unique, `guid` nullable, `guid_hash` nullable, `content_hash` nullable indexé, `author`, `description`, `available_content`, `image_url`, `language`, `published_at` nullable, `collected_at`, `analysis_status`, `analysis_error`, `event_cluster_id` nullable en phase 6 |
| `categories` | `name`, `slug` unique, `description`, `keywords` JSON, `is_monitored` |
| `article_category` | Clé composite unique `(article_id, category_id)` et clés étrangères |
| `article_analyses` | `article_id` unique, `score` 0–100, `summary`, `summary_language`, `explanation`, `criteria_scores` JSON, `method`, `rules_version`, `configuration_hash`, `analyzed_at` |
| `article_reviews` | `(article_id, user_id)` unique, `decision` nullable (`relevant`, `rejected`), `decided_at`, `read_at`, `saved_for_later_at`, `personal_note` |
| `collection_runs` | `status`, `trigger`, `requested_by` nullable, `started_at`, `finished_at`, `heartbeat_at`, `configuration_snapshot`, compteurs et durée |
| `collection_run_sources` | `(collection_run_id, rss_source_id)` unique, état, tentatives, dates, compteurs, erreur ; reprise et diagnostic par flux |
| `collection_run_items` | `(collection_run_id, article_id)` unique, état d'analyse, tentatives, dernière erreur, échéance de reprise ; suivi de chaque article jusqu'à une issue terminale |
| `settings` | `key` unique, `value` JSON ; schéma de validation centralisé dans un service typé |
| `event_clusters` en phase 6 | Sujet normalisé, méthode et date de regroupement ; regroupement heuristique explicite |

### Relations et conservation

- Une source possède plusieurs articles ; un article possède une analyse courante et plusieurs catégories.
- Un utilisateur possède plusieurs suivis d'articles. La lecture, les notes et la pertinence ne sont pas globales.
- Une collecte possède plusieurs suivis de sources et d'articles. Les compteurs d'exécution sont des agrégats techniques, pas des statistiques personnelles.
- Pas de colonne `is_interesting` redondante : sélectionner une analyse réussie dont le score dépasse le seuil configuré. Une modification du seuil reclasse immédiatement le catalogue. Les rapports historiques gardent le seuil de l'exécution.
- Distinguer explicitement `analysis_status` (`pending`, `processing`, `completed`, `failed`) et décision humaine. Une erreur d'analyse n'équivaut jamais à un score nul.
- Effacer une décision remet `decision` et `decided_at` à null sans supprimer la note ni la lecture. Retirer de la bibliothèque correspond à cette opération ; le marque-page « à lire plus tard » est indépendant.
- Suppression d'une source : archivage logique pour préserver les articles et leur attribution. Catégories et pivots ont des règles de suppression explicites.
- La purge exclut les articles validés, annotés ou conservés pour plus tard par au moins un utilisateur. La durée de conservation des journaux est distincte de celle des articles.

### Index et déduplication

- Index articles sur `(collected_at, id)`, `(published_at, id)`, `(rss_source_id, published_at)`, `(analysis_status, id)` ; analyses sur `(score, article_id)` ; suivis sur `(user_id, decision, decided_at)` et `(user_id, read_at)`.
- GUID unique dans une source via `(rss_source_id, guid_hash)` ; ne jamais considérer un GUID comme mondialement unique.
- URL normalisée puis empreinte unique : retirer seulement les paramètres de suivi connus, conserver les paramètres fonctionnels. Pas de récupération de la page éditeur nécessaire à cette normalisation.
- Empreinte de contenu sur un texte suffisamment long et normalisé. Ignorer les descriptions vides et les courts textes génériques. Vérifier les candidats avant de fusionner ; index non unique pour ne pas perdre des articles distincts.
- Les articles couvrant la même actualité restent distincts et peuvent être regroupés ensuite ; similarité thématique et doublon exact sont deux traitements séparés.
- Contraintes SQL et transactions courtes protègent les insertions concurrentes. Aucun appel réseau dans une transaction longue.

## 4. Collecte, analyse et reprise

1. Créer une exécution `pending` avec photographie de la configuration et sources concernées.
2. Prendre un verrou partagé avec propriétaire, expiration et renouvellement ; passer à `running`.
3. Traiter un nombre borné de flux et d'articles avec budget de temps, taille maximale des réponses, délais réseau et débit par domaine.
4. Enregistrer les nouveaux articles et leurs unités d'analyse de façon transactionnelle. La base permet de retrouver les unités restées en attente après une interruption.
5. Analyser les articles en attente et enregistrer score détaillé, catégories et version des règles.
6. Terminer uniquement lorsque les sources et unités d'analyse sont toutes dans un état terminal. Statuts : `completed`, `completed_with_errors`, `failed`. Une unité en attente ou en nouvelle tentative interdit la clôture.
7. Reprendre les réservations périmées après interruption ; nombre de tentatives limité et temporisation progressive. Une nouvelle tentative ne double pas les compteurs.

Un lot interrompu reste reprenable. Le verrou interdit aussi le chevauchement entre demande manuelle et planifiée. Les jobs utilisent la même logique que les commandes ; le fonctionnement de base ne nécessite aucun worker permanent.

### Analyse locale transparente

Proposition initiale de poids, total 100 : nouveauté 25, importance 15, impact 15, utilité informatique 20, originalité 5, fiabilité de source 10, actualité 10. Score final = somme des sous-scores normalisés pondérés. Les règles et signaux déclencheurs sont conservés pour expliquer le résultat. Les critères qualitatifs sont des estimations par règles, pas une compréhension humaine garantie.

Les catégories, mots-clés prioritaires et exclusions sont configurables. Les valeurs absentes, notamment la date, doivent être signalées et ne pas produire une fausse certitude. L'originalité sera un indicateur limité à la similarité avec le corpus collecté.

Sans IA, produire un extrait du contenu fourni, sans invention. Un moteur local à mots-clés ne peut pas garantir un résumé français d'un texte anglais : afficher une indication française et l'extrait dans sa langue d'origine. Une traduction réelle demandera un moteur dédié ou l'adaptateur IA. Le fournisseur IA reste optionnel, avec secrets en environnement, quotas, timeouts, validation de sortie et repli local.

## 5. Sécurité et déploiement

- Commande de création administrateur avec mot de passe saisi de manière masquée, confirmation et validation ; aucun secret en argument ou dans les logs.
- Désactiver réellement les routes d'inscription. Conserver protection CSRF, hachage, limitation des connexions et sécurité de session Fortify.
- Policies sur les opérations globales et contrôle systématique de l'utilisateur propriétaire des décisions, notes et exports.
- Récupération RSS : HTTP(S) uniquement, pas d'identifiants dans les URL, ports autorisés, rejet des IP privées/locales/réservées IPv4 et IPv6 ; contrôle DNS et connexion à une adresse validée pour éviter le DNS rebinding. Désactiver les redirections ou revalider chaque saut avec les mêmes contraintes.
- Parseur sans résolution d'entités XML externes, taille et nombre d'entrées limités. HTML externe transformé en texte ou nettoyé selon une liste restrictive ; Blade échappe la sortie.
- Images distantes : protocole contrôlé ; pas de récupération arbitraire par le moteur PDF. Tout proxy éventuel utilise le même client sécurisé.
- Ne pas récupérer automatiquement les pages complètes des éditeurs. Conserver seulement le contenu permis par les flux et les conditions des sources, avec attribution et lien original.
- Déploiement OVH à vérifier sur l'offre réelle : version PHP CLI, extensions, commande cron et fréquence, mémoire, durée maximale et racine web `public`. Ne pas supposer qu'une exécution chaque minute ou un démon est disponible.
- Fournir une commande périodique autonome qui vérifie les échéances en base ; Laravel Scheduler utilisable si l'offre le permet. La précision de la fréquence dépend de celle du cron disponible.
- HTTPS, debug désactivé, cookies sécurisés, sauvegarde MySQL et secrets hors dépôt. Stockage UTC, calendrier quotidien et graphiques en Europe/Paris.

## 6. Plan d'implémentation et critères de réception

| Phase | Livraison | Vérification avant la suite |
|---|---|---|
| 1 — Fondations | Accès privé, commande administrateur, migrations et relations, référentiel des 15 catégories, interface française Flux/Blade avec accents bleus et thèmes clair/sombre | Inscription impossible, redirections correctes, commandes testées, migrations SQLite et MySQL, autorisations et relations vérifiées ; aucun chiffre fictif |
| 2 — Collecte RSS | CRUD des sources, test sécurisé d'un flux, parseur maintenu, collecte manuelle bornée et persistée, déduplication | Fixtures RSS/Atom, encodages, dates absentes, redirections, flux cassés, SSRF, doublons et concurrence ; vérification réseau datée avant ajout des sources initiales |
| 3 — Analyse | Contrat de moteur, règles locales, poids et seuils validés, explications et résultats persistants | Scores bornés et reproductibles, exclusions, catégories multiples, absence d'informations, erreurs et réanalyse explicite |
| 4 — Lecture | Dashboard sur vraies données, listes paginées, filtres, détail, lecture, décisions réversibles, notes et bibliothèque | Parcours collecte → analyse → validation → bibliothèque ; isolation entre comptes, filtres combinés, états vides, affichage mobile |
| 5 — Automatisation | Échéances configurables, commandes et scheduler, relances, historique détaillé, suivi Livewire | Interruption puis reprise, verrou expiré, erreurs partielles, compteurs exacts et absence de clôture prématurée ; essai cron réel |
| 6 — Fonctions avancées | Graphiques 30 jours, exports PDF par période, qualité des recommandations, regroupements et résumé quotidien ; adaptateur IA facultatif | Agrégats vérifiés sur dates limites, PDF français lisible sans appels réseau arbitraires, notes privées, quotas et repli IA |
| 7 — Finalisation | Audit sécurité/performance, documentation BTS, procédure OVH, sauvegarde/restauration | Suite Pest, Pint, Larastan, build frontend, tests MySQL et parcours navigateur ; preuve d'une collecte planifiée terminée |

Les tests accompagnent chaque phase ; la dernière phase consolide les contrôles. La navigation n'affiche des actions que lorsque celles-ci fonctionnent. Les fixtures restent exclusivement dans les tests.

### Mesures et graphiques

Définir les dates des indicateurs : collecte selon `collected_at`, analyse selon `analyzed_at`, validation selon `decided_at`. Le taux personnel de pertinence est `validés / (validés + rejetés)` ; afficher un état sans données si le dénominateur est nul. Mesurer séparément la qualité des recommandations sur les seuls articles évalués qui appartiennent à la sélection automatique considérée.

Les graphiques par catégorie précisent qu'un article peut appartenir à plusieurs catégories ; leur somme peut dépasser le nombre d'articles. Les statistiques actuelles des décisions ne constituent pas un journal des changements : ajouter un journal dédié seulement si l'on veut retracer chaque modification historique.

### Documentation BTS à enrichir pendant le développement

Contexte, cas d'utilisation, schéma relationnel, architecture MVC, collecte et scoring, sécurité, matrice de tests, limites techniques, incidents et choix de conception, procédure d'installation/déploiement, exploitation cron et sauvegardes. Décrire les limites de l'analyse locale et ne revendiquer aucun apprentissage automatique inexistant.
