# Mise en ligne sur un hébergement web OVHcloud

Procédure pour un hébergement web OVH (offres Perso, Pro, Performance) qui héberge déjà d'autres sites. Le site de veille est ajouté à côté, dans son propre dossier, sans toucher aux autres.

## 0. À savoir avant de commencer

| Point | Hébergement web OVH |
|---|---|
| PHP | 8.4 disponible (environnement `stable64`) |
| Base de données | MySQL fourni par OVH (SQLite n'est pas adapté en production) |
| SSH | À partir de l'offre **Pro** uniquement. En **Perso**, tout se fait par FTP et le cron fait le reste |
| Cron | **Une fois par heure maximum**, 60 minutes d'exécution maximum, la commande est le chemin d'un fichier PHP |

Le fichier `cron/veille.php` est prévu pour ces limites : appelé chaque heure, il applique les migrations, démarre la collecte quotidienne à l'heure choisie dans Paramètres de veille (6 h par défaut), puis enchaîne pendant 45 minutes au plus la lecture des flux, l'analyse Gemini et la recherche d'images. La purge est lancée une fois par jour.

## 1. Préparer les fichiers sur ton PC

Dans une **copie** du projet (pour garder tes outils de développement dans l'original) :

```
composer install --no-dev --optimize-autoloader
npm.cmd ci
npm.cmd run build
```

Génère une clé d'application **pour la production** et note-la :

```
php artisan key:generate --show
```

## 2. Créer la base MySQL

Espace client OVH › Hébergements › ton hébergement › **Bases de données** › Créer une base de données (MySQL). Note :
- le serveur (de la forme `xxxxx.mysql.db`) ;
- le nom de la base, l'utilisateur et le mot de passe.

## 3. Envoyer les fichiers par FTP

Avec FileZilla (identifiants dans l'onglet **FTP - SSH**), crée un dossier dédié, par exemple `www/veille/`, et envoie tout le projet **sauf** :

- `node_modules/`, `tests/`, `.git/`
- `.env` (il sera créé à l'étape 5)
- `database/database.sqlite`
- le contenu de `storage/logs/`

Garde bien `vendor/`, `public/build/`, `public/images/`, `cron/` et les dossiers `storage/` et `bootstrap/cache/` (même vides).

## 4. Déclarer le site (multisite) et le HTTPS

Hébergements › **Multisite** › Ajouter un domaine ou sous-domaine (par exemple `veille.ton-domaine.fr`) :
- **Dossier racine** : `veille/public`. Seul le dossier `public/` doit être visible depuis le web. Le code, le `.env` et le cron restent ainsi inaccessibles.
- Coche **SSL** pour obtenir le certificat Let's Encrypt.

### Version de PHP

Vérifie le fichier `.ovhconfig` à la racine FTP. Si tes autres sites fonctionnent en PHP 8.4, règle-le ainsi pour tout l'hébergement :

```
app.engine=php
app.engine.version=8.4
http.firewall=none
environment=production
container.image=stable64
```

Si tes autres sites ont besoin d'une version plus ancienne, laisse ce fichier tel quel. Place plutôt un `.ovhconfig` avec le contenu ci-dessus dans le dossier racine du site de veille (`www/veille/public/`), comme le permet OVH en multisite.

## 5. Créer le fichier `.env`

Envoie dans `www/veille/` un fichier `.env` (sans extension) :

```
APP_NAME="Veille SIO"
APP_ENV=production
APP_KEY=base64:...la clé générée à l'étape 1...
APP_DEBUG=false
APP_URL=https://veille.ton-domaine.fr

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=xxxxx.mysql.db
DB_PORT=3306
DB_DATABASE=nom_de_la_base
DB_USERNAME=utilisateur
DB_PASSWORD=mot_de_passe

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

WATCH_ANALYZER=gemini
GEMINI_API_KEY=ta_cle_gemini
GEMINI_MODEL=gemini-3.5-flash-lite
GEMINI_MAX_PER_MINUTE=10
WATCH_PREVIEW_IMAGES=true

MAIL_MAILER=log
```

Ne mets jamais ce fichier sur GitHub. Avec `APP_URL` en `https://`, le site génère automatiquement des liens HTTPS.

## 6. Déclarer la tâche planifiée

Hébergements › **Tâches planifiées - Cron** › Ajouter une planification :

| Champ | Valeur |
|---|---|
| Commande à exécuter | `www/veille/cron/veille.php` |
| Langage | PHP 8.4 |
| Activation | Activée |
| Fréquence | Toutes les heures |
| Alerte | Ton e-mail (recommandé) |

Au premier passage, le cron crée les tables et les 15 catégories, puis démarre la première collecte. Tu peux suivre son déroulement dans la page **Historique** du site et dans les journaux OVH (onglet Statistiques et logs).

## 7. Créer ton compte administrateur

- **Offre Pro ou supérieure (SSH)** : connecte-toi en SSH puis lance

  ```
  cd www/veille
  /usr/local/php8.4/bin/php artisan veille:create-admin
  ```

  Le mot de passe est demandé de façon masquée.
- **Offre Perso (sans SSH)** : la commande interactive n'est pas disponible. Il faut ajouter une méthode de création adaptée (voir avec le développement).

Avec SSH, tu peux aussi accélérer le site :

```
/usr/local/php8.4/bin/php artisan config:cache
/usr/local/php8.4/bin/php artisan route:cache
/usr/local/php8.4/bin/php artisan view:cache
```

Après une modification du `.env`, relance `config:cache`, sinon l'ancienne configuration reste utilisée. Sans SSH, ne mets pas la configuration en cache.

## 8. Ajouter les sources RSS

Connecte-toi, va dans **Sources RSS** et ajoute tes flux. La collecte suivante les prendra en compte. Pour ne pas attendre l'heure de la collecte automatique, clique sur **Lancer une collecte** dans l'Historique : le prochain passage du cron la traitera.

## Mettre à jour le site

1. Refaire l'étape 1 sur la nouvelle version.
2. Envoyer les fichiers modifiés par FTP (sans écraser `.env` ni `storage/`).
3. Les nouvelles migrations sont appliquées automatiquement au passage suivant du cron (ou tout de suite en SSH avec `php artisan migrate --force`).
4. Avec SSH : relancer `config:cache`, `route:cache` et `view:cache`.

## En cas de problème

- **Erreur 500** : lire `storage/logs/laravel-AAAA-MM-JJ.log` par FTP. Vérifier `APP_KEY`, les accès MySQL et que `storage/` et `bootstrap/cache/` sont accessibles en écriture.
- **Page sans style** : `public/build/` manquant (refaire `npm.cmd run build` et l'envoyer).
- **Articles « en attente » qui restent en attente** : clé Gemini absente ou quota atteint. La page Historique indique si l'analyse IA est en pause.
- **Le cron ne fait rien** : vérifier le chemin `www/veille/cron/veille.php`, la version PHP 8.4 et les journaux de la tâche. OVH désactive une tâche après 10 échecs consécutifs.
