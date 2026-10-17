# Guide de déploiement — Darul Fatwa

Ce guide couvre la mise en production sur un hébergement mutualisé (Hostinger, cPanel, Plesk…) ou un VPS,
conformément au §16.2 du cahier des charges. Deux chemins sont possibles :

- **A. Sans accès SSH** (gestionnaire de fichiers + phpMyAdmin) : paquet ZIP + fichier SQL + page `/setup`.
- **B. Avec SSH / Git** : `composer install`, `php bin/install.php`, `php bin/doctor.php`.

Dans les deux cas, lisez d'abord la section 1.

---

## 1. Avant de commencer

| Point | Valeur attendue |
|---|---|
| PHP | 8.2 ou 8.3, extensions `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `openssl`, `json` (+ `gd`, `zip`, `curl` recommandées) |
| Base de données | MySQL 8 ou MariaDB 10.6+, un utilisateur dédié avec tous les droits **sur cette base uniquement** |
| Domaine | HTTPS actif (certificat gratuit de l'hébergeur) — **indispensable pour le micro** |
| E-mail | Une adresse `contact@votre-domaine` (ou `no-reply@`) avec ses identifiants SMTP ; SPF/DKIM/DMARC configurés chez l'hébergeur |
| Cron | Possibilité de lancer `php …/cron/run.php` chaque minute (ou au minimum toutes les 5 minutes) |

Structure du projet une fois déployé (racine du site = dossier qui contient `app/`) :

```
racine/
├── .env               ← configuration (jamais dans Git)
├── .htaccess          ← redirige tout vers public/ si le domaine ne peut pas pointer directement dessus
├── app/  bin/  cron/  database/  lang/  storage/  vendor/
└── public/            ← la seule partie réellement exposée (index.php, assets, uploads)
```

**Idéal** : faire pointer le domaine sur `racine/public`. **Sinon** (Hostinger « public_html »), déposez toute la racine dans
`public_html/` : le `.htaccess` de la racine réécrit les requêtes vers `public/` et bloque `app/`, `storage/`, `vendor/`, `.env`.

---

## 2. Chemin A — sans SSH (Hostinger hPanel, cPanel…)

### 2.1 Récupérer le paquet

Deux possibilités :

1. **Onglet « Actions » du dépôt GitHub** → workflow « Paquet de déploiement » → artefact `darul-fatwa-deploiement`
   (contient `darul-fatwa-site.zip` avec `vendor/` et les ressources construites, et `darul_fatwa_install.sql`).
2. **Depuis un ordinateur avec PHP et Composer** :
   ```bash
   composer install --no-dev --optimize-autoloader
   php bin/build-assets.php
   php bin/export-sql.php
   zip -r darul-fatwa-site.zip . -x '.git/*' 'tests/*' '.env' 'storage/logs/*' 'storage/cache/*'
   ```

> Le ZIP « Code → Download ZIP » de GitHub **ne contient pas `vendor/`** : le site affiche alors
> « Dépendances manquantes : lancez composer install ». Utilisez toujours le paquet ci-dessus.

### 2.2 Envoyer les fichiers

1. hPanel → *Sites web* → votre domaine → *Gestionnaire de fichiers* → `public_html`.
2. Supprimez le contenu existant de `public_html` (ancienne tentative, fichiers par défaut).
3. *Téléverser* `darul-fatwa-site.zip` **dans** `public_html`, clic droit → *Extraire* en laissant la destination `public_html`
   (pas de sous-dossier). Vous devez voir directement `app/`, `public/`, `vendor/`, `.htaccess`, `composer.json`…
4. Supprimez le ZIP.
5. Vérifiez que `storage/` et ses sous-dossiers sont accessibles en écriture (droits `755` ou `775`).

### 2.3 Créer et importer la base

1. hPanel → *Bases de données* → *Bases de données MySQL* : créez une base, un utilisateur et son mot de passe.
   Notez les **noms complets** affichés (ex. `u123456789_darulfatwa`, `u123456789_admin`).
2. *Entrer dans phpMyAdmin* → sélectionnez la base (colonne de gauche) → onglet *Importer* →
   fichier `database/dist/darul_fatwa_install.sql` (présent dans le paquet) → *Importer*.
3. Résultat attendu : **48 tables** + `schema_migrations`. La base contient les rôles, rubriques, pages, catégories du forum,
   sections de l'accueil, réglages, bannières, versets du jour et trois articles en brouillon. **Aucun compte.**

### 2.4 Le fichier `.env`

Dans le gestionnaire de fichiers (afficher les fichiers cachés), créez `.env` à la racine à partir de `.env.example`
et renseignez au minimum :

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.com
APP_KEY=base64:…            # 32 octets aléatoires en base64 — générez-en un : https://generate.plus/fr/base64 (44 caractères) ou `openssl rand -base64 32`
SETUP_TOKEN=un-mot-secret-long   # temporaire, pour l'étape 2.5

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_darulfatwa
DB_USERNAME=u123456789_admin
DB_PASSWORD="le mot de passe"   # guillemets si caractères spéciaux

MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=contact@votre-domaine.com
MAIL_PASSWORD="…"
MAIL_FROM_ADDRESS=contact@votre-domaine.com
MAIL_FROM_NAME="Darul Fatwa"

WHATSAPP_OFFICIAL_NUMBER=+223XXXXXXXX
```

