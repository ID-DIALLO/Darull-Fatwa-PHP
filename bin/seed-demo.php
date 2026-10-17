<?php

declare(strict_types=1);

/*
 * Données de démonstration et de recette (livrable §19.6) — JAMAIS en production :
 *   php bin/seed-demo.php            comptes de test + contenus d'exemple (forum, idées, article publié, Q/R)
 *   php bin/seed-demo.php --accounts comptes de test uniquement
 *
 * Comptes créés (mot de passe commun : Demo1234!) :
 *   superadmin@demo.test, admin@demo.test, moderateur@demo.test,
 *   professeur1@demo.test, professeur2@demo.test (profils publics, rubriques Coran / Hadiths),
 *   membre1@demo.test, +22370000002 (téléphone seul), membre3@demo.test
 */

require __DIR__ . '/_cli.php';

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Session;
use App\Services\ForumService;
use App\Services\IdeaService;
use App\Services\Installer;

$options = cli_options($argv);
$app = App::create($basePath);
Lang::setLocale('fr');
if ((string) $app->config('app.env') === 'production' && !isset($options['force'])) {
    cli_fail('APP_ENV=production : les données de démonstration ne sont pas installées (ajoutez --force si vous savez ce que vous faites).');
    exit(1);
}
try {
    Database::pdo();
} catch (PDOException $e) {
    cli_db_error($e, (array) $app->config('database'));
    exit(1);
}
$installer = new Installer($basePath);
$_SERVER['HTTP_HOST'] = parse_url((string) $app->config('app.url'), PHP_URL_HOST) ?: 'localhost';

cli_title('Comptes de test');
$accounts = [
    ['superadmin', 'Super Admin Démo', 'superadmin@demo.test', null],
    ['admin', 'Admin Démo', 'admin@demo.test', null],
    ['moderator', 'Modératrice Démo', 'moderateur@demo.test', null],
    ['scholar', 'Cheick Moussa Koné', 'professeur1@demo.test', null],
    ['scholar', 'Oustaz Ibrahim Diallo', 'professeur2@demo.test', null],
    ['member', 'Awa Diarra', 'membre1@demo.test', null],
    ['member', 'Mamadou Traoré', null, '+22370000002'],
    ['member', 'Fatoumata Sissoko', 'membre3@demo.test', null],
];
$ids = [];
foreach ($accounts as [$role, $name, $email, $phone]) {
    $existing = $email !== null ? Database::value('SELECT id FROM users WHERE email = ?', [$email]) : Database::value('SELECT id FROM users WHERE phone = ?', [$phone]);
    if ($existing !== null) {
        $ids[] = (int) $existing;
        cli_warn($name . ' existe déjà');
        continue;
    }
    $id = $installer->createAdmin(['full_name' => $name, 'email' => (string) $email, 'phone' => (string) $phone, 'password' => 'Demo1234!', 'locale' => 'fr'], $role);
    $ids[] = $id;
    cli_ok($name . ' (' . $role . ') — ' . ($email ?? $phone));
}
[$superadmin, $admin, $moderator, $scholar1, $scholar2, $member1, $member2, $member3] = $ids;
Database::execute("UPDATE users SET display_name = 'Oum Fatima' WHERE id = ?", [$member1]);
foreach ([[$scholar1, 3, 'fr,ar', 'Cheick', 'Diplômé de l\'Université islamique de Médine, enseigne le fiqh et le tafsir à Bamako depuis vingt ans.'], [$scholar2, 4, 'fr,bm', 'Oustaz', 'Imam et enseignant à Ségou, spécialiste des sciences du hadith.']] as [$uid, $rubrique, $langs, $title, $bio]) {
    Database::execute("INSERT IGNORE INTO scholar_profiles (user_id, languages, can_self_assign, requires_review, is_public) VALUES (?, ?, 1, ?, 1)", [$uid, $langs, $uid === $scholar2 ? 1 : 0]);
    Database::execute('INSERT IGNORE INTO scholar_rubriques (user_id, rubrique_id) VALUES (?, ?)', [$uid, $rubrique]);
    Database::execute("INSERT IGNORE INTO scholar_translations (user_id, locale, title, bio) VALUES (?, 'fr', ?, ?)", [$uid, $title, $bio]);
}
cli_ok('Profils des deux professeurs (publics ; le second est soumis à relecture).');

if (isset($options['accounts'])) {
    exit(0);
}

$as = static function (int $id): array {
    Session::reset();
    Auth::reset();
    Auth::login($id);

    return Auth::user();
};

