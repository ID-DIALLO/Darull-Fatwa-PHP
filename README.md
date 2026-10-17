# Darul Fatwa – دار الفتوى

> **Réponses islamiques authentiques** · أجوبة إسلامية موثوقة · *Authentic Islamic Answers*

Plateforme web de questions-réponses islamiques, en **français, arabe (RTL) et anglais**, avec une priorité :
**poser sa question par message vocal**, simplement, depuis un téléphone.

- Spécification complète : [`docs/CAHIER_DES_CHARGES.md`](docs/CAHIER_DES_CHARGES.md)
- Décisions techniques : [`docs/DECISIONS.md`](docs/DECISIONS.md)
- Règles de développement : [`CLAUDE.md`](CLAUDE.md)

## Technologies

PHP 8.2+ (MVC maison, sans framework) · MySQL 8 / MariaDB 10.6+ · Bootstrap 5.3 (LTR + RTL) · JavaScript vanilla, sans étape de build.

## Installation locale (Mac, Windows ou Linux)

Prérequis : PHP 8.2+ avec les extensions `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `openssl`, `json` (plus `curl`, `zip` et `gd` recommandées), Composer, et MySQL 8 ou MariaDB 10.6+.

```bash
git clone https://github.com/id-diallo/darull-fatwa-php.git darul-fatwa
cd darul-fatwa
composer install
php bin/install.php        # crée .env, la base, les tables, les données initiales et le super-administrateur
php -S localhost:8000 -t public public/index.php
```

Ouvrez ensuite <http://localhost:8000> : vous êtes redirigé vers `/fr`, `/ar` ou `/en` selon la langue de votre navigateur.

> **Mac avec MAMP** (conseillé sur Mac Intel) : démarrez MAMP (bouton *Start*), puis dans `.env` :
> `DB_HOST=127.0.0.1`, `DB_PORT=8889`, `DB_USERNAME=root`, `DB_PASSWORD=root`.
> Pour utiliser le PHP de MAMP dans le Terminal :
> `echo "export PATH=\"$(ls -d /Applications/MAMP/bin/php/php8.* | tail -1)/bin:\$PATH\"" >> ~/.zprofile && source ~/.zprofile`

### Commandes utiles

| Commande | Rôle |
|---|---|
| `php bin/install.php` | Installation complète (questions interactives) |
| `php bin/install.php --fresh` | **Efface toutes les tables** puis réinstalle (confirmation demandée) |
| `php bin/create-admin.php [--role=admin]` | Crée un compte d'administration |
| `php bin/migrate.php [--status]` | Applique les migrations de `database/migrations/` |
| `vendor/bin/phpunit` | Lance les tests |
| `php cron/run.php` | Tâches planifiées (à lancer chaque minute par le cron du serveur) |

Mode maintenance : créez le fichier `storage/maintenance.flag` (supprimez-le pour rouvrir le site).

## Arborescence

```
app/        Config, Core (noyau MVC), Middleware, Controllers, Models, Services, Helpers, Views
lang/       fr/, ar/, en/ : textes de l'interface
public/     racine web : index.php, assets (css, js, img, fonts), vendor (Bootstrap…), uploads
storage/    hors racine web : audio, logs, cache, exports, sauvegardes
database/   schema.sql, seeds/, migrations/
bin/        install.php, create-admin.php, migrate.php
cron/       run.php
tests/      Unit/, Feature/
docs/       cahier des charges, décisions
```

## Logo et identité visuelle

Les logos se trouvent dans `public/assets/img/logo/` (SVG à utiliser sur le site, PNG et exports 4K pour l'impression et les réseaux sociaux). Les favicons sont dans `public/assets/img/favicons/`. Couleurs et polices : §8 du cahier des charges et `public/assets/css/variables.css`.

## Avancement

- [x] **Phase 0** — Initialisation et noyau
- [x] **Phase 1** — Authentification, comptes et rôles
- [x] **Phase 2** — Gabarit public, accueil, rubriques, pages, contact
- [x] **Phase 3** — Poser une question (vocal + texte) et Mon espace ⭐ *(test sur téléphones réels à faire en HTTPS)*
- [x] **Phase 4** — Espace professeur, réponses, workflow, WhatsApp niveau 1, notifications, cron
- [x] **Phase 5** — Back-office d'administration (tableau de bord, questions, utilisateurs, professeurs, contenus, paramètres chiffrés, journal, sauvegardes)
- [x] **Phase 6** — Blog multilingue, Q/R publiques (QAPage), recherche plein texte, favoris, partage, commentaires modérés
- [ ] Phases 7 à 9 — voir §17 du cahier des charges

---
Maître d'ouvrage : Idrissa Diallo — Bamako, Mali.
