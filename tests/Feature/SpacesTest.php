<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Services\AnswerService;
use App\Services\AssignmentService;
use Tests\Support\DatabaseTestCase;

/**
 * Espaces membre et professeur (demande du 18/10) : question adressée à un professeur précis, « répondue par qui et
 * quand », visibilité modifiable, origine WhatsApp/Darul Fatwa, réponse privée ou publique, pages statistiques,
 * commentaires et débats du professeur.
 */
final class SpacesTest extends DatabaseTestCase
{
    private int $member;
    private int $scholar;
    private int $other;
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createUser(['email' => 'awa@example.ml', 'full_name' => 'Awa Diarra', 'display_name' => 'Oum Fatima', 'locale' => 'fr']);
        $this->scholar = $this->createUser(['email' => 'cheick@example.ml', 'full_name' => 'Cheick Moussa Koné', 'role_id' => 2, 'locale' => 'fr']);
        $this->other = $this->createUser(['email' => 'oustaz@example.ml', 'full_name' => 'Oustaz Ibrahim Diallo', 'role_id' => 2, 'locale' => 'fr']);
        $this->admin = $this->createUser(['email' => 'admin@example.ml', 'full_name' => 'Admin', 'role_id' => 4]);
        // Le premier professeur n'a pas l'auto-attribution ; le second oui
        Database::execute("INSERT INTO scholar_profiles (user_id, languages, can_self_assign, is_public, is_available) VALUES (?, 'fr,ar', 0, 1, 1)", [$this->scholar]);
        Database::execute("INSERT INTO scholar_profiles (user_id, languages, can_self_assign, is_public, is_available) VALUES (?, 'fr', 1, 1, 1)", [$this->other]);
        Database::execute("INSERT INTO scholar_translations (user_id, locale, title) VALUES (?, 'fr', 'Cheick')", [$this->scholar]);
        Database::execute('INSERT INTO scholar_rubriques (user_id, rubrique_id) VALUES (?, 3), (?, 4)', [$this->scholar, $this->other]);
        \App\Models\Setting::set('auto_assign', '0', 'questions');
        \App\Core\Cache::flush('');
    }

    private function switchTo(?int $id): void
    {
        Session::reset();
        Auth::reset();
        if ($id !== null) {
            $this->login($id);
        }
    }

    public function testLeMembreAdresseSaQuestionAUnProfesseurQuiLaRecoitDirectement(): void
    {
        $this->switchTo($this->member);
        $form = $this->get('/fr/poser-une-question?professeur=' . $this->scholar)->body();
        self::assertStringContainsString('name="requested_scholar_id"', $form);
        self::assertStringContainsString('value="' . $this->scholar . '" data-rubriques="3" selected', $form);
        self::assertStringContainsString('Cheick Cheick Moussa Koné', $form);

        $this->post('/fr/poser-une-question', ['rubrique_id' => 3, 'body' => 'Puis-je lire le Coran sur mon téléphone sans ablutions ?', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'publishable', 'requested_scholar_id' => $this->scholar]);
        $question = Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');
        self::assertSame($this->scholar, (int) $question['requested_scholar_id']);
        // Assignée directement au professeur demandé, même sans assignation automatique générale
        self::assertSame('assigned', $question['status']);
        self::assertSame($this->scholar, (int) $question['assigned_to']);
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'question.requested'", [$this->scholar]));
        self::assertSame('Professeur demandé par le membre', Database::value('SELECT reason FROM question_assignments WHERE question_id = ?', [(int) $question['id']]));

        // Le membre voit qui suit sa question
        self::assertStringContainsString(__('questions.detail.handled_by', ['name' => 'Cheick Cheick Moussa Koné']), $this->get('/fr/mon-espace/questions/' . $question['reference'])->body());

        // L'administration voit le souhait du membre
        $this->switchTo($this->admin);
        self::assertStringContainsString(__('admin.questions.requested_scholar'), $this->get('/admin/questions/' . $question['id'])->body());

        // Un professeur demandé mais surchargé la reçoit quand même : elle doit apparaître dans son espace
        // (un professeur marqué indisponible n'est simplement plus proposé dans le formulaire)
        Database::execute('UPDATE scholar_profiles SET max_open_questions = 1 WHERE user_id = ?', [$this->scholar]);
        $this->switchTo($this->member);
        $this->post('/fr/poser-une-question', ['rubrique_id' => 3, 'body' => 'Seconde question adressée au même professeur, pour la file.', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private', 'requested_scholar_id' => $this->scholar]);
        $second = Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');
        self::assertNotSame((int) $question['id'], (int) $second['id']);
        self::assertSame('assigned', $second['status']);
        self::assertSame($this->scholar, (int) $second['assigned_to']);
        $this->switchTo($this->scholar);
        self::assertStringContainsString($second['reference'], $this->get('/fr/professeur/questions')->body(), 'visible dans « Mes questions » du professeur demandé');
        self::assertStringNotContainsString($second['reference'], $this->get('/fr/professeur/questions?file=a-prendre')->body());
        $this->switchTo($this->admin);
        self::assertStringContainsString($second['reference'], $this->get('/admin/questions')->body(), 'visible par l\'administration');

        // Remise dans la file par l'administration (désattribution) : la question reste adressée à ce professeur,
        // lui seul peut la reprendre lui-même et elle apparaît dans sa file « À prendre »
        Database::execute("UPDATE questions SET status = 'new', assigned_to = NULL WHERE id = ?", [(int) $second['id']]);
        $third = Database::fetch('SELECT * FROM questions WHERE id = ?', [(int) $second['id']]);
        self::assertTrue(AssignmentService::canSelfAssign($third, $this->scholar));
        self::assertFalse(AssignmentService::canSelfAssign($third, $this->other));
        $this->switchTo($this->scholar);
        $available = $this->get('/fr/professeur/questions?file=a-prendre')->body();
        self::assertStringContainsString($third['reference'], $available, 'adressée à lui : visible dans « À prendre » malgré can_self_assign = 0');
        self::assertStringContainsString(__('scholar.question.requested_you'), $available);

        // Un professeur inconnu ou non public est refusé
        $this->switchTo($this->member);
        $this->post('/fr/poser-une-question', ['rubrique_id' => 3, 'body' => 'Question avec un professeur inexistant, qui doit être refusée.', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private', 'requested_scholar_id' => $this->admin]);
        self::assertSame(2, (int) Database::value('SELECT COUNT(*) FROM questions'));
    }

    public function testLeMembreVoitQuiARepondEtQuandEtPeutRepliquerEtChangerLaVisibilite(): void
    {
        $this->switchTo($this->member);
        $this->post('/fr/poser-une-question', ['rubrique_id' => 3, 'body' => 'Puis-je lire le Coran sur mon téléphone sans ablutions ?', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private']);
        $question = Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');

        // Visibilité modifiable par le membre
        $this->post('/fr/mon-espace/questions/' . $question['reference'] . '/visibilite', ['visibility' => 'publishable']);
        self::assertSame('publishable', Database::value('SELECT visibility FROM questions WHERE id = ?', [(int) $question['id']]));
        $page = $this->get('/fr/mon-espace/questions/' . $question['reference'])->body();
        self::assertStringContainsString(__('questions.detail.visibility_publishable'), $page);
        self::assertStringContainsString(__('questions.detail.visibility_make_private'), $page);

        $this->switchTo($this->admin);
        $question = AssignmentService::assign(Database::fetch('SELECT * FROM questions WHERE id = ?', [(int) $question['id']]), $this->scholar, $this->admin);
        $this->switchTo($this->scholar);
        $this->get('/fr/professeur/questions/' . $question['reference']);
        AnswerService::answer(Database::fetch('SELECT * FROM questions WHERE id = ?', [(int) $question['id']]), Auth::user(), '<p>Oui, la lecture depuis un téléphone est permise sans ablutions.</p>', null, [], 'fr');

        // Le membre voit « répondue par … le … » dans sa liste et sur la fiche, puis réplique
        $this->switchTo($this->member);
        $answered = Database::fetch('SELECT answered_at FROM questions WHERE id = ?', [(int) $question['id']]);
        self::assertNotNull($answered['answered_at']);
        $expectedList = __('questions.list.answered_by', ['name' => 'Cheick Cheick Moussa Koné', 'date' => format_date((string) $answered['answered_at'])]);
        self::assertStringContainsString($expectedList, $this->get('/fr/mon-espace/questions')->body());
        $detail = $this->get('/fr/mon-espace/questions/' . $question['reference'])->body();
        self::assertStringContainsString(__('questions.detail.answered_by', ['name' => 'Cheick Cheick Moussa Koné', 'date' => format_date((string) $answered['answered_at'], true)]), $detail);
        $this->post('/fr/mon-espace/questions/' . $question['reference'] . '/precision', ['body' => 'Merci. Et si le téléphone contient une application de Coran uniquement en arabe ?']);
        self::assertSame('in_progress', Database::value('SELECT status FROM questions WHERE id = ?', [(int) $question['id']]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM question_messages WHERE question_id = ? AND kind = 'follow_up'", [(int) $question['id']]));
    }

    public function testLeProfesseurVoitLOrigineEtPeutRepondreEnPublic(): void
    {
        $this->switchTo($this->member);
        $this->post('/fr/poser-une-question', ['rubrique_id' => 3, 'title' => 'Lire le Coran sur un téléphone', 'body' => 'Puis-je lire le Coran sur mon téléphone sans ablutions ?', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'publishable']);
        $question = Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');
        Database::execute("UPDATE questions SET source = 'whatsapp' WHERE id = ?", [(int) $question['id']]);
        $this->switchTo($this->admin);
        $question = AssignmentService::assign(Database::fetch('SELECT * FROM questions WHERE id = ?', [(int) $question['id']]), $this->scholar, $this->admin);

        $this->switchTo($this->scholar);
        $queue = $this->get('/fr/professeur/questions')->body();
        self::assertStringContainsString(__('scholar.question.source_whatsapp'), $queue);
        $sheet = $this->get('/fr/professeur/questions/' . $question['reference'])->body();
        self::assertStringContainsString(__('scholar.question.source_whatsapp'), $sheet);
        self::assertStringContainsString('name="propose_public"', $sheet, 'réponse publique proposable : le membre a accepté la publication');
        self::assertStringContainsString('Oum Fatima', $sheet, 'le professeur voit qui a posé la question (pseudonyme ou nom)');

        // Réponse « publique » : réponse envoyée + proposition de publication transmise à l'administration
        $response = $this->post('/fr/professeur/questions/' . $question['reference'] . '/repondre', ['body_html' => '<p>Oui, c\'est permis : le téléphone n\'a pas le statut du mushaf.</p>', 'answer_language' => 'fr', 'references' => '[]', 'propose_public' => '1']);
        self::assertSame(302, $response->status());
        self::assertSame('answered', Database::value('SELECT status FROM questions WHERE id = ?', [(int) $question['id']]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM activity_logs WHERE action = 'question.publication_proposed' AND subject_id = ?", [(int) $question['id']]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'question.publication_proposed'", [$this->admin]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM question_messages WHERE question_id = ? AND kind = 'internal_note'", [(int) $question['id']]));

        // Question privée : pas de proposition possible
        $this->switchTo($this->member);
        $this->post('/fr/poser-une-question', ['rubrique_id' => 3, 'body' => 'Une question privée qui ne doit jamais être proposée en publication.', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private']);
        $private = Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');
        $this->switchTo($this->admin);
        $private = AssignmentService::assign($private, $this->scholar, $this->admin);
        $this->switchTo($this->scholar);
        self::assertStringContainsString(e(__('scholar.actions.private_only')), $this->get('/fr/professeur/questions/' . $private['reference'])->body());
        $this->post('/fr/professeur/questions/' . $private['reference'] . '/repondre', ['body_html' => '<p>Réponse privée.</p>', 'answer_language' => 'fr', 'references' => '[]', 'propose_public' => '1']);
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM activity_logs WHERE action = 'question.publication_proposed'"), 'la case cochée est ignorée pour une question privée');
    }

    public function testLeProfesseurNeVoitLeNomReelQueDesMembresAuxquelsIlARepondu(): void
    {
        // Membre sans pseudonyme : avant la réponse, le professeur ne voit que « le demandeur »
        $anonymous = $this->createUser(['email' => 'mamadou@example.ml', 'full_name' => 'Mamadou Traoré', 'display_name' => null]);
        $this->switchTo($anonymous);
        $this->post('/fr/poser-une-question', ['rubrique_id' => 3, 'body' => 'Question posée par un membre sans pseudonyme, pour vérifier le nom.', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private']);
        $question = Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');
        $this->switchTo($this->admin);
        $question = AssignmentService::assign($question, $this->scholar, $this->admin);

        $this->switchTo($this->scholar);
        self::assertStringNotContainsString('Mamadou Traoré', $this->get('/fr/professeur/questions')->body());
        $sheet = $this->get('/fr/professeur/questions/' . $question['reference'])->body();
        self::assertStringNotContainsString('Mamadou Traoré', $sheet);
        self::assertStringContainsString(__('scholar.question.member_anonymous'), $sheet);

        // Après sa réponse : nom réel visible dans la fiche et dans sa file
        AnswerService::answer(Database::fetch('SELECT * FROM questions WHERE id = ?', [(int) $question['id']]), Auth::user(), '<p>Réponse.</p>', null, [], 'fr');
        self::assertStringContainsString('Mamadou Traoré', $this->get('/fr/professeur/questions/' . $question['reference'])->body());
        self::assertStringContainsString('Mamadou Traoré', $this->get('/fr/professeur/questions?statut=done')->body());

        // Un autre professeur n'a pas accès à cette question (et n'en verrait donc pas le nom)
        $this->switchTo($this->other);
        self::assertSame(404, $this->get('/fr/professeur/questions/' . $question['reference'])->status());

        // Membre avec pseudonyme, question non répondue : seul le pseudonyme apparaît
        $this->switchTo($this->member);
        $this->post('/fr/poser-une-question', ['rubrique_id' => 3, 'body' => 'Seconde question, membre avec pseudonyme, non répondue pour le test.', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private']);
        $q2 = Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');
        $this->switchTo($this->admin);
        AssignmentService::assign($q2, $this->scholar, $this->admin);
        $this->switchTo($this->scholar);
        $sheet = $this->get('/fr/professeur/questions/' . $q2['reference'])->body();
        self::assertStringContainsString('Oum Fatima', $sheet);
        self::assertStringNotContainsString('Awa Diarra', $sheet);
    }

    public function testLesPagesStatistiquesCommentairesEtDebatsDuProfesseur(): void
    {
        $this->switchTo($this->member);
        $this->post('/fr/poser-une-question', ['rubrique_id' => 3, 'body' => 'Puis-je lire le Coran sur mon téléphone sans ablutions ?', 'spoken_language' => 'ar', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private']);
        $question = Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');
        $this->switchTo($this->admin);
        $question = AssignmentService::assign($question, $this->scholar, $this->admin);
        $this->switchTo($this->scholar);
        $this->get('/fr/professeur/questions/' . $question['reference']);
        AnswerService::answer(Database::fetch('SELECT * FROM questions WHERE id = ?', [(int) $question['id']]), Auth::user(), '<p>Réponse.</p>', null, [], 'fr');
        $this->post('/fr/forum/nouveau-sujet', ['category_id' => 3, 'title' => 'Faut-il apprendre le tajwid avant de mémoriser ?', 'body' => 'Question à débattre avec la communauté : votre expérience ?', 'language' => 'fr', 'accept_rules' => '1']);

        $stats = $this->get('/fr/professeur/statistiques');
        self::assertSame(200, $stats->status());
        self::assertStringContainsString('id="scholar-stats-data"', $stats->body());
        self::assertStringContainsString(__('questions.languages.ar'), $stats->body());
        $data = \App\Services\ScholarService::statistics($this->scholar);
        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['answered']);
        self::assertSame(1, array_sum(array_column($data['months'], 'answered')));
        self::assertSame('web', $data['by_source'][0]['source']);

        self::assertSame(200, $this->get('/fr/professeur/commentaires')->status());
        $debates = $this->get('/fr/professeur/debats');
        self::assertSame(200, $debates->status());
        self::assertStringContainsString('tajwid', $debates->body());
        foreach (['/ar/scholar/statistics', '/en/scholar/comments', '/en/scholar/debates'] as $uri) {
            self::assertSame(200, $this->get($uri)->status(), $uri);
        }
        // Le menu de l'espace professeur propose les nouvelles pages
        $dashboard = $this->get('/fr/professeur')->body();
        foreach (['scholar.nav.stats', 'scholar.nav.comments', 'scholar.nav.debates'] as $key) {
            self::assertStringContainsString(__($key), $dashboard);
        }
        // Un membre n'y a pas accès
        $this->switchTo($this->member);
        self::assertSame(403, $this->get('/fr/professeur/statistiques')->status());
    }

    public function testLEquipeNePosePasDeQuestionsEtArriveDansSonEspaceALaConnexion(): void
    {
        // Le professeur est renvoyé vers son espace avec un message ; aucune question créée
        $this->switchTo($this->scholar);
        $get = $this->get('/fr/poser-une-question');
        self::assertSame(302, $get->status());
        self::assertSame('/fr/professeur', $get->getHeader('Location'));
        $this->post('/fr/poser-une-question', ['rubrique_id' => 3, 'body' => 'Un professeur essaie de poser une question.', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private']);
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM questions'));
        self::assertContains(__('questions.errors.staff_cannot_ask'), $this->flashMessages());

        // Aucun bouton « Poser une question » pour l'équipe ; raccourci vers l'espace à la place
        $home = $this->get('/fr')->body();
        self::assertStringNotContainsString('/fr/poser-une-question', $home);
        self::assertStringContainsString(e(__('nav.scholar_space')), $home);
        self::assertStringNotContainsString('/fr/poser-une-question', $this->get('/fr/professeurs/' . $this->scholar)->body() . $this->get('/fr/contact')->body());
        $dashboard = $this->get('/fr/mon-espace')->body();
        self::assertStringNotContainsString('/fr/poser-une-question', $dashboard);
        self::assertStringContainsString(e(__('account.dashboard.staff_title')), $dashboard);

        $this->switchTo($this->admin);
        self::assertSame('/admin', $this->get('/fr/poser-une-question')->getHeader('Location'));
        self::assertStringNotContainsString('/fr/poser-une-question', $this->get('/fr')->body());

        // Le membre garde ses boutons
        $this->switchTo($this->member);
        self::assertStringContainsString('/fr/poser-une-question', $this->get('/fr')->body());
        self::assertSame(200, $this->get('/fr/poser-une-question')->status());

        // Connexion : chacun arrive dans son espace
        foreach ([$this->scholar => '/fr/professeur', $this->admin => '/admin', $this->member => '/fr/mon-espace'] as $id => $target) {
            $this->switchTo(null);
            Database::execute('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash('MotDePasse123!', PASSWORD_DEFAULT), $id]);
            $email = (string) Database::value('SELECT email FROM users WHERE id = ?', [$id]);
            $login = $this->post('/fr/connexion', ['identifier' => $email, 'password' => 'MotDePasse123!']);
            self::assertSame(302, $login->status(), $email);
            self::assertSame($target, $login->getHeader('Location'), $email);
        }
    }
}