cli_title('Forum et idées');
if ((int) Database::value('SELECT COUNT(*) FROM forum_topics') === 0) {
    $u = $as($scholar1);
    $t1 = ForumService::createTopic($u, ['category_id' => 3, 'title' => 'Comment organiser un cercle de lecture du Coran dans son quartier ?', 'body' => "Assalamu alaykum,\n\nJe souhaite lancer un cercle hebdomadaire de lecture et de méditation du Coran. Quels conseils pratiques pour la durée, le rythme et le choix des sourates ?", 'language' => 'fr']);
    $u = $as($admin);
    $t3 = ForumService::createTopic($u, ['category_id' => 7, 'title' => 'Bienvenue sur le forum : présentez-vous !', 'body' => "Ce sujet est fait pour les nouveaux membres : dites-nous d'où vous écrivez et ce que vous attendez de la plateforme.", 'language' => 'fr']);
    ForumService::updateTopic($t3, ['is_pinned' => true], $admin);
    ForumService::createTopic($u, ['category_id' => 8, 'title' => 'كيف ننظّم حلقة لتحفيظ القرآن للأطفال؟', 'body' => 'السلام عليكم، نريد تنظيم حلقة أسبوعية لتحفيظ القرآن للأطفال في الحي. ما هي نصائحكم العملية؟', 'language' => 'ar']);
    $p1 = ForumService::reply(ForumService::topic($t1), $u, 'Barak Allahu fik. Commencez court (45 minutes), une fois par semaine, et choisissez une sourate que tout le monde peut suivre.', null);
    $u = $as($scholar1);
    $p2 = ForumService::reply(ForumService::topic($t1), $u, 'Je confirme : la régularité compte plus que la durée. Prévoyez un temps de questions à la fin, en présence d\'une personne formée.', $p1);
    ForumService::validateAnswer($p2, $scholar1, true);
    $u = $as($member1);
    ForumService::reply(ForumService::topic($t1), $u, 'Merci beaucoup pour ces conseils, je vais proposer cela à la mosquée du quartier.', null);
    ForumService::toggleLike($member1, 'forum_post', $p2);
    cli_ok('3 sujets, 3 réponses (dont une validée et une en attente de modération).');
}
if ((int) Database::value('SELECT COUNT(*) FROM ideas') === 0) {
    $u = $as($member1);
    $i1 = IdeaService::create($u, 'platform', 'Ajouter un mode sombre au site', 'Un thème sombre faciliterait la lecture le soir et réduirait la fatigue visuelle, surtout sur téléphone.');
    $i2 = IdeaService::create($u, 'content', 'Une série d\'articles sur les quarante hadiths', 'Publier chaque semaine un article détaillé sur l\'un des quarante hadiths de l\'imam An-Nawawî.');
    $u = $as($scholar2);
    $i3 = IdeaService::create($u, 'event', 'Une rencontre mensuelle en direct avec un professeur', 'Organiser une session vidéo mensuelle de questions-réponses en direct.');
    IdeaService::setStatus($i1, 'accepted', 'Excellente idée : le mode sombre est prévu pour la prochaine version du site.', $admin);
    IdeaService::setStatus($i2, 'under_review', null, $admin);
    IdeaService::setStatus($i3, 'new', null, $admin);
    IdeaService::toggleVote($admin, $i1);
    IdeaService::toggleVote($scholar1, $i1);
    IdeaService::toggleVote($member1, $i2);
    cli_ok('3 idées (retenue, à l\'étude, nouvelle) avec des votes.');
}

cli_title('Blog et Q/R publiques');
if ((int) Database::value("SELECT COUNT(*) FROM articles WHERE status = 'published'") === 0) {
    // Les trois articles initiaux (brouillons du seed) sont publiés et attribués au premier professeur
    Database::execute("UPDATE articles SET status = 'published', author_id = ?, published_at = UTC_TIMESTAMP() WHERE status = 'draft' AND author_id IS NULL", [$scholar1]);
    cli_ok('Articles initiaux publiés au nom du premier professeur.');
}
if ((int) Database::value('SELECT COUNT(*) FROM qa_publications') === 0) {
    Database::execute("INSERT INTO qa_publications (question_id, rubrique_id, scholar_id, status, published_at, created_by, views) VALUES (NULL, 3, ?, 'published', UTC_TIMESTAMP(), ?, 12)", [$scholar1, $admin]);
    $qa = Database::lastInsertId();
    Database::execute("INSERT INTO qa_publication_translations (publication_id, locale, title, slug, question_text, answer_html) VALUES (?, 'fr', ?, ?, ?, ?)", [$qa, 'Lire le Coran sur un téléphone sans ablutions', 'lire-le-coran-sur-un-telephone-sans-ablutions', 'Peut-on lire le Coran depuis une application sur son téléphone sans avoir fait ses ablutions ?', '<p>La majorité des savants contemporains considèrent que le téléphone n\'a pas le statut du <em>mushaf</em> : la lecture depuis une application est permise sans ablutions, même si les faire reste préférable par respect pour la parole d\'Allah.</p><p><strong>Références :</strong> fatwas du Comité permanent (Arabie saoudite) n° 19599 ; avis de Dar al-Ifta d\'Égypte.</p>']);
    cli_ok('1 question-réponse publique publiée.');
}
\App\Core\Cache::flush('');
cli_line();
cli_ok('Données de démonstration en place. Mot de passe de tous les comptes : Demo1234!');
