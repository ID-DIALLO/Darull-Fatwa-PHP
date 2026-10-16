# Darul Fatwa — instructions pour Claude Code

Plateforme PHP/MySQL de questions-réponses islamiques (FR/AR/EN). Spécification complète :
`docs/CAHIER_DES_CHARGES.md` (à relire avant chaque phase). Fonction prioritaire : la question vocale.

## Commandes
- Installer : `composer install` puis `php bin/install.php`
- Lancer en local : `php -S localhost:8000 -t public public/index.php`
- Tests : `vendor/bin/phpunit`
- Tâches planifiées : `php cron/run.php`
- Migration : `php bin/migrate.php`

## Règles de code
- PHP 8.2+, `declare(strict_types=1);`, PSR-4 (`App\` → `app/`), PSR-12. Pas de framework.
- Base de données : PDO + requêtes préparées uniquement. Jamais de concaténation de variables dans le SQL.
  Toute modification du schéma = nouveau fichier dans `database/migrations/` + mise à jour de `schema.sql`.
- Sorties HTML : toujours `e()`. HTML riche : HTMLPurifier à l'enregistrement.
- Formulaires : `csrf_field()`, validation serveur avec `Validator`, messages traduits.
- Textes d'interface : uniquement via `__('fichier.cle')`. Toute nouvelle clé est ajoutée **en même temps**
  dans `lang/fr`, `lang/ar` et `lang/en`.
- CSS : propriétés logiques (`margin-inline-start`…) pour le RTL ; couleurs via les variables de `variables.css`.
- URL : routes nommées (`route('nom', [...])`), jamais d'URL écrite en dur.
- Questions : tout changement de statut passe par `QuestionWorkflow::transition()`.
- Actions sensibles : `ActivityLog::record()`.
- Audio : jamais dans `public/` ; servi par `MediaController` (contrôle d'accès + HTTP Range).
- Numéros de téléphone masqués pour les professeurs sauf canal WhatsApp choisi.
- Dépendance nouvelle : seulement si nécessaire, notée dans `docs/DECISIONS.md`.
- Commentaires métier en français ; noms de code (classes, variables, tables) en anglais.

## Définition de « terminé »
1. Fonctionne en FR, AR (RTL) et EN, sur mobile (360 px) et ordinateur.
2. Tests PHPUnit concernés écrits et au vert.
3. Aucune erreur PHP ni console navigateur.
4. Critères d'acceptation de la phase cochés dans `docs/CAHIER_DES_CHARGES.md`.
5. Commit clair en français.

## Interdits
- Représentation figurative des prophètes ou des Compagnons (images, illustrations).
- Publication d'une donnée personnelle ou de la voix d'un demandeur.
- Secrets dans le code ou dans Git (`.env` uniquement).
