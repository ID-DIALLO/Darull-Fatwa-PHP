<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Models\Question;
use App\Services\MailService;
use App\Services\QueueService;
use App\Services\QuestionWorkflow;
use App\Services\WorkflowException;
use Tests\Support\DatabaseTestCase;

/**
 * Phase 3 : création d'une question (vocal / texte), référence, limite quotidienne, Mon espace,
 * contrôle d'accès aux audios (404 pour un autre membre), précision et clôture.
 */
final class QuestionsTest extends DatabaseTestCase
{
    private const FIXTURES = __DIR__ . '/../Fixtures/audio/';

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (glob(dirname(__DIR__, 2) . '/storage/audio/*/*/*') ?: [] as $file) {
            @unlink($file);
        }
    }

    public function testVisitorIsInvitedToLogInThenReturnsToTheWizard(): void
    {
        $response = $this->get('/fr/poser-une-question');
        self::assertSame(302, $response->status());
        self::assertSame('/fr/connexion', $response->getHeader('Location'));

        $this->createUser(['email' => 'awa@example.ml']);
        $login = $this->post('/fr/connexion', ['identifier' => 'awa@example.ml', 'password' => 'MotDePasse123!']);
        self::assertSame('/fr/poser-une-question', $login->getHeader('Location'));
        self::assertStringContainsString('data-question-wizard', $this->get('/fr/poser-une-question')->body());
    }

    public function testTextQuestionGetsAReferenceAndAppearsAsPendingInMyAccount(): void
    {
        $id = $this->createUser(['email' => 'awa@example.ml']);
        $this->login($id);

        $response = $this->post('/fr/poser-une-question', [
            'rubrique_id' => 3, 'title' => 'Lecture du Coran', 'body' => "Assalamou alaykoum.\n\nPuis-je lire le Coran sans ablutions ?",
            'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private',
        ]);
        self::assertSame(302, $response->status());
        $question = Database::fetch('SELECT * FROM questions WHERE user_id = ?', [$id]);
        self::assertNotNull($question);
        self::assertMatchesRegularExpression('/^DF-\d{4}-\d{6}$/', $question['reference']);
        self::assertSame(sprintf('DF-%s-%06d', gmdate('Y'), $question['id']), $question['reference']);
        self::assertSame('/fr/poser-une-question/confirmation/' . $question['reference'], $response->getHeader('Location'));
        self::assertSame('text', $question['input_type']);
        self::assertSame('new', $question['status']);

        $message = Database::fetch('SELECT * FROM question_messages WHERE question_id = ?', [(int) $question['id']]);
        self::assertSame('question', $message['kind']);
        self::assertStringContainsString('<p>Assalamou alaykoum.</p><p>Puis-je lire le Coran sans ablutions ?</p>', $message['body']);
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM question_status_logs WHERE question_id = ? AND to_status = ?', [(int) $question['id'], 'new']));

        // Confirmation, notification in-app et e-mail de confirmation
        $confirmation = $this->get('/fr/poser-une-question/confirmation/' . $question['reference']);
        self::assertStringContainsString($question['reference'], $confirmation->body());
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$id, 'question.received']));
        QueueService::process();
        self::assertSame('question_received', MailService::$sent[0]['template'] ?? null);
        self::assertStringContainsString($question['reference'], MailService::$sent[0]['subject']);

        // Mon espace : immédiatement « En attente »
        $list = $this->get('/fr/mon-espace/questions')->body();
        self::assertStringContainsString($question['reference'], $list);
        self::assertStringContainsString('En attente', $list);
        self::assertStringContainsString('Lecture du Coran', $list);
        $detail = $this->get('/fr/mon-espace/questions/' . $question['reference'])->body();
        self::assertStringContainsString('Puis-je lire le Coran sans ablutions', $detail);
        self::assertStringContainsString('Un professeur étudie votre question', $detail);
        self::assertStringContainsString('2', $this->get('/fr/mon-espace')->body());
    }

    public function testVoiceQuestionViaUploadApi(): void
    {
        $id = $this->createUser(['email' => 'awa@example.ml']);
        $this->login($id);

        $upload = $this->uploadAudio('question.mp3');
        self::assertSame(201, $upload->status(), $upload->body());
        $data = json_decode($upload->body(), true);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $data['media_uuid']);
        self::assertSame(2, $data['duration']);
        $media = Database::fetch('SELECT * FROM media_files WHERE uuid = ?', [$data['media_uuid']]);
        self::assertSame('private', $media['disk']);
        self::assertStringStartsWith('audio/' . gmdate('Y') . '/' . gmdate('m') . '/', $media['path']);
        self::assertFileExists(dirname(__DIR__, 2) . '/storage/' . $media['path']);
        self::assertSame(hash_file('sha256', self::FIXTURES . 'question.mp3'), $media['checksum_sha256']);
        self::assertSame('none', $media['conversion_status']);

        $response = $this->post('/ar/ask', [
            'rubrique_id' => 2, 'media_uuid' => $data['media_uuid'], 'spoken_language' => 'bm',
            'answer_language' => 'fr', 'answer_channel' => 'both', 'whatsapp_number' => '76 11 22 33', 'visibility' => 'publishable',
        ]);
        self::assertSame(302, $response->status(), json_encode(Session::getFlash('_errors')));
        $question = Database::fetch('SELECT * FROM questions WHERE user_id = ?', [$id]);
        self::assertSame('audio', $question['input_type']);
        self::assertSame('both', $question['answer_channel']);
        self::assertSame('+22376112233', $question['whatsapp_number']);
        self::assertSame('publishable', $question['visibility']);
        self::assertSame((int) $media['id'], (int) Database::value('SELECT media_id FROM question_messages WHERE question_id = ?', [(int) $question['id']]));

        // Le propriétaire lit son audio avec prise en charge des requêtes Range
        $full = $this->get('/media/audio/' . $data['media_uuid']);
        self::assertSame(200, $full->status());
        self::assertSame('audio/mpeg', $full->getHeader('Content-Type'));
        self::assertSame('bytes', $full->getHeader('Accept-Ranges'));
        $partial = $this->get('/media/audio/' . $data['media_uuid'], ['HTTP_RANGE' => 'bytes=0-99']);
        self::assertSame(206, $partial->status());
        self::assertSame('bytes 0-99/' . $media['size_bytes'], $partial->getHeader('Content-Range'));
        self::assertSame('100', $partial->getHeader('Content-Length'));

        // Un enregistrement rattaché ne peut plus être supprimé par l'API
        $delete = $this->app->handle(Request::create('/api/audio/' . $data['media_uuid'], 'DELETE', [], ['HTTP_X_CSRF_TOKEN' => \App\Core\Csrf::token()]));
        self::assertSame(404, $delete->status());
    }

    public function testAnotherMemberGuessingTheAudioUrlGets404(): void
    {
        $owner = $this->createUser(['email' => 'awa@example.ml']);
        $this->login($owner);
        $uuid = json_decode($this->uploadAudio('question.mp3')->body(), true)['media_uuid'];
        $this->post('/fr/poser-une-question', ['rubrique_id' => 1, 'media_uuid' => $uuid, 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private']);

        Session::reset();
        Auth::reset();
        self::assertSame(404, $this->get('/media/audio/' . $uuid)->status(), 'Visiteur');

        $other = $this->createUser(['email' => 'autre@example.ml']);
        $this->login($other);
        self::assertSame(404, $this->get('/media/audio/' . $uuid)->status(), 'Autre membre');
        self::assertSame(404, $this->get('/fr/mon-espace/questions/' . Database::value('SELECT reference FROM questions WHERE user_id = ?', [$owner]))->status());

        // Le professeur assigné et l'administrateur y accèdent
        Session::reset();
        Auth::reset();
        $scholar = $this->createUser(['email' => 'cheick@example.ml', 'role_id' => 2]);
        Database::execute('UPDATE questions SET assigned_to = ? WHERE user_id = ?', [$scholar, $owner]);
        $this->login($scholar);
        self::assertSame(200, $this->get('/media/audio/' . $uuid)->status(), 'Professeur assigné');
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM activity_logs WHERE action = ?', ['media.listened']), 'Écoute journalisée');

        Session::reset();
        Auth::reset();
        $admin = $this->createUser(['email' => 'admin@example.ml', 'role_id' => 4]);
        $this->login($admin);
        self::assertSame(200, $this->get('/media/audio/' . $uuid)->status(), 'Administrateur');
    }

    public function testNonAudioFileRenamedAsMp3IsRefused(): void
    {
        $this->login($this->createUser());
        $response = $this->uploadAudio('fake.mp3');
        self::assertSame(422, $response->status());
        self::assertSame('invalid_type', json_decode($response->body(), true)['reason']);
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM media_files'));

        self::assertSame('too_short', json_decode($this->uploadAudio('short-1s.mp3')->body(), true)['reason']);
        foreach (['question.webm', 'question.ogg', 'question.m4a', 'question.wav'] as $fixture) {
            $accepted = $this->uploadAudio($fixture, 2);
            self::assertSame(201, $accepted->status(), $fixture . ' : ' . $accepted->body());
        }
        self::assertSame(4, (int) Database::value('SELECT COUNT(*) FROM media_files WHERE conversion_status = ?', ['pending']));
    }

    public function testDailyLimitOfFiveQuestions(): void
    {
        $id = $this->createUser();
        $this->login($id);
        $data = ['rubrique_id' => 1, 'body' => 'Une question suffisamment longue.', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private'];
        for ($i = 0; $i < 5; $i++) {
            $this->post('/fr/poser-une-question', $data);
        }
        self::assertSame(5, (int) Database::value('SELECT COUNT(*) FROM questions WHERE user_id = ?', [$id]));
        $this->post('/fr/poser-une-question', $data, ['HTTP_REFERER' => 'http://localhost/fr/poser-une-question']);
        self::assertSame(5, (int) Database::value('SELECT COUNT(*) FROM questions WHERE user_id = ?', [$id]));
        self::assertStringContainsString('limite de 5 questions', ((array) Session::getFlash('_errors'))['body'][0] ?? '');
        self::assertStringContainsString('Vous avez atteint la limite', $this->get('/fr/poser-une-question')->body());
    }

    public function testEmptyQuestionAndWhatsAppNumberValidation(): void
    {
        $this->login($this->createUser());
        $this->post('/fr/poser-une-question', ['rubrique_id' => 1, 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private'], ['HTTP_REFERER' => 'http://localhost/fr/poser-une-question']);
        self::assertSame('Enregistrez un message vocal ou écrivez votre question.', ((array) Session::getFlash('_errors'))['body'][0] ?? null);

        Session::reset();
        $this->post('/fr/poser-une-question', ['rubrique_id' => 1, 'body' => 'Question écrite.', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'whatsapp', 'whatsapp_number' => '', 'visibility' => 'private'], ['HTTP_REFERER' => 'http://localhost/fr/poser-une-question']);
        self::assertArrayHasKey('whatsapp_number', (array) Session::getFlash('_errors'));
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM questions'));
    }

    public function testFollowUpResolveAndFeedback(): void
    {
        $id = $this->createUser(['email' => 'awa@example.ml']);
        $scholar = $this->createUser(['email' => 'cheick@example.ml', 'role_id' => 2]);
        $this->login($id);
        $this->post('/fr/poser-une-question', ['rubrique_id' => 1, 'body' => 'Ma question initiale.', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'private']);
        $question = Question::find((int) Database::value('SELECT id FROM questions'));
        $reference = (string) $question['reference'];

        // Le professeur prend la question, demande une précision (via le workflow)
        $question = QuestionWorkflow::transition($question, 'assigned', $scholar, null, ['assigned_to' => $scholar]);
        $question = QuestionWorkflow::transition($question, 'in_progress', $scholar);
        $question = QuestionWorkflow::transition($question, 'needs_info', $scholar, 'Quel pays ?');
        self::assertStringContainsString('Précision demandée', $this->get('/fr/mon-espace/questions/' . $reference)->body());
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$id, 'question.needs_info']));

        // Le membre précise : la question repasse « en cours » et le professeur est prévenu
        $response = $this->post('/fr/mon-espace/questions/' . $reference . '/precision', ['body' => 'Je vis au Mali.']);
        self::assertSame(302, $response->status());
        self::assertSame('in_progress', Database::value('SELECT status FROM questions WHERE id = ?', [(int) $question['id']]));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM question_messages WHERE question_id = ? AND kind = ?', [(int) $question['id'], 'follow_up']));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$scholar, 'question.info_received']));

        // Réponse officielle puis clôture par le membre
        Database::execute("INSERT INTO question_messages (question_id, sender_id, kind, body) VALUES (?, ?, 'answer', '<p>Voici la réponse.</p>')", [(int) $question['id'], $scholar]);
        $answerId = Database::lastInsertId();
        $question = QuestionWorkflow::transition(Question::find((int) $question['id']), 'answered', $scholar, null, ['answer_message_id' => $answerId]);
        self::assertNotNull($question['answered_at']);
        self::assertNotNull($question['first_response_minutes']);
        $detail = $this->get('/fr/mon-espace/questions/' . $reference)->body();
        self::assertStringContainsString('Voici la réponse.', $detail);
        self::assertStringContainsString('Ma question est résolue', $detail);

        $this->post('/fr/mon-espace/questions/' . $reference . '/avis', ['helpful' => '1']);
        self::assertSame(1, (int) Database::value('SELECT user_found_helpful FROM questions WHERE id = ?', [(int) $question['id']]));
        $this->post('/fr/mon-espace/questions/' . $reference . '/resolue');
        self::assertSame('closed', Database::value('SELECT status FROM questions WHERE id = ?', [(int) $question['id']]));
        self::assertStringContainsString('Clôturée', $this->get('/fr/mon-espace/questions?statut=answered')->body());

        // Une question clôturée ne peut plus recevoir de précision
        self::assertSame(403, $this->post('/fr/mon-espace/questions/' . $reference . '/precision', ['body' => 'Encore ?'])->status());
    }

    public function testWorkflowRejectsForbiddenTransitions(): void
    {
        $id = $this->createUser();
        Database::execute("INSERT INTO questions (reference, user_id, rubrique_id, input_type, status) VALUES ('DF-2026-000001', ?, 1, 'text', 'new')", [$id]);
        $question = Question::find((int) Database::lastInsertId());

        $this->expectException(WorkflowException::class);
        try {
            QuestionWorkflow::transition($question, 'answered', null);
        } finally {
            self::assertSame('new', Database::value('SELECT status FROM questions WHERE id = ?', [(int) $question['id']]), 'Rien n\'est enregistré');
            self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM question_status_logs'));
        }
    }

    public function testEveryAllowedTransitionIsAcceptedAndOthersRefused(): void
    {
        foreach (Question::STATUSES as $from) {
            foreach (Question::STATUSES as $to) {
                $expected = in_array($to, QuestionWorkflow::TRANSITIONS[$from], true);
                self::assertSame($expected, QuestionWorkflow::canTransition($from, $to), "{$from} → {$to}");
            }
        }
        self::assertTrue(QuestionWorkflow::canTransition('answered', 'closed'));
        self::assertFalse(QuestionWorkflow::canTransition('closed', 'in_progress'));
        self::assertFalse(QuestionWorkflow::canTransition('rejected', 'assigned'));
    }

    public function testNotificationsPageAndApi(): void
    {
        $id = $this->createUser();
        $this->login($id);
        \App\Services\NotificationService::notify($id, 'question.answered', ['reference' => 'DF-2026-000042'], '/fr/mon-espace', null);
        self::assertSame(['count' => 1], json_decode($this->get('/api/notifications/count')->body(), true));
        $page = $this->get('/fr/mon-espace/notifications')->body();
        self::assertStringContainsString('Réponse disponible pour DF-2026-000042', $page);
        $open = $this->get('/fr/mon-espace/notifications/' . Database::value('SELECT id FROM notifications'));
        self::assertSame('/fr/mon-espace', $open->getHeader('Location'));
        self::assertSame(['count' => 0], json_decode($this->get('/api/notifications/count')->body(), true));
    }

    private function uploadAudio(string $fixture, ?int $duration = null): \App\Core\Response
    {
        $tmp = tempnam(sys_get_temp_dir(), 'df-audio-');
        copy(self::FIXTURES . $fixture, $tmp);
        $files = ['audio' => ['name' => $fixture, 'type' => 'application/octet-stream', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)]];
        $params = ['_token' => \App\Core\Csrf::token()];
        if ($duration !== null) {
            $params['duration'] = (string) $duration;
        }
        $request = new Request([], $params, ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/audio', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json'], [], $files);

        return $this->app->handle($request);
    }
}
