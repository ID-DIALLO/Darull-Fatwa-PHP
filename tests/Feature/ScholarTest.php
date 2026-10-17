<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Models\Question;
use App\Services\AnswerService;
use App\Services\AssignmentService;
use App\Services\MailService;
use App\Services\Maintenance;
use App\Services\QueueService;
use App\Services\WhatsApp\LinkGateway;
use App\Services\WhatsApp\WhatsApp;
use Tests\Support\DatabaseTestCase;

/**
 * Phase 4 : parcours complet (question → assignation → réponse → notification → lecture), relecture obligatoire,
 * numéro masqué hors canal WhatsApp, lien wa.me prérempli, auto-attribution, assignation automatique, cron.
 */
final class ScholarTest extends DatabaseTestCase
{
    private int $member;
    private int $scholar;
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createUser(['email' => 'awa@example.ml', 'full_name' => 'Awa Diarra', 'display_name' => 'Oum Fatima', 'locale' => 'fr']);
        $this->scholar = $this->createUser(['email' => 'cheick@example.ml', 'full_name' => 'Cheick Moussa Koné', 'role_id' => 2, 'locale' => 'fr']);
        $this->admin = $this->createUser(['email' => 'admin@example.ml', 'full_name' => 'Admin', 'role_id' => 4]);
        Database::execute("INSERT INTO scholar_profiles (user_id, languages, can_self_assign) VALUES (?, 'fr,ar', 1)", [$this->scholar]);
        Database::execute('INSERT INTO scholar_rubriques (user_id, rubrique_id) VALUES (?, 3)', [$this->scholar]);
    }

    /** @return array<string, mixed> */
    private function askQuestion(array $overrides = []): array
    {
        $this->login($this->member);
        $this->post('/fr/poser-une-question', $overrides + ['rubrique_id' => 3, 'body' => 'Puis-je lire le Coran sur mon téléphone sans ablutions ?', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'publishable']);
        Session::reset();
        Auth::reset();

        return Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');
    }

    public function testFullJourneyFromQuestionToAnswerReadByMember(): void
    {
        $question = $this->askQuestion();
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->admin, 'question.new_for_staff']));

        // L'administrateur assigne
        $this->login($this->admin);
        $question = AssignmentService::assign($question, $this->scholar, $this->admin);
        self::assertSame('assigned', $question['status']);
        self::assertNotNull($question['assigned_at']);
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->scholar, 'question.assigned']));
        Session::reset();
        Auth::reset();

        // Le professeur voit la question dans sa file, l'ouvre (→ en cours), répond en vocal + références
        $this->login($this->scholar);
        self::assertStringContainsString($question['reference'], $this->get('/fr/professeur/questions')->body());
        $sheet = $this->get('/fr/professeur/questions/' . $question['reference']);
        self::assertSame(200, $sheet->status());
        self::assertStringContainsString('Oum Fatima', $sheet->body(), 'Pseudonyme du demandeur');
        self::assertStringNotContainsString('Awa Diarra', $sheet->body());
        self::assertSame('in_progress', Database::value('SELECT status FROM questions WHERE id = ?', [(int) $question['id']]));

        $upload = $this->uploadAudio();
        $uuid = json_decode($upload->body(), true)['media_uuid'];
        $response = $this->post('/fr/professeur/questions/' . $question['reference'] . '/repondre', [
            'body_html'       => '<p>Oui, <strong>la lecture</strong> sur écran est permise.</p><script>alert(1)</script>',
            'media_uuid'      => $uuid,
            'answer_language' => 'fr',
            'references'      => json_encode([
                ['type' => 'quran', 'surah' => '56', 'ayah_from' => '79', 'ayah_to' => '79'],
                ['type' => 'hadith', 'collection' => 'bukhari', 'number' => '1', 'grade' => 'sahih'],
                ['type' => 'book', 'title' => 'Al-Majmu', 'author' => 'An-Nawawi', 'page' => '2/67'],
                ['type' => 'quran', 'surah' => '999', 'ayah_from' => '1'],
            ]),
        ]);
        self::assertSame(302, $response->status(), json_encode(Session::getFlash('_errors')));
        $question = Question::find((int) $question['id']);
        self::assertSame('answered', $question['status']);
        self::assertNotNull($question['answered_at']);
        self::assertNotNull($question['answer_message_id']);
        $answer = Database::fetch('SELECT * FROM question_messages WHERE id = ?', [(int) $question['answer_message_id']]);
        self::assertStringNotContainsString('<script', $answer['body']);
        self::assertStringContainsString('<strong>la lecture</strong>', $answer['body']);
        self::assertCount(3, json_decode($answer['references_json'], true), 'La référence invalide est ignorée');
        self::assertSame('not_required', $answer['review_status']);

        // Le membre est notifié (cloche + e-mail) et lit la réponse avec les références
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->member, 'question.answered']));
        QueueService::process();
        $mails = array_filter(MailService::$sent, static fn (array $m): bool => $m['to'] === 'awa@example.ml' && str_contains($m['subject'], 'Réponse disponible'));
        self::assertCount(1, $mails);
        Session::reset();
        Auth::reset();
        $this->login($this->member);
        $detail = $this->get('/fr/mon-espace/questions/' . $question['reference'])->body();
        self::assertStringContainsString('Réponse du professeur', $detail);
        self::assertStringContainsString('la lecture', $detail);
        self::assertStringContainsString('Coran, sourate Al-Wâqiʿa (56), verset(s) 79', $detail);
        self::assertStringContainsString('Hadith rapporté par Al-Bukhari, n° 1 (sahih)', $detail);
        self::assertStringContainsString('/media/audio/' . $uuid, $detail);
        self::assertSame(200, $this->get('/media/audio/' . $uuid)->status(), 'Le membre écoute la réponse vocale');
    }

    public function testReviewRequiredHidesTheAnswerUntilAdminApproval(): void
    {
        Database::execute('UPDATE scholar_profiles SET requires_review = 1 WHERE user_id = ?', [$this->scholar]);
        $question = $this->askQuestion();
        $question = AssignmentService::assign($question, $this->scholar, $this->admin);

        $this->login($this->scholar);
        $this->post('/fr/professeur/questions/' . $question['reference'] . '/repondre', ['body_html' => '<p>Réponse à relire.</p>', 'answer_language' => 'fr']);
        $question = Question::find((int) $question['id']);
        self::assertSame('in_progress', $question['status'], 'Pas encore répondue');
        $pending = AnswerService::pendingReview((int) $question['id']);
        self::assertNotNull($pending);
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->admin, 'question.review_pending']));
        self::assertStringContainsString('attend la validation', $this->get('/fr/professeur/questions/' . $question['reference'])->body());

        Session::reset();
        Auth::reset();
        $this->login($this->member);
        $detail = $this->get('/fr/mon-espace/questions/' . $question['reference'])->body();
        self::assertStringNotContainsString('Réponse à relire', $detail, 'Invisible pour le membre');
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->member, 'question.answered']));

        // Validation par l'administrateur
        Session::reset();
        Auth::reset();
        $this->login($this->admin);
        $question = AnswerService::approve($question, (int) $pending['id'], $this->admin);
        self::assertSame('answered', $question['status']);
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->member, 'question.answered']));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->scholar, 'question.review_approved']));
        Session::reset();
        Auth::reset();
        $this->login($this->member);
        self::assertStringContainsString('Réponse à relire', $this->get('/fr/mon-espace/questions/' . $question['reference'])->body());
    }

    public function testReviewRejectionLeavesInternalNoteInvisibleToMember(): void
    {
        Database::execute('UPDATE scholar_profiles SET requires_review = 1 WHERE user_id = ?', [$this->scholar]);
        $question = AssignmentService::assign($this->askQuestion(), $this->scholar, $this->admin);
        $this->login($this->scholar);
        $this->post('/fr/professeur/questions/' . $question['reference'] . '/repondre', ['body_html' => '<p>Brouillon.</p>', 'answer_language' => 'fr']);
        $pending = AnswerService::pendingReview((int) $question['id']);
        AnswerService::reject(Question::find((int) $question['id']), (int) $pending['id'], $this->admin, 'Merci de citer la source.');

        self::assertStringContainsString('Merci de citer la source.', $this->get('/fr/professeur/questions/' . $question['reference'])->body());
        Session::reset();
        Auth::reset();
        $this->login($this->member);
        self::assertStringNotContainsString('Merci de citer la source.', $this->get('/fr/mon-espace/questions/' . $question['reference'])->body());
    }

    public function testForbiddenTransitionIsNotRecorded(): void
    {
        $question = $this->askQuestion();
        $this->expectException(\App\Services\WorkflowException::class);
        try {
            \App\Services\QuestionWorkflow::transition($question, 'answered', $this->scholar);
        } finally {
            self::assertSame('new', Database::value('SELECT status FROM questions WHERE id = ?', [(int) $question['id']]));
            self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM question_status_logs WHERE question_id = ?', [(int) $question['id']]), 'Seule la création est journalisée');
        }
    }

    public function testPhoneIsMaskedUnlessWhatsAppChannelWasChosen(): void
    {
        Database::execute('UPDATE users SET phone = ?, whatsapp = ? WHERE id = ?', ['+22376123412', '+22376123412', $this->member]);
        $platform = $this->askQuestion(['answer_channel' => 'platform']);
        $whatsapp = $this->askQuestion(['answer_channel' => 'whatsapp', 'whatsapp_number' => '+22376123412']);
        AssignmentService::assign($platform, $this->scholar, $this->admin);
        AssignmentService::assign($whatsapp, $this->scholar, $this->admin);

        $this->login($this->scholar);
        $sheet1 = $this->get('/fr/professeur/questions/' . $platform['reference'])->body();
        self::assertStringNotContainsString('76 12 34 12', $sheet1);
        self::assertStringNotContainsString('+22376123412', $sheet1);
        self::assertStringContainsString('masqué', $sheet1);

        $sheet2 = $this->get('/fr/professeur/questions/' . $whatsapp['reference'])->body();
        self::assertStringContainsString('+223 76 12 34 12', $sheet2);

        // Après réponse : lien wa.me prérempli dans la langue de la réponse, puis « marqué comme envoyé »
        $this->post('/fr/professeur/questions/' . $whatsapp['reference'] . '/repondre', ['body_html' => '<p>Réponse.</p>', 'answer_language' => 'fr']);
        $sheet3 = $this->get('/fr/professeur/questions/' . $whatsapp['reference'])->body();
        self::assertStringContainsString('https://wa.me/22376123412?text=', $sheet3);
        self::assertStringContainsString(rawurlencode('Votre question ' . $whatsapp['reference']), $sheet3);
        self::assertStringContainsString(rawurlencode(url('/fr/mon-espace/questions/' . $whatsapp['reference'])), $sheet3);

        $this->post('/fr/professeur/questions/' . $whatsapp['reference'] . '/whatsapp-envoye', ['text' => 'Assalamou alaykoum…']);
        self::assertNotNull(Database::value('SELECT sent_via_whatsapp_at FROM question_messages WHERE question_id = ? AND kind = ?', [(int) $whatsapp['id'], 'answer']));
        $log = Database::fetch('SELECT * FROM whatsapp_messages');
        self::assertSame('out', $log['direction']);
        self::assertSame('link', $log['mode']);
        self::assertSame('+22376123412', $log['phone']);
        self::assertSame((int) $whatsapp['id'], (int) $log['question_id']);

        // Sans canal WhatsApp : refus
        $this->post('/fr/professeur/questions/' . $platform['reference'] . '/repondre', ['body_html' => '<p>Réponse.</p>', 'answer_language' => 'fr']);
        $this->post('/fr/professeur/questions/' . $platform['reference'] . '/whatsapp-envoye');
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM whatsapp_messages'));
    }

    public function testWhatsAppLinkIsInTheAnswerLanguage(): void
    {
        $question = ['reference' => 'DF-2026-000009', 'answer_language' => 'ar'];
        $text = WhatsApp::scholarReplyText($question, 'ar', 'ملخص');
        self::assertStringContainsString('السلام عليكم', $text);
        self::assertStringContainsString('/ar/my-account/questions/DF-2026-000009', $text);
        $link = (new LinkGateway())->conversationLink('+223 76 12 34 56', $text);
        self::assertStringStartsWith('https://wa.me/22376123456?text=', $link);
        self::assertNull((new LinkGateway())->conversationLink('abc', 'x'));
    }

    public function testSelfAssignFromAvailableQueueAndRequestInfoFlow(): void
    {
        $question = $this->askQuestion();
        $this->login($this->scholar);
        $available = $this->get('/fr/professeur/questions?file=a-prendre')->body();
        self::assertStringContainsString($question['reference'], $available);
        $this->post('/fr/professeur/questions/' . $question['reference'] . '/prendre');
        self::assertSame($this->scholar, (int) Database::value('SELECT assigned_to FROM questions WHERE id = ?', [(int) $question['id']]));
        self::assertSame('assigned', Database::value('SELECT status FROM questions WHERE id = ?', [(int) $question['id']]));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM question_assignments WHERE question_id = ?', [(int) $question['id']]));
        self::assertStringNotContainsString('/questions/' . $question['reference'] . '/prendre', $this->get('/fr/professeur/questions?file=a-prendre')->body(), 'Plus proposée dans la file « à prendre »');

        // Demande de précision → membre notifié → précision → professeur notifié
        $this->post('/fr/professeur/questions/' . $question['reference'] . '/demander-precision', ['body' => 'Dans quel pays vivez-vous ?']);
        self::assertSame('needs_info', Database::value('SELECT status FROM questions WHERE id = ?', [(int) $question['id']]));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->member, 'question.needs_info']));
        Session::reset();
        Auth::reset();
        $this->login($this->member);
        self::assertStringContainsString('Dans quel pays vivez-vous ?', $this->get('/fr/mon-espace/questions/' . $question['reference'])->body());
        $this->post('/fr/mon-espace/questions/' . $question['reference'] . '/precision', ['body' => 'Au Mali.']);
        self::assertSame('in_progress', Database::value('SELECT status FROM questions WHERE id = ?', [(int) $question['id']]));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->scholar, 'question.info_received']));

        // Une autre personne ne peut pas prendre une question déjà assignée
        $other = $this->createUser(['email' => 'autre@example.ml', 'role_id' => 2]);
        Database::execute("INSERT INTO scholar_profiles (user_id) VALUES (?)", [$other]);
        Database::execute('INSERT INTO scholar_rubriques (user_id, rubrique_id) VALUES (?, 3)', [$other]);
        Session::reset();
        Auth::reset();
        $this->login($other);
        self::assertSame(404, $this->get('/fr/professeur/questions/' . $question['reference'])->status());
    }

    public function testInternalNoteReassignmentAndPublicationProposal(): void
    {
        $question = AssignmentService::assign($this->askQuestion(), $this->scholar, $this->admin);
        $this->login($this->scholar);
        $this->post('/fr/professeur/questions/' . $question['reference'] . '/note', ['body' => 'Vérifier la référence du hadith.']);
        $this->post('/fr/professeur/questions/' . $question['reference'] . '/reassignation', ['reason' => 'Hors de ma spécialité.']);
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->admin, 'question.reassign_requested']));
        $this->post('/fr/professeur/questions/' . $question['reference'] . '/repondre', ['body_html' => '<p>Réponse.</p>', 'answer_language' => 'fr']);
        $this->post('/fr/professeur/questions/' . $question['reference'] . '/proposer-publication', ['title' => 'Lire le Coran sur téléphone', 'rephrased' => 'Peut-on lire le Coran sur un téléphone sans ablutions ?']);
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->admin, 'question.publication_proposed']));
        self::assertSame(3, (int) Database::value('SELECT COUNT(*) FROM question_messages WHERE question_id = ? AND kind = ?', [(int) $question['id'], 'internal_note']));

        Session::reset();
        Auth::reset();
        $this->login($this->member);
        $detail = $this->get('/fr/mon-espace/questions/' . $question['reference'])->body();
        self::assertStringNotContainsString('Vérifier la référence', $detail);
        self::assertStringNotContainsString('Hors de ma spécialité', $detail);
    }

    public function testAutomaticAssignmentWhenEnabled(): void
    {
        \App\Models\Setting::set('auto_assign', '1', 'questions');
        try {
            $busy = $this->createUser(['email' => 'busy@example.ml', 'role_id' => 2]);
            Database::execute("INSERT INTO scholar_profiles (user_id, languages, max_open_questions) VALUES (?, 'fr', 1)", [$busy]);
            Database::execute('INSERT INTO scholar_rubriques (user_id, rubrique_id) VALUES (?, 3)', [$busy]);
            Database::execute("INSERT INTO questions (reference, user_id, rubrique_id, input_type, status, assigned_to) VALUES ('DF-2026-000900', ?, 3, 'text', 'assigned', ?)", [$this->member, $busy]);

            $question = $this->askQuestion();
            $fresh = Question::find((int) $question['id']);
            self::assertSame('assigned', $fresh['status']);
            self::assertSame($this->scholar, (int) $fresh['assigned_to'], 'Le professeur saturé est écarté');
            self::assertNull(Database::value('SELECT assigned_by FROM question_assignments WHERE question_id = ?', [(int) $question['id']]), 'assigned_by NULL = automatique');
        } finally {
            \App\Models\Setting::set('auto_assign', '0', 'questions');
        }
    }

    public function testLateRemindersAndAutoClose(): void
    {
        $question = AssignmentService::assign($this->askQuestion(), $this->scholar, $this->admin);
        Database::execute('UPDATE questions SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 80 HOUR) WHERE id = ?', [(int) $question['id']]);
        self::assertSame(1, Maintenance::remindLateQuestions());
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->scholar, 'question.late']));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->admin, 'question.late']));
        self::assertSame(0, Maintenance::remindLateQuestions(), 'Un seul rappel par jour');
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM message_queue WHERE template = 'late_digest'"));
        QueueService::process();
        self::assertStringContainsString('1 question(s) en retard', MailService::$sent[array_key_last(MailService::$sent)]['subject']);

        // Clôture automatique 30 jours après la réponse
        Database::execute("UPDATE questions SET status = 'answered', answered_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 31 DAY) WHERE id = ?", [(int) $question['id']]);
        self::assertSame(1, Maintenance::autoCloseAnswered());
        self::assertSame('closed', Database::value('SELECT status FROM questions WHERE id = ?', [(int) $question['id']]));
        self::assertSame(0, Maintenance::autoCloseAnswered());
    }

    public function testScholarProfileUpdateAndDashboard(): void
    {
        $this->login($this->scholar);
        self::assertSame(200, $this->get('/fr/professeur')->status());
        self::assertSame(200, $this->get('/ar/scholar')->status());
        $this->post('/fr/professeur/profil', ['title' => ['fr' => 'Cheick', 'ar' => 'الشيخ', 'en' => ''], 'bio' => ['fr' => 'Bio FR', 'ar' => 'نبذة', 'en' => ''], 'languages' => ['fr', 'bm'], 'is_available' => '1']);
        self::assertSame('fr,bm', Database::value('SELECT languages FROM scholar_profiles WHERE user_id = ?', [$this->scholar]));
        self::assertSame(2, (int) Database::value('SELECT COUNT(*) FROM scholar_translations WHERE user_id = ?', [$this->scholar]));
        self::assertStringContainsString('Cheick Cheick Moussa Koné', $this->get('/fr/professeurs')->body());

        // Un membre n'accède pas à l'espace professeur
        Session::reset();
        Auth::reset();
        $this->login($this->member);
        self::assertSame(403, $this->get('/fr/professeur/questions')->status());
    }

    private function uploadAudio(): \App\Core\Response
    {
        $tmp = tempnam(sys_get_temp_dir(), 'df-audio-');
        copy(__DIR__ . '/../Fixtures/audio/question.mp3', $tmp);
        $files = ['audio' => ['name' => 'reponse.mp3', 'type' => 'audio/mpeg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)]];

        return $this->app->handle(new \App\Core\Request([], ['_token' => \App\Core\Csrf::token()], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/audio', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json'], [], $files));
    }
}
