<?php

declare(strict_types=1);

use App\Controllers\Account\DashboardController as AccountDashboardController;
use App\Controllers\Account\MyQuestionsController;
use App\Controllers\Account\NotificationsController;
use App\Controllers\Account\ProfileController;
use App\Controllers\Api\AudioUploadController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\PasswordController;
use App\Controllers\Auth\RegisterController;
use App\Controllers\Auth\VerificationController;
use App\Controllers\Front\ContactController;
use App\Controllers\Front\HomeController;
use App\Controllers\Front\MediaController;
use App\Controllers\Front\NewsletterController;
use App\Controllers\Front\PageController;
use App\Controllers\Front\QuestionController;
use App\Controllers\Front\RubriqueController;
use App\Controllers\Front\ScholarController;
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
    $router->localized('GET', ['fr' => '/professeur/profil', 'en' => '/scholar/profile'], [ScholarProfileController::class, 'edit'], 'scholar.profile', $scholar);
    $router->localized('POST', ['fr' => '/professeur/profil', 'en' => '/scholar/profile'], [ScholarProfileController::class, 'update'], 'scholar.profile.update', $scholar);

    // ---- Administration (§11.4) : tableau de bord provisoire, complété en Phase 5 -------------
    $router->group(['prefix' => '/admin', 'middleware' => 'role:moderator,admin'], static function (Router $router): void {
        $router->get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    });
};
