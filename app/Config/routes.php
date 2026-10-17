<?php

declare(strict_types=1);

use App\Controllers\Account\DashboardController as AccountDashboardController;
use App\Controllers\Account\MyQuestionsController;
use App\Controllers\Account\NotificationsController;
use App\Controllers\Account\ProfileController;
use App\Controllers\Api\AudioUploadController;
use App\Controllers\Admin\ActivityLogController;
use App\Controllers\Admin\BackupsController;
use App\Controllers\Admin\ArticlesController as AdminArticlesController;
use App\Controllers\Admin\BannersController;
use App\Controllers\Admin\CommentsController;
use App\Controllers\Admin\ContactMessagesController;
use App\Controllers\Admin\DailyQuotesController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\ForumController as AdminForumController;
use App\Controllers\Admin\HomeSectionsController;
use App\Controllers\Admin\IdeasController as AdminIdeasController;
use App\Controllers\Admin\NewsletterController as AdminNewsletterController;
use App\Controllers\Admin\PagesController;
use App\Controllers\Admin\QaController as AdminQaController;
use App\Controllers\Admin\QuestionsController as AdminQuestionsController;
use App\Controllers\Admin\ReportsController;
use App\Controllers\Admin\RubriquesController as AdminRubriquesController;
use App\Controllers\Admin\ScholarsController as AdminScholarsController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\TagsController;
use App\Controllers\Admin\UsersController;
use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\PasswordController;
use App\Controllers\Auth\RegisterController;
use App\Controllers\Auth\VerificationController;
use App\Controllers\Front\BlogController;
use App\Controllers\Front\ContactController;
use App\Controllers\Front\FavoriteController;
use App\Controllers\Front\ForumController;
use App\Controllers\Front\IdeaController;
use App\Controllers\Front\HomeController;
use App\Controllers\Front\MediaController;
use App\Controllers\Front\NewsletterController;
use App\Controllers\Front\PageController;
use App\Controllers\Front\QaController;
use App\Controllers\Front\QuestionController;
use App\Controllers\Front\RubriqueController;
use App\Controllers\Front\ScholarController;
use App\Controllers\Front\SearchController;
use App\Controllers\Scholar\ArticlesController as ScholarArticlesController;
use App\Controllers\Scholar\DashboardController as ScholarDashboardController;
use App\Controllers\Scholar\ProfileController as ScholarProfileController;
use App\Controllers\Scholar\QueueController;
use App\Core\Router;

/*
 * Routes de l'application (§11). Les routes publiques sont déclarées avec localized() :
 * segments en français pour /fr/, en anglais pour /en/ et /ar/. Toutes les routes sont nommées.
 */
