# Workflow de tri des articles par l'IA (Gemini)

## Principe

1. **Collecte** : `veille:collect` lit les flux RSS actifs, dédoublonne et enregistre chaque nouvel article (inchangé).
2. **Analyse** : chaque article est envoyé à Gemini (titre, source, date et extrait du flux uniquement). Gemini répond dans un JSON imposé : `title_fr` (titre traduit en français, affiché sur le site avec le titre original en dessous), `relevant`, `score`, `reason`, `summary` (en français), `categories`, `criteria`.
3. **Classement** : le verdict est stocké dans `article_analyses.verdict` :
   - `relevant` → page **Articles pertinents** ;
   - `irrelevant` → page **Articles impertinents**, consultable pour repêcher un article, puis purgée après `irrelevant_retention_days` (30 jours par défaut).
4. **Décision personnelle** : indépendante du verdict. Marquer un article « Pertinent » l'ajoute à **Ma bibliothèque**, même s'il a été écarté par l'IA, et il n'est alors jamais purgé.

Le critère de pertinence se règle dans **Paramètres de veille → Tri par l'IA** (texte lu par Gemini avant chaque article) et avec les mots-clés prioritaires et exclus.

## Indisponibilité de l'IA : nouvelle tentative plus tard

| Situation | Comportement |
|---|---|
| Quota (HTTP 429), panne (5xx), réseau | Pause des analyses (délai indiqué par Google, sinon 60 s). L'article reste en attente, avec 3 essais dans la collecte. |
| Clé absente ou refusée, modèle inconnu (400/401/403/404) | Les articles restent en attente et la collecte se termine « avec erreurs ». |
| Collecte suivante | Reprend automatiquement jusqu'à 500 articles en attente ou en échec. |
| Réponse illisible ou bloquée | Échec propre à l'article : 3 essais, puis nouvelle tentative à la collecte suivante. |

Un limiteur local (`GEMINI_MAX_PER_MINUTE`, 10 par défaut) évite de dépasser le quota gratuit. L'historique indique quand l'analyse IA est en pause.

## Configuration (.env)

```
WATCH_ANALYZER=gemini        # ou local pour l'analyse par mots-clés
GEMINI_API_KEY=...           # https://aistudio.google.com/apikey
GEMINI_MODEL=gemini-3.5-flash-lite
GEMINI_MAX_PER_MINUTE=10
```

La clé est envoyée dans l'en-tête `x-goog-api-key`, jamais dans l'URL ni dans les journaux. Sur OVH, exécuter `php artisan config:cache` après chaque modification du `.env`.

## Commandes

- `php artisan migrate` : ajoute la colonne `verdict` et classe les analyses locales existantes selon le seuil actuel.
- `php artisan veille:reanalyze` : fait rejuger par Gemini les articles notés par l'analyse locale (`--all` pour tous, `--dry-run` pour compter).
- `php artisan veille:collect` : collecte et analyse un lot (appelée chaque minute par le scheduler).
- `php artisan veille:prune` : purge les anciens articles, dont les impertinents plus anciens que la durée configurée.

## Limites

- Gemini juge uniquement l'extrait fourni par le flux, pas l'article complet.
- Le verdict peut se tromper : la liste des impertinents sert à le vérifier. L'IA n'apprend pas des décisions personnelles.
- Avec l'offre gratuite de Google, les contenus envoyés peuvent servir à améliorer ses modèles. Seuls des extraits publics de flux RSS sont transmis.

## Tests

`tests/Feature/GeminiWorkflowTest.php` simule l'API avec `Http::fake` : tri pertinent/impertinent, repêchage, quota 429, clé absente puis reprise, réponse illisible, purge et réanalyse. Les autres tests utilisent `WATCH_ANALYZER=local` (voir phpunit.xml).
