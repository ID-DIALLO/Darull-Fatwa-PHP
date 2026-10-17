# Qualité — Phase 8 (SEO, performance, sécurité, accessibilité, PWA, recette)

Document de référence de la Phase 8 du cahier des charges (§12, §13, §18). Il récapitule ce qui a été mis en place,
comment le vérifier, et ce qui reste à faire en production (Phase 9).

## 1. Référencement (§13.1)

| Exigence | Mise en œuvre | Vérification |
|---|---|---|
| URL propres et traduites | Routes `localized()` FR / AR / EN (`app/Config/routes.php`) | `tests/Unit/RouterTest.php` |
| `title` / `meta description` uniques, champs SEO en back-office | Sections `title` / `description` de chaque vue ; champs `meta_title` / `meta_description` des rubriques, pages, articles et Q/R | `PublicPagesTest`, `BlogQaSearchTest` |
| `canonical`, `hreflang` FR/AR/EN + `x-default` | `layouts/front.php` à partir de `alternate_urls()` | `PublicPagesTest::testHreflangAlternatesArePresent` |
| Open Graph / Twitter Cards, image 1200 × 630 par défaut | `layouts/front.php` ; image `public/assets/img/og-default.png` générée par `bin/build-assets.php` | `QualityTest::testDonneesStructureesEtMetadonneesDePartage` |
| JSON-LD | `Organization` + `WebSite`/`SearchAction` (accueil), `BreadcrumbList` (toutes les pages internes, via `partials/breadcrumb`), `Person` (fiche professeur), `QAPage` (Q/R), `Article` (blog) ; helper `json_ld()` | idem + `BlogQaSearchTest` |
| `sitemap.xml` (index) + un sitemap par langue, régénérés par cron | `SitemapService` → `storage/cache/sitemaps/`, cron à 04:00, régénération à la demande si > 24 h ; `/sitemap.xml`, `/sitemap-fr.xml`… avec `xhtml:link hreflang` | `QualityTest::testLesSitemapsParLangue…` |
| `robots.txt` | Route `/robots.txt` (`SitemapService::robots()`) : exclut `/admin`, mon espace, espace professeur, `/api`, `/media`, connexion/inscription/recherche, dans les trois langues ; adresse du sitemap | `QualityTest::testRobotsExclut…` |
| Fil d'Ariane partout | `partials/breadcrumb` sur toutes les pages internes | captures Chromium |

## 2. Performance (§13.2)

| Exigence | Mise en œuvre |
|---|---|
| Images WebP + dimensions explicites + `loading="lazy"` | `ImageService` ré-encode toute image envoyée en WebP ; `<picture>`/`srcset` sur le Hero (première bannière en `fetchpriority="high"`), `width`/`height` sur toutes les images |
| Polices locales WOFF2, `font-display: swap`, sous-ensembles | `assets/css/fonts.css` (Inter, Gloock, Amiri — latin / arabe) |
| CSS/JS concaténés et minifiés sans Node | `php bin/build-assets.php` → `public/assets/dist/` (+ `manifest.json`). `asset()` et `styles()` servent la version construite quand `APP_ENV=production` (ou `ASSETS_BUILD=1`) ; les sources restent servies en développement. Les fichiers construits sont versionnés dans Git pour un déploiement par simple copie |
| `defer` / modules | Tous les scripts sont `defer` ou `type="module"` ; aucun script en ligne (CSP) |
| Cache HTTP long, compression | `public/.htaccess` : Brotli ou GZIP, `Cache-Control: immutable` 1 an sur les ressources versionnées (`?v=hash`), `no-cache` sur `sw.js` et le manifest |
| Cache applicatif | `App\Core\Cache` (fichiers) : rubriques, menus, bannières, sections de l'accueil, verset du jour, chiffres clés, paramètres ; invalidé par l'administration |
| Pagination, index SQL, pas de N+1 | Pagination sur toutes les listes ; index du schéma vérifiés ci-dessous ; chargements groupés (`WHERE … IN (…)`) |

### Index vérifiés (`EXPLAIN`, base de développement, puis base de test avec 5 000 réponses et 3 000 idées)

| Requête | Index utilisé |
|---|---|
| Dernières Q/R publiées (accueil) | `idx_qa_rubrique_status` → `uq_qa_tr_slug` |
| Liste des articles d'une langue | `uq_article_tr_slug` → `PRIMARY` |
| Article / Q/R / page / rubrique par slug | clés uniques `(locale, slug)` |
| Mes questions, file du professeur, questions admin | `idx_questions_user`, `idx_questions_status` |
| Question par référence | `uq_questions_reference` |
| Messages d'une question, notifications non lues | `idx_qm_question`, `idx_notifications_user` |
| Connexion par e-mail / téléphone | clés uniques `users.email`, `users.phone` |
| Sujets d'une catégorie, réponses d'un sujet | `idx_ft_category`, `idx_fp_topic` |
| Idées publiques, favoris, commentaires, journal | `idx_ideas_status`, `PRIMARY (user_id, …)`, `idx_comments_target`, `idx_al_user` |
| Recherche plein texte | index `FULLTEXT` `ft_article_tr`, `ft_qa_tr` (mode booléen, préfixes) |