return static function (Router $router): void {
    // « / » → langue détectée (cookie, profil, navigateur, français par défaut)
    $router->get('/', [HomeController::class, 'root'])->name('root');

    // Accueil : /fr, /ar, /en
    $router->localized('GET', '', [HomeController::class, 'index'], 'home');

    // ---- Site public (§11.1) ------------------------------------------------------------------
    $router->localized('GET', ['fr' => '/rubriques', 'en' => '/topics'], [RubriqueController::class, 'index'], 'rubriques.index');
    $router->localized('GET', ['fr' => '/rubriques/{slug}', 'en' => '/topics/{slug}'], [RubriqueController::class, 'show'], 'rubriques.show');
    $router->localized('GET', ['fr' => '/professeurs', 'en' => '/scholars'], [ScholarController::class, 'index'], 'scholars.index');
    $router->localized('GET', ['fr' => '/professeurs/{slug}', 'en' => '/scholars/{slug}'], [ScholarController::class, 'show'], 'scholars.show');
    $router->localized('GET', ['fr' => '/page/{slug}', 'en' => '/page/{slug}'], [PageController::class, 'show'], 'pages.show');
    $router->localized('GET', ['fr' => '/contact', 'en' => '/contact'], [ContactController::class, 'show'], 'contact');
    $router->localized('POST', ['fr' => '/contact', 'en' => '/contact'], [ContactController::class, 'send'], 'contact.send', 'throttle:3,3600');
    $router->post('/newsletter', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe')->middleware('throttle:5,3600');
    $router->localized('GET', ['fr' => '/newsletter/confirmer/{token}', 'en' => '/newsletter/confirm/{token}'], [NewsletterController::class, 'confirm'], 'newsletter.confirm');
    $router->localized('GET', ['fr' => '/newsletter/desinscription/{token}', 'en' => '/newsletter/unsubscribe/{token}'], [NewsletterController::class, 'unsubscribe'], 'newsletter.unsubscribe');

    // ---- Q/R publiques, blog, recherche, favoris (§4.7, §4.8, §4.13) ------------------------------
    $router->localized('GET', ['fr' => '/questions-reponses', 'en' => '/questions-answers'], [QaController::class, 'index'], 'qa.index');
    $router->localized('GET', ['fr' => '/questions-reponses/{slug}', 'en' => '/questions-answers/{slug}'], [QaController::class, 'show'], 'qa.show');
    $router->localized('POST', ['fr' => '/questions-reponses/{slug}/commentaire', 'en' => '/questions-answers/{slug}/comment'], [QaController::class, 'comment'], 'qa.comment', ['auth', 'throttle:10,3600']);
    $router->localized('GET', ['fr' => '/blog', 'en' => '/blog'], [BlogController::class, 'index'], 'blog.index');
    $router->localized('GET', ['fr' => '/blog/{slug}', 'en' => '/blog/{slug}'], [BlogController::class, 'show'], 'blog.show');
    $router->localized('POST', ['fr' => '/blog/{slug}/commentaire', 'en' => '/blog/{slug}/comment'], [BlogController::class, 'comment'], 'blog.comment', ['auth', 'throttle:10,3600']);
    $router->localized('GET', ['fr' => '/recherche', 'en' => '/search'], [SearchController::class, 'index'], 'search');
    $router->localized('POST', ['fr' => '/favoris', 'en' => '/favorites'], [FavoriteController::class, 'toggle'], 'favorites.toggle', 'auth');

    // ---- Forum et boîte à idées (§4.9, §4.10) ---------------------------------------------------
    $router->localized('GET', ['fr' => '/forum', 'en' => '/forum'], [ForumController::class, 'index'], 'forum.index');
    $router->localized('GET', ['fr' => '/forum/nouveau-sujet', 'en' => '/forum/new-topic'], [ForumController::class, 'create'], 'forum.create', 'auth');
    $router->localized('POST', ['fr' => '/forum/nouveau-sujet', 'en' => '/forum/new-topic'], [ForumController::class, 'store'], 'forum.store', ['auth', 'throttle:10,3600']);
    $router->localized('POST', ['fr' => '/forum/jaime', 'en' => '/forum/like'], [ForumController::class, 'like'], 'forum.like', 'auth');
    $router->localized('POST', ['fr' => '/forum/signaler', 'en' => '/forum/report'], [ForumController::class, 'report'], 'forum.report', ['auth', 'throttle:20,3600']);
    $router->localized('GET', ['fr' => '/forum/sujet/{id:\d+}-{slug}', 'en' => '/forum/topic/{id:\d+}-{slug}'], [ForumController::class, 'topic'], 'forum.topic');
    $router->localized('GET', ['fr' => '/forum/sujet/{id:\d+}', 'en' => '/forum/topic/{id:\d+}'], [ForumController::class, 'topic'], 'forum.topic_short');
    $router->localized('POST', ['fr' => '/forum/sujet/{id:\d+}/repondre', 'en' => '/forum/topic/{id:\d+}/reply'], [ForumController::class, 'reply'], 'forum.reply', ['auth', 'throttle:30,3600']);
    $router->localized('POST', ['fr' => '/forum/sujet/{id:\d+}/abonnement', 'en' => '/forum/topic/{id:\d+}/subscribe'], [ForumController::class, 'subscribe'], 'forum.subscribe', 'auth');
    $router->localized('POST', ['fr' => '/forum/sujet/{id:\d+}/moderer', 'en' => '/forum/topic/{id:\d+}/moderate'], [ForumController::class, 'moderate'], 'forum.moderate', 'role:moderator,admin');
    $router->localized('POST', ['fr' => '/forum/message/{postId}/valider', 'en' => '/forum/post/{postId}/validate'], [ForumController::class, 'validate_'], 'forum.validate', 'role:scholar,admin');
    $router->localized('GET', ['fr' => '/forum/{slug}', 'en' => '/forum/{slug}'], [ForumController::class, 'category'], 'forum.category');
    $router->localized('GET', ['fr' => '/idees', 'en' => '/ideas'], [IdeaController::class, 'index'], 'ideas.index');
    $router->localized('GET', ['fr' => '/idees/proposer', 'en' => '/ideas/propose'], [IdeaController::class, 'create'], 'ideas.create', 'auth');
    $router->localized('POST', ['fr' => '/idees/proposer', 'en' => '/ideas/propose'], [IdeaController::class, 'store'], 'ideas.store', ['auth', 'throttle:5,3600']);
    $router->localized('GET', ['fr' => '/idees/{id:\d+}', 'en' => '/ideas/{id:\d+}'], [IdeaController::class, 'show'], 'ideas.show');
    $router->localized('POST', ['fr' => '/idees/{id:\d+}/vote', 'en' => '/ideas/{id:\d+}/vote'], [IdeaController::class, 'vote'], 'ideas.vote', 'auth');
    $router->localized('POST', ['fr' => '/idees/{id:\d+}/commentaire', 'en' => '/ideas/{id:\d+}/comment'], [IdeaController::class, 'comment'], 'ideas.comment', ['auth', 'throttle:20,3600']);

    // ---- Poser une question (§4.2, §11.1) ----------------------------------------------------
    $router->localized('GET', ['fr' => '/poser-une-question', 'en' => '/ask'], [QuestionController::class, 'create'], 'questions.create', 'auth');
    $router->localized('POST', ['fr' => '/poser-une-question', 'en' => '/ask'], [QuestionController::class, 'store'], 'questions.store', ['auth', 'throttle:10,3600']);
    $router->localized('GET', ['fr' => '/poser-une-question/confirmation/{reference}', 'en' => '/ask/confirmation/{reference}'], [QuestionController::class, 'confirmation'], 'questions.confirmation', 'auth');

    // ---- Médias privés et API internes (§11.5) ------------------------------------------------
    $router->get('/media/audio/{uuid}', [MediaController::class, 'audio'])->name('media.audio');
    $router->post('/api/audio', [AudioUploadController::class, 'store'])->name('api.audio.store')->middleware(['auth', 'throttle:40,3600']);
    $router->delete('/api/audio/{uuid}', [AudioUploadController::class, 'destroy'])->name('api.audio.destroy')->middleware('auth');
    $router->get('/api/notifications/count', [NotificationsController::class, 'count'])->name('api.notifications.count')->middleware('auth');
    $router->get('/api/notifications', [NotificationsController::class, 'list'])->name('api.notifications.list')->middleware('auth');
    $router->post('/api/notifications/read-all', [NotificationsController::class, 'readAll'])->name('api.notifications.read_all')->middleware('auth');

    // ---- Authentification (§4.1, §11.1) ------------------------------------------------------
    $router->localized('GET', ['fr' => '/connexion', 'en' => '/login'], [LoginController::class, 'show'], 'auth.login', 'guest');
    $router->localized('POST', ['fr' => '/connexion', 'en' => '/login'], [LoginController::class, 'login'], 'auth.login.post', ['guest', 'throttle:20,60']);
    $router->localized('POST', ['fr' => '/deconnexion', 'en' => '/logout'], [LoginController::class, 'logout'], 'auth.logout', 'auth');

    $router->localized('GET', ['fr' => '/inscription', 'en' => '/register'], [RegisterController::class, 'show'], 'auth.register', 'guest');
    $router->localized('POST', ['fr' => '/inscription', 'en' => '/register'], [RegisterController::class, 'register'], 'auth.register.post', ['guest', 'throttle:10,3600']);

    $router->localized('GET', ['fr' => '/mot-de-passe-oublie', 'en' => '/forgot-password'], [PasswordController::class, 'forgot'], 'auth.forgot', 'guest');
    $router->localized('POST', ['fr' => '/mot-de-passe-oublie', 'en' => '/forgot-password'], [PasswordController::class, 'sendLink'], 'auth.forgot.post', ['guest', 'throttle:5,900']);
    $router->localized('GET', ['fr' => '/reinitialiser/{token}', 'en' => '/reset/{token}'], [PasswordController::class, 'reset'], 'auth.reset', 'guest');
    $router->localized('POST', ['fr' => '/reinitialiser/{token}', 'en' => '/reset/{token}'], [PasswordController::class, 'update'], 'auth.reset.post', ['guest', 'throttle:10,900']);

    $router->localized('GET', ['fr' => '/verifier-email/{token}', 'en' => '/verify-email/{token}'], [VerificationController::class, 'verify'], 'auth.verify');

    // ---- Mon espace (§4.4, §11.2) ------------------------------------------------------------
    $router->localized('GET', ['fr' => '/mon-espace', 'en' => '/my-account'], [AccountDashboardController::class, 'index'], 'account.dashboard', 'auth');
    $router->localized('GET', ['fr' => '/mon-espace/questions', 'en' => '/my-account/questions'], [MyQuestionsController::class, 'index'], 'account.questions', 'auth');
    $router->localized('GET', ['fr' => '/mon-espace/questions/{reference}', 'en' => '/my-account/questions/{reference}'], [MyQuestionsController::class, 'show'], 'account.questions.show', 'auth');
    $router->localized('POST', ['fr' => '/mon-espace/questions/{reference}/precision', 'en' => '/my-account/questions/{reference}/follow-up'], [MyQuestionsController::class, 'followUp'], 'account.questions.follow_up', ['auth', 'throttle:20,3600']);
    $router->localized('POST', ['fr' => '/mon-espace/questions/{reference}/resolue', 'en' => '/my-account/questions/{reference}/resolved'], [MyQuestionsController::class, 'resolve'], 'account.questions.resolve', 'auth');
    $router->localized('POST', ['fr' => '/mon-espace/questions/{reference}/avis', 'en' => '/my-account/questions/{reference}/feedback'], [MyQuestionsController::class, 'feedback'], 'account.questions.feedback', 'auth');
    $router->localized('GET', ['fr' => '/mon-espace/favoris', 'en' => '/my-account/favorites'], [FavoriteController::class, 'index'], 'account.favorites', 'auth');
    $router->localized('GET', ['fr' => '/mon-espace/forum', 'en' => '/my-account/forum'], [ForumController::class, 'mine'], 'account.forum', 'auth');
    $router->localized('GET', ['fr' => '/mon-espace/idees', 'en' => '/my-account/ideas'], [IdeaController::class, 'mine'], 'account.ideas', 'auth');
    $router->localized('GET', ['fr' => '/mon-espace/notifications', 'en' => '/my-account/notifications'], [NotificationsController::class, 'index'], 'account.notifications', 'auth');
    $router->localized('POST', ['fr' => '/mon-espace/notifications/tout-lu', 'en' => '/my-account/notifications/read-all'], [NotificationsController::class, 'readAll'], 'account.notifications.read_all', 'auth');
    $router->localized('GET', ['fr' => '/mon-espace/notifications/{id}', 'en' => '/my-account/notifications/{id}'], [NotificationsController::class, 'open'], 'account.notifications.open', 'auth');
    $router->localized('GET', ['fr' => '/mon-espace/profil', 'en' => '/my-account/profile'], [ProfileController::class, 'edit'], 'account.profile', 'auth');
    $router->localized('POST', ['fr' => '/mon-espace/profil', 'en' => '/my-account/profile'], [ProfileController::class, 'update'], 'account.profile.update', 'auth');
    $router->localized('POST', ['fr' => '/mon-espace/renvoyer-verification', 'en' => '/my-account/resend-verification'], [VerificationController::class, 'resend'], 'account.verification.resend', ['auth', 'throttle:3,600']);
    $router->localized('GET', ['fr' => '/mon-espace/securite', 'en' => '/my-account/security'], [ProfileController::class, 'security'], 'account.security', 'auth');
    $router->localized('POST', ['fr' => '/mon-espace/securite/mot-de-passe', 'en' => '/my-account/security/password'], [ProfileController::class, 'updatePassword'], 'account.security.password', 'auth');
    $router->localized('POST', ['fr' => '/mon-espace/securite/deconnecter-partout', 'en' => '/my-account/security/logout-everywhere'], [ProfileController::class, 'logoutEverywhere'], 'account.security.logout_everywhere', 'auth');
    $router->localized('GET', ['fr' => '/mon-espace/donnees', 'en' => '/my-account/data'], [ProfileController::class, 'data'], 'account.data', 'auth');
    $router->localized('GET', ['fr' => '/mon-espace/export', 'en' => '/my-account/export'], [ProfileController::class, 'export'], 'account.export', ['auth', 'throttle:5,3600']);
    $router->localized('POST', ['fr' => '/mon-espace/supprimer', 'en' => '/my-account/delete'], [ProfileController::class, 'destroy'], 'account.delete', 'auth');

    // ---- Espace professeur (§4.5, §11.3) --------------------------------------------------------
    $scholar = 'role:scholar,admin';
    $router->localized('GET', ['fr' => '/professeur', 'en' => '/scholar'], [ScholarDashboardController::class, 'index'], 'scholar.dashboard', $scholar);
    $router->localized('GET', ['fr' => '/professeur/questions', 'en' => '/scholar/questions'], [QueueController::class, 'index'], 'scholar.questions', $scholar);
    $router->localized('GET', ['fr' => '/professeur/questions/{reference}', 'en' => '/scholar/questions/{reference}'], [QueueController::class, 'show'], 'scholar.questions.show', $scholar);
    $router->localized('POST', ['fr' => '/professeur/questions/{reference}/prendre', 'en' => '/scholar/questions/{reference}/take'], [QueueController::class, 'take'], 'scholar.questions.take', $scholar);
    $router->localized('POST', ['fr' => '/professeur/questions/{reference}/repondre', 'en' => '/scholar/questions/{reference}/answer'], [QueueController::class, 'answer'], 'scholar.questions.answer', $scholar);
    $router->localized('POST', ['fr' => '/professeur/questions/{reference}/demander-precision', 'en' => '/scholar/questions/{reference}/request-info'], [QueueController::class, 'requestInfo'], 'scholar.questions.request_info', $scholar);
    $router->localized('POST', ['fr' => '/professeur/questions/{reference}/note', 'en' => '/scholar/questions/{reference}/note'], [QueueController::class, 'note'], 'scholar.questions.note', $scholar);
    $router->localized('POST', ['fr' => '/professeur/questions/{reference}/reassignation', 'en' => '/scholar/questions/{reference}/reassign'], [QueueController::class, 'reassign'], 'scholar.questions.reassign', $scholar);
    $router->localized('POST', ['fr' => '/professeur/questions/{reference}/whatsapp-envoye', 'en' => '/scholar/questions/{reference}/whatsapp-sent'], [QueueController::class, 'whatsappSent'], 'scholar.questions.whatsapp_sent', $scholar);
    $router->localized('POST', ['fr' => '/professeur/questions/{reference}/proposer-publication', 'en' => '/scholar/questions/{reference}/propose-publication'], [QueueController::class, 'proposePublication'], 'scholar.questions.propose_publication', $scholar);
    $router->localized('POST', ['fr' => '/professeur/questions/{reference}/transcription', 'en' => '/scholar/questions/{reference}/transcript'], [QueueController::class, 'transcript'], 'scholar.questions.transcript', $scholar);
    $router->localized('GET', ['fr' => '/professeur/articles', 'en' => '/scholar/articles'], [ScholarArticlesController::class, 'index'], 'scholar.articles', $scholar);
    $router->localized('GET', ['fr' => '/professeur/articles/nouveau', 'en' => '/scholar/articles/new'], [ScholarArticlesController::class, 'create'], 'scholar.articles.create', $scholar);
    $router->localized('POST', ['fr' => '/professeur/articles', 'en' => '/scholar/articles'], [ScholarArticlesController::class, 'store'], 'scholar.articles.store', $scholar);
    $router->localized('GET', ['fr' => '/professeur/articles/{id}', 'en' => '/scholar/articles/{id}'], [ScholarArticlesController::class, 'edit'], 'scholar.articles.edit', $scholar);
    $router->localized('POST', ['fr' => '/professeur/articles/{id}', 'en' => '/scholar/articles/{id}'], [ScholarArticlesController::class, 'update'], 'scholar.articles.update', $scholar);
    $router->localized('GET', ['fr' => '/professeur/profil', 'en' => '/scholar/profile'], [ScholarProfileController::class, 'edit'], 'scholar.profile', $scholar);
    $router->localized('POST', ['fr' => '/professeur/profil', 'en' => '/scholar/profile'], [ScholarProfileController::class, 'update'], 'scholar.profile.update', $scholar);

    // ---- Administration (§4.6, §11.4) : modérateurs, administrateurs et super-administrateur -------
    // Chaque contrôleur vérifie en plus la permission de son module (users.manage, settings.manage…).
    $router->group(['prefix' => '/admin', 'middleware' => 'role:moderator,admin'], static function (Router $router): void {
        $router->get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        $router->get('/stats/data', [AdminDashboardController::class, 'data'])->name('admin.stats.data');
        $router->get('/lang/{code}', [AdminDashboardController::class, 'language'])->name('admin.lang');

        // Questions
        $router->get('/questions', [AdminQuestionsController::class, 'index'])->name('admin.questions');
        $router->get('/questions/export', [AdminQuestionsController::class, 'export'])->name('admin.questions.export');
        $router->get('/questions/new', [AdminQuestionsController::class, 'create'])->name('admin.questions.create');
        $router->post('/questions', [AdminQuestionsController::class, 'store'])->name('admin.questions.store');
        $router->post('/questions/bulk', [AdminQuestionsController::class, 'bulk'])->name('admin.questions.bulk');
        $router->get('/questions/{id}', [AdminQuestionsController::class, 'show'])->name('admin.questions.show');
        $router->post('/questions/{id}/assign', [AdminQuestionsController::class, 'assign'])->name('admin.questions.assign');
        $router->post('/questions/{id}/status', [AdminQuestionsController::class, 'status'])->name('admin.questions.status');
        $router->post('/questions/{id}/review/{messageId}', [AdminQuestionsController::class, 'review'])->name('admin.questions.review');
        $router->post('/questions/{id}/publish', [AdminQuestionsController::class, 'publish'])->name('admin.questions.publish');
        $router->post('/questions/{id}/import-audio', [AdminQuestionsController::class, 'importAudio'])->name('admin.questions.import_audio');
        $router->post('/questions/{id}/note', [AdminQuestionsController::class, 'note'])->name('admin.questions.note');
        $router->post('/questions/{id}/delete', [AdminQuestionsController::class, 'destroy'])->name('admin.questions.destroy');

        // Utilisateurs et professeurs
        $router->get('/users', [UsersController::class, 'index'])->name('admin.users');
        $router->get('/users/new', [UsersController::class, 'create'])->name('admin.users.create');
        $router->post('/users', [UsersController::class, 'store'])->name('admin.users.store');
        $router->get('/users/{id}', [UsersController::class, 'show'])->name('admin.users.show');
        $router->get('/users/{id}/edit', [UsersController::class, 'edit'])->name('admin.users.edit');
        $router->post('/users/{id}', [UsersController::class, 'update'])->name('admin.users.update');
        $router->post('/users/{id}/role', [UsersController::class, 'role'])->name('admin.users.role');
        $router->post('/users/{id}/status', [UsersController::class, 'status'])->name('admin.users.status');
        $router->post('/users/{id}/reset-link', [UsersController::class, 'resetLink'])->name('admin.users.reset_link');
        $router->post('/users/{id}/delete', [UsersController::class, 'destroy'])->name('admin.users.destroy');
        $router->get('/scholars', [AdminScholarsController::class, 'index'])->name('admin.scholars');
        $router->get('/scholars/{id}/edit', [AdminScholarsController::class, 'edit'])->name('admin.scholars.edit');
        $router->post('/scholars/{id}', [AdminScholarsController::class, 'update'])->name('admin.scholars.update');

        // Contenus
        $router->get('/rubriques', [AdminRubriquesController::class, 'index'])->name('admin.rubriques');
        $router->get('/rubriques/new', [AdminRubriquesController::class, 'create'])->name('admin.rubriques.create');
        $router->post('/rubriques', [AdminRubriquesController::class, 'store'])->name('admin.rubriques.store');
        $router->post('/rubriques/reorder', [AdminRubriquesController::class, 'reorder'])->name('admin.rubriques.reorder');
        $router->get('/rubriques/{id}/edit', [AdminRubriquesController::class, 'edit'])->name('admin.rubriques.edit');
        $router->post('/rubriques/{id}', [AdminRubriquesController::class, 'update'])->name('admin.rubriques.update');
        $router->post('/rubriques/{id}/delete', [AdminRubriquesController::class, 'destroy'])->name('admin.rubriques.destroy');
        $router->get('/articles', [AdminArticlesController::class, 'index'])->name('admin.articles');
        $router->get('/articles/new', [AdminArticlesController::class, 'create'])->name('admin.articles.create');
        $router->post('/articles', [AdminArticlesController::class, 'store'])->name('admin.articles.store');
        $router->get('/articles/{id}/edit', [AdminArticlesController::class, 'edit'])->name('admin.articles.edit');
        $router->post('/articles/{id}', [AdminArticlesController::class, 'update'])->name('admin.articles.update');
        $router->post('/articles/{id}/status', [AdminArticlesController::class, 'status'])->name('admin.articles.status');
        $router->post('/articles/{id}/delete', [AdminArticlesController::class, 'destroy'])->name('admin.articles.destroy');
        $router->get('/tags', [TagsController::class, 'index'])->name('admin.tags');
        $router->post('/tags/{id}', [TagsController::class, 'update'])->name('admin.tags.update');
        $router->post('/tags/{id}/delete', [TagsController::class, 'destroy'])->name('admin.tags.destroy');
        $router->get('/comments', [CommentsController::class, 'index'])->name('admin.comments');
        $router->post('/comments/{id}/moderate', [CommentsController::class, 'moderate'])->name('admin.comments.moderate');
        $router->post('/comments/{id}/delete', [CommentsController::class, 'destroy'])->name('admin.comments.destroy');
        $router->get('/forum', [AdminForumController::class, 'index'])->name('admin.forum');
        $router->get('/forum/posts', [AdminForumController::class, 'posts'])->name('admin.forum.posts');
        $router->get('/forum/categories', [AdminForumController::class, 'categories'])->name('admin.forum.categories');
        $router->post('/forum/categories', [AdminForumController::class, 'saveCategory'])->name('admin.forum.categories.store');
        $router->post('/forum/categories/{id}', [AdminForumController::class, 'saveCategory'])->name('admin.forum.categories.update');
        $router->post('/forum/topics/{id}', [AdminForumController::class, 'topic'])->name('admin.forum.topic');
        $router->post('/forum/posts/{id}', [AdminForumController::class, 'post'])->name('admin.forum.post');
        $router->get('/reports', [ReportsController::class, 'index'])->name('admin.reports');
        $router->post('/reports/{id}', [ReportsController::class, 'handle'])->name('admin.reports.handle');
        $router->get('/ideas', [AdminIdeasController::class, 'index'])->name('admin.ideas');
        $router->get('/ideas/{id:\d+}', [AdminIdeasController::class, 'show'])->name('admin.ideas.show');
        $router->post('/ideas/{id:\d+}', [AdminIdeasController::class, 'update'])->name('admin.ideas.update');
        $router->post('/ideas/{id:\d+}/delete', [AdminIdeasController::class, 'destroy'])->name('admin.ideas.destroy');
        $router->post('/ideas/comments/{id}', [AdminIdeasController::class, 'comment'])->name('admin.ideas.comment');
        $router->get('/qa', [AdminQaController::class, 'index'])->name('admin.qa');
        $router->get('/qa/{id}/edit', [AdminQaController::class, 'edit'])->name('admin.qa.edit');
        $router->post('/qa/{id}', [AdminQaController::class, 'update'])->name('admin.qa.update');
        $router->post('/qa/{id}/delete', [AdminQaController::class, 'destroy'])->name('admin.qa.destroy');
        $router->get('/banners', [BannersController::class, 'index'])->name('admin.banners');
        $router->get('/banners/new', [BannersController::class, 'create'])->name('admin.banners.create');
        $router->post('/banners', [BannersController::class, 'store'])->name('admin.banners.store');
        $router->get('/banners/{id}/edit', [BannersController::class, 'edit'])->name('admin.banners.edit');
        $router->post('/banners/{id}', [BannersController::class, 'update'])->name('admin.banners.update');
        $router->post('/banners/{id}/delete', [BannersController::class, 'destroy'])->name('admin.banners.destroy');
        $router->get('/home-sections', [HomeSectionsController::class, 'index'])->name('admin.home_sections');
        $router->post('/home-sections', [HomeSectionsController::class, 'update'])->name('admin.home_sections.update');
        $router->get('/daily-quotes', [DailyQuotesController::class, 'index'])->name('admin.daily_quotes');
        $router->get('/daily-quotes/new', [DailyQuotesController::class, 'create'])->name('admin.daily_quotes.create');
        $router->post('/daily-quotes', [DailyQuotesController::class, 'store'])->name('admin.daily_quotes.store');
        $router->get('/daily-quotes/{id}/edit', [DailyQuotesController::class, 'edit'])->name('admin.daily_quotes.edit');
        $router->post('/daily-quotes/{id}', [DailyQuotesController::class, 'update'])->name('admin.daily_quotes.update');
        $router->post('/daily-quotes/{id}/delete', [DailyQuotesController::class, 'destroy'])->name('admin.daily_quotes.destroy');
        $router->get('/pages', [PagesController::class, 'index'])->name('admin.pages');
        $router->get('/pages/new', [PagesController::class, 'create'])->name('admin.pages.create');
        $router->post('/pages', [PagesController::class, 'store'])->name('admin.pages.store');
        $router->get('/pages/{id}/edit', [PagesController::class, 'edit'])->name('admin.pages.edit');
        $router->post('/pages/{id}', [PagesController::class, 'update'])->name('admin.pages.update');

        // Contact, newsletter
        $router->get('/contact-messages', [ContactMessagesController::class, 'index'])->name('admin.contact');
        $router->get('/contact-messages/{id}', [ContactMessagesController::class, 'show'])->name('admin.contact.show');
        $router->post('/contact-messages/{id}/status', [ContactMessagesController::class, 'status'])->name('admin.contact.status');
        $router->post('/contact-messages/{id}/reply', [ContactMessagesController::class, 'reply'])->name('admin.contact.reply');
        $router->post('/contact-messages/{id}/delete', [ContactMessagesController::class, 'destroy'])->name('admin.contact.destroy');
        $router->get('/newsletter', [AdminNewsletterController::class, 'index'])->name('admin.newsletter');
        $router->get('/newsletter/export', [AdminNewsletterController::class, 'export'])->name('admin.newsletter.export');
        $router->post('/newsletter/{id}/delete', [AdminNewsletterController::class, 'destroy'])->name('admin.newsletter.destroy');

        // Paramètres, journal, sauvegardes
        $router->get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
        $router->post('/settings/cache/clear', [SettingsController::class, 'clearCache'])->name('admin.cache.clear');
        $router->post('/settings/{group}', [SettingsController::class, 'update'])->name('admin.settings.update');
        $router->get('/activity-log', [ActivityLogController::class, 'index'])->name('admin.activity_log');
        $router->get('/backups', [BackupsController::class, 'index'])->name('admin.backups');
        $router->post('/backups', [BackupsController::class, 'store'])->name('admin.backups.store');
        $router->get('/backups/{name}/download', [BackupsController::class, 'download'])->name('admin.backups.download');
        $router->post('/backups/{name}/delete', [BackupsController::class, 'destroy'])->name('admin.backups.destroy');
    });
};