Le SMTP et le numéro WhatsApp peuvent aussi être saisis plus tard dans *Administration → Paramètres* (valeurs chiffrées en base).

### 2.5 Créer le premier administrateur : la page `/setup`

1. Ouvrez `https://votre-domaine.com/setup`.
2. Saisissez le `SETUP_TOKEN` du `.env`, votre nom, votre e-mail et un mot de passe (10 caractères, lettres et chiffres).
3. Vous êtes connecté et redirigé vers l'administration.
4. **Supprimez la ligne `SETUP_TOKEN` du `.env`** : la page `/setup` disparaît (elle répond déjà 404 dès qu'un administrateur existe).

### 2.6 Réglages de l'hébergeur

- *Avancé → Configuration PHP* : version 8.2/8.3 ; extensions cochées ; `upload_max_filesize = 12M`, `post_max_size = 14M`,
  `max_execution_time = 60`, `memory_limit = 256M` (les fichiers `.user.ini` du projet les demandent déjà).
- *Sécurité → SSL* : certificat actif + « Forcer HTTPS ».
- *Avancé → Tâches cron* : `* * * * *  php /home/u123456789/domains/votre-domaine.com/public_html/cron/run.php >> /dev/null 2>&1`
  (adaptez le chemin ; si la fréquence minimale est de 5 minutes, le script rattrape les tâches en retard).

---

## 3. Chemin B — avec SSH ou Git

```bash
git clone https://github.com/id-diallo/darull-fatwa-php.git darul-fatwa && cd darul-fatwa
composer install --no-dev --optimize-autoloader
cp .env.example .env && nano .env            # APP_ENV=production, APP_DEBUG=false, APP_URL, base, SMTP
php bin/install.php                          # génère APP_KEY, crée les tables + données initiales + le super-admin
php bin/build-assets.php                     # CSS/JS de production (déjà versionnés, à relancer après modification)
php bin/doctor.php                           # vérification complète
```

Mises à jour ultérieures :

```bash
git pull
composer install --no-dev --optimize-autoloader
php bin/migrate.php                          # migrations SQL en attente
php bin/build-assets.php
php bin/doctor.php
```

Sans SSH, une mise à jour consiste à : téléverser le nouveau paquet (en conservant `.env`, `storage/` et `public/uploads/`),
puis exécuter dans phpMyAdmin les fichiers de `database/migrations/` non encore appliqués (table `schema_migrations`).

---

## 4. Après la mise en ligne : liste de contrôle

| # | Vérification | Comment |
|---|---|---|
| 1 | Page d'accueil en FR, AR et EN | `https://domaine/fr`, `/ar`, `/en` |
| 2 | HTTPS forcé | `http://domaine` redirige vers `https://` |
| 3 | Micro sur **Android (Chrome)** et **iPhone (Safari)** | Poser une question → vocal → 2 minutes → envoi → lecture dans l'espace professeur (R3, R4 du cahier) |
| 4 | E-mails | Inscription avec e-mail → message de vérification reçu (sinon : SMTP dans Paramètres, SPF/DKIM) |
| 5 | Cron | `php bin/doctor.php` (SSH) ou *Administration → Tableau de bord* : la file d'envoi se vide ; `storage/cache/cron.lock` récent |
| 6 | Sauvegardes | Le lendemain : *Administration → Sauvegardes* contient `darul-fatwa-…-cron.sql.gz` ; téléchargez-la et **testez une restauration** (section 5) |
| 7 | WhatsApp | Depuis l'espace professeur, le lien `wa.me` s'ouvre avec le message pré-rempli |
| 8 | Journaux | `storage/logs/app-AAAA-MM-JJ.log` sans `ERROR` |
| 9 | SEO | `https://domaine/sitemap.xml`, `/robots.txt` ; soumettre le sitemap dans Google Search Console |
| 10 | Comptes | Créer les professeurs réels (*Utilisateurs → Nouveau*, rôle professeur, puis *Professeurs → Modifier* : rubriques, langues, profil public) |
| 11 | Contenus | Relire et publier les 3 articles en brouillon (*Blog*), compléter les pages (*Pages*) et les bannières |
| 12 | `.env` | `APP_DEBUG=false`, `SETUP_TOKEN` supprimé |

---

## 5. Sauvegardes et restauration

- **Base** : chaque nuit à 01:00, `storage/backups/darul-fatwa-AAAAMMJJ-HHMMSS-cron.sql.gz` (14 jours conservés) ;
  bouton *Sauvegarder maintenant* réservé au super-administrateur.
- **Audios** : chaque dimanche à 01:30, `storage/backups/audio-AAAAMMJJ.zip` (4 archives conservées).
- **Hors serveur** : téléchargez régulièrement ces fichiers (ou planifiez une copie vers un stockage externe) — le serveur seul n'est pas une sauvegarde.

Restaurer (SSH) :

```bash
php bin/restore-backup.php storage/backups/darul-fatwa-20261017-010000-cron.sql.gz --database=darul_fatwa_test   # test sur une autre base
php bin/restore-backup.php storage/backups/darul-fatwa-20261017-010000-cron.sql.gz                                # base de production (confirmation)
```

Sans SSH : décompressez le `.sql.gz` puis importez le `.sql` dans phpMyAdmin (la base cible est remplacée table par table).

Test réalisé le 17/10/2026 : sauvegarde de 48 tables restaurée dans une base vierge, mêmes nombres de lignes (utilisateurs,
messages du forum, réglages, titres arabes intacts).

---

## 6. Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| « Dépendances manquantes : lancez composer install » | `vendor/` absent (ZIP GitHub) | Utiliser le paquet de déploiement ou `composer install --no-dev` |
| Page blanche / erreur 500 | `.env` incorrect, `storage/` non inscriptible | `APP_DEBUG=true` **temporairement** pour voir le détail, `storage/logs/`, droits 775 |
| Page 503 « maintenance » | fichier `storage/maintenance.flag` | le supprimer |
| Le menu ou les pages n'apparaissent pas | base vide ou non reliée | phpMyAdmin : 48 tables ? `.env` : noms complets ? |
| `/setup` répond 404 | `SETUP_TOKEN` vide, ou un administrateur existe déjà | renseigner le jeton, ou utiliser *Mot de passe oublié* |
| Micro inactif | site en HTTP | activer le certificat SSL et forcer HTTPS |
| Pas d'e-mails | SMTP non configuré | *Paramètres → Courrier* puis test d'inscription ; vérifier la file `message_queue` et `storage/logs/` |
| Recherche sans résultats sur mots courts | `innodb_ft_min_token_size` > 3 | demander à l'hébergeur de le passer à 3 (sinon les mots de moins de 4 lettres utilisent un repli `LIKE`) |
| Fichiers audio refusés | limites PHP | `upload_max_filesize ≥ 12M`, `post_max_size ≥ 14M` |

---

## 7. Mise à jour depuis une première installation incomplète

Si une version antérieure a été copiée sans base de données (symptômes : pas de menu, pas de rubriques, inscription impossible) :

1. Sauvegardez d'éventuels fichiers de `public/uploads/` et `storage/audio/`.
2. Videz `public_html`, puis suivez le chemin A depuis 2.2 (nouveau paquet) et 2.3 (import de `darul_fatwa_install.sql` dans une base **vide**).
3. Recréez `.env` (2.4) et le premier administrateur (2.5).