Script de contrôle : `php /chemin/explain.php` (voir `docs/QUALITE.md` du dépôt ; la version utilisée pendant la phase est reproduite dans l'historique Git du présent document).

### Lighthouse mobile (Chromium headless, réseau « slow 4G » simulé, assets construits, compression active)

| Page | Performance | Accessibilité | Bonnes pratiques | SEO | LCP | FCP | Poids |
|---|---|---|---|---|---|---|---|
| Accueil `/fr` | **98** | **100** | **100** | **100** | 2,3 s | 1,5 s | 565 Kio |
| Article de blog | **100** | **100** | **100** | **100** | 1,6 s | 1,2 s | 496 Kio |
| Formulaire de question (connecté) | **99** | **100** | 96 ¹ | 66 ² | 1,9 s | 1,4 s | 520 Kio |

¹ Une requête `/api/notifications/count` en 401 pendant la mesure : Lighthouse n'envoie le cookie de session qu'au document, pas aux appels `fetch()`. En navigation réelle (Playwright connecté), aucune erreur console.
² Attendu : la page est réservée aux membres et porte `noindex` ; le score SEO ne s'applique pas à une page privée.

Avant la Phase 8 (sources non groupées, sans compression) : accueil 61 / 93 / 100 / 100. Pour rejouer :
`npx lighthouse https://votre-domaine/fr --form-factor=mobile --screenEmulation.mobile`.

## 3. Revue sécurité (§12.1), point par point

| Risque | Mesure | État |
|---|---|---|
| Injection SQL | PDO + requêtes préparées partout ; tris dynamiques via listes blanches (`AdminQuestionService::SORTS`, `match` dans `QaService`, `ScholarService`, `IdeaService`) ; `Model::orderBy` n'accepte que des identifiants validés | ✅ |
| XSS | `e()` systématique ; HTML riche nettoyé par HTMLPurifier à l'enregistrement ; CSP `default-src 'self'` sans `unsafe-inline` pour les scripts, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'` | ✅ `QualityTest::testEnTetesDeSecurite…` |
| CSRF | Jeton de session sur tout POST (`CsrfMiddleware`, en-tête `X-CSRF-Token` pour `fetch()`), cookies `SameSite=Lax` | ✅ `HttpTest::testPostWithoutCsrfTokenGives419` |
| Mots de passe | Argon2id (repli bcrypt), `password_needs_rehash()` à la connexion | ✅ |
| Sessions | `session_regenerate_id(true)` à la connexion ; cookies `HttpOnly`, `Secure` (HTTPS), `SameSite=Lax` ; inactivité 2 h, **30 min pour l'équipe** (drapeau `auth_staff` posé à la connexion) ; changement de mot de passe = déconnexion des autres appareils | ✅ `QualityTest::testLaSessionDeLEquipe…` |
| Force brute | `login_attempts` (connexion) et `rate_limits` (inscription, contact, questions, forum, idées…) → 429 | ✅ `AuthTest::testLoginThrottle…` |
| Robots | Cloudflare Turnstile (actif dès que les clés sont saisies) + champ piège sur inscription, contact, newsletter | ✅ `PublicPagesTest::testHoneypotRejectsRobots` |
| Contrôle d'accès | `RoleMiddleware` + permission par module + vérification de propriété dans chaque contrôleur ; références `DF-AAAA-NNNNNN` et UUID pour les médias | ✅ `QuestionsTest::testAnotherMemberGuessingTheAudioUrlGets404`, `AdminTest::testModeratorCannotAccess…` |
| Fichiers envoyés | `finfo` + liste blanche d'extensions + taille max ; audio hors racine web servi par `MediaController` (HTTP Range) ; images ré-encodées (EXIF supprimés) ; `public/uploads/.htaccess` interdit PHP | ✅ `QuestionsTest::testNonAudioFileRenamedAsMp3IsRefused` |
| Transport | Redirection HTTP → HTTPS dans `.htaccess` ; HSTS 1 an en HTTPS | ✅ |
| En-têtes | `nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: microphone=(self), camera=(), geolocation=()` | ✅ |
| Secrets | `.env` hors Git ; SMTP / WhatsApp / Turnstile chiffrés en base (AES-256-GCM avec `APP_KEY`) | ✅ |
| Erreurs | `APP_DEBUG=false` → page 500 générique traduite, détails dans `storage/logs/` | ✅ |
| Webhook WhatsApp | Signature HMAC `X-Hub-Signature-256` : prévue dans `CloudApiGateway` (V2, non exposé en V1) | ⏳ V2 |
| Journalisation | `activity_logs` pour les actions sensibles ; journaux applicatifs quotidiens, rotation 30 jours | ✅ |
| Sauvegardes | Base chaque nuit (01:00, rétention 14 j) ; archive ZIP des audios chaque dimanche (01:30, 4 archives conservées) ; copie hors serveur à organiser chez l'hébergeur | ✅ / à compléter en production |

Données personnelles (§12.2) : questions privées par défaut, publication anonymisée après accord, voix du demandeur jamais publiée,
numéros masqués pour les professeurs sauf canal WhatsApp, export et suppression de compte depuis « Mon espace ».

## 4. Accessibilité (§13.3)

- Lien d'évitement, `main` focalisable, focus visible (`:focus-visible` sur tous les contrôles), contrastes AA des couleurs de la charte.
- Cibles tactiles ≥ 44 px pour les boutons, 24 px minimum garantis pour les puces du carrousel ; bouton « Poser une question » nommé même quand son libellé est masqué (`aria-label`).
- Formulaires : libellés explicites, erreurs associées par `aria-describedby`, champs téléphone/mot de passe accessibles au clavier.
- `lang` / `dir` sur les passages dans une autre langue (versets arabes, `dir="auto"` sur les contenus saisis).
- Carrousel : boutons pause/lecture, pas de défilement automatique si `prefers-reduced-motion` ; compteurs animés désactivés de même.
- Réponses audio : le texte de la réponse est toujours affiché à côté du lecteur.
- Lighthouse « Accessibilité » : 100 sur les trois pages mesurées.

## 5. PWA légère (§13.4)

- `manifest.webmanifest` (nom, icônes 192/512 + SVG, `display: standalone`, couleur `#0B5D45`).
- `public/sw.js` : ressources statiques (`/assets`, `/vendor`, manifest) en cache avec mise à jour en arrière-plan ; pages HTML réseau uniquement ;
  page `/offline` (trilingue, autonome) en repli ; **aucune mise en cache** de `/admin`, de l'espace membre, de l'espace professeur, de `/api` ni de `/media`.
- Enregistrement dans `app.js` (contexte sécurisé uniquement). Vérifié dans Chromium : service worker actif, page hors-ligne affichée après arrêt du serveur.

## 6. Recette (§18.2)

| # | Scénario | Résultat | Preuve |
|---|---|---|---|
| R1 | Visiteur clique « Poser une question » | ✅ | `HttpTest::testGuestIsRedirectedToLoginThenBackToIntendedPage` |
| R2 | Inscription avec téléphone seul | ✅ | `AuthTest::testMemberCanRegisterWithPhoneOnlyAndLogIn` |
| R3 | Question vocale de 2 min sur Android | ⏳ | Enregistreur vérifié dans Chromium (`tests` + captures) ; **à rejouer sur appareil réel en HTTPS** (Phase 9) |
| R4 | Question vocale sur iPhone (Safari) | ⏳ | Repli `MediaRecorder` mp4 prévu ; **à rejouer sur appareil réel** (Phase 9) |
| R5 | Micro refusé | ✅ | Message traduit + bascule texte (`voice-recorder.js`), vérifié dans Chromium avec permission refusée |
| R6 | Question écrite en arabe sur la version française | ✅ | `dir="auto"` sur les textes saisis ; captures AR/FR |
| R7 | Assignation, précision, réponse vocale du membre | ✅ | `ScholarTest::testFullJourneyFromQuestionToAnswerReadByMember`, `QuestionsTest::testFollowUpResolveAndFeedback` |
| R8 | Professeur avec relecture obligatoire | ✅ | `ScholarTest::testReviewRequiredHidesTheAnswerUntilAdminApproval` |
| R9 | Membre choisit « WhatsApp » | ✅ | `ScholarTest::testPhoneIsMaskedUnlessWhatsAppChannelWasChosen` |
| R10 | Admin publie une Q/R | ✅ | `AdminTest::testPublishedQaContainsNoPersonalData` |
| R11 | Export Excel filtré | ✅ | `AdminTest::testQuestionJourneyAssignReviewPublishAndExport` (CSV BOM + XLSX, arabe lisible) |
| R12 | FR → AR sur un article traduit | ✅ | `BlogQaSearchTest::testArticlePublishedInFrAndArSwitchesLanguage` |
| R13 | Changement de langue sur un article non traduit | ✅ | `BlogQaSearchTest` (`?missing=id`) |
| R14 | Nouveau membre poste sur le forum | ✅ | `ForumIdeasTest::testLesTroisPremiersMessages…` |
| R15 | Audio d'un autre membre | ✅ | `QuestionsTest::testAnotherMemberGuessingTheAudioUrlGets404` |
| R16 | Suppression de compte | ✅ | `AuthTest::testAccountExportAndDeletion` |
| R17 | Site en 3G simulée | ✅ | Lighthouse « slow 4G » : accueil utilisable à 1,5 s (FCP), 2,3 s (LCP) ; 3G réelle à confirmer en production |

Suite automatisée : `vendor/bin/phpunit` — 185 tests, 1 305 assertions, au vert.
