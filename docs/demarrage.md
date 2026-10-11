# Démarrage — phase 1

## Fonctionnalités livrées

- Connexion privée avec Fortify, inscription publique désactivée, redirection de l'accueil.
- Création interactive d'administrateurs avec validation du mot de passe, hachage et adresse vérifiée par l'opérateur.
- Tables et modèles de veille, décisions personnelles séparées des analyses, autorisation `manage-watch` et policy de propriété des avis.
- Tableau de bord avec compteurs réels, état vide explicite, référentiel des 15 catégories.
- Interface française, accents bleus, thèmes clair/sombre/système dans les paramètres existants.

## Installation

Utiliser PHP 8.4, Composer et Node.js. À la première installation, configurer `.env` à partir de `.env.example`, générer la clé uniquement pour cette nouvelle installation, installer les dépendances et compiler les assets :

```sh
composer install
php artisan key:generate
npm ci
npm run build
php artisan migrate --seed
php artisan veille:create-admin
```

Pour un projet déjà configuré, conserver sa clé d'application et ses données. Ne pas utiliser `migrate:fresh` sur une base à conserver. Les catégories sont ajoutées de manière idempotente ; les réglages existants ne sont pas réinitialisés.

La commande administrateur demande le nom, l'adresse e-mail et deux saisies masquées du mot de passe. Il faut au moins 12 caractères, des minuscules, majuscules, chiffres et caractères spéciaux. Aucun mot de passe par défaut ni option en ligne de commande n'est fourni. Un e-mail déjà utilisé est refusé. Le rôle administrateur n'est pas assignable par remplissage de masse.

Avec Herd, ouvrir http://projectveillesio.test. Sinon lancer `php artisan serve`. Définir `APP_URL` sur l'URL réellement utilisée, notamment pour les liens envoyés par e-mail et les passkeys.

## Vérifications effectuées

- SQLite : migrations appliquées et catégories initialisées dans le projet local.
- Pest : 43 tests, 122 assertions réussies lors de la validation initiale de la phase.
- Larastan : aucune erreur ; Pint : formatage appliqué ; build frontend réussi.
- Contrôle visuel de la connexion française dans le navigateur Herd.
- Les tests couvrent notamment l'inscription interdite, les redirections, la commande administrateur, les contraintes d'unicité, les catégories idempotentes, les décisions par utilisateur et l'archivage d'une source.

MySQL et le rendu du tableau de bord sur plusieurs tailles d'écran restent à vérifier dans l'environnement cible. Aucun compte personnel n'a été créé automatiquement. La réinitialisation par e-mail nécessite un transport mail configuré ; le projet utilise encore le journal local.

## Suite prévue

Phase 2 : gestion et vérification des sources RSS, client HTTP protégé contre les SSRF, lecture RSS/Atom, déduplication et collecte manuelle persistée. Les tables de suivi sont présentes, mais ni la collecte ni le moteur d'analyse ne sont encore opérationnels. Les autres entrées de navigation seront ajoutées avec leurs fonctions réelles.
