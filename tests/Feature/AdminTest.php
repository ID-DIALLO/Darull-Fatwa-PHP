<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Models\Setting;
use App\Services\Admin\BackupService;
use App\Services\AnswerService;
use App\Services\MailService;
use Tests\Support\DatabaseTestCase;

/**
 * Phase 5 : back-office (§4.6). Accès par rôle, questions (assignation, relecture, publication anonymisée,
 * export filtré, actions groupées), utilisateurs, professeurs, rubriques, paramètres chiffrés, contact,
 * newsletter, sauvegardes, journal d'activité.
 */
final class AdminTest extends DatabaseTestCase
{
    private int $member;
    private int $scholar;
    private int $admin;
    private int $superadmin;
    private int $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createUser(['email' => 'awa@example.ml', 'full_name' => 'Awa Diarra', 'display_name' => 'Oum Fatima', 'country' => 'ML', 'phone' => '+22376123499']);
        $this->scholar = $this->createUser(['email' => 'cheick@example.ml', 'full_name' => 'Cheick Moussa Koné', 'role_id' => 2]);
        $this->admin = $this->createUser(['email' => 'admin@example.ml', 'full_name' => 'Admin Principal', 'role_id' => 4]);
        $this->superadmin = $this->createUser(['email' => 'super@example.ml', 'full_name' => 'Super Admin', 'role_id' => 5]);
        $this->moderator = $this->createUser(['email' => 'modo@example.ml', 'full_name' => 'Modo', 'role_id' => 3]);
        Database::execute("INSERT INTO scholar_profiles (user_id, languages, can_self_assign, requires_review) VALUES (?, 'fr,ar', 1, 1)", [$this->scholar]);
        Database::execute('INSERT INTO scholar_rubriques (user_id, rubrique_id) VALUES (?, 3)', [$this->scholar]);
    }

    /** @return array<string, mixed> */
    private function askQuestion(string $body = 'Puis-je lire le Coran sur mon téléphone sans ablutions ?', array $overrides = []): array
    {
        $this->login($this->member);
        $this->post('/fr/poser-une-question', $overrides + ['rubrique_id' => 3, 'body' => $body, 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'publishable']);
        Session::reset();
        Auth::reset();

        return Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');
    }

    /** Requête POST multipart avec un fichier. @param array<string, mixed> $data @param array<string, mixed> $file */
    private function postWithFile(string $uri, array $data, string $field, array $file): \App\Core\Response
    {
        return $this->app->handle(new Request([], $data + ['_token' => Csrf::token()], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost'], [], [$field => $file]));
    }

    /** @return array<string, mixed> Entrée $_FILES d'une image PNG générée */
    private function pngFile(): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'df-img-');
        $image = imagecreatetruecolor(64, 32);
        imagefill($image, 0, 0, imagecolorallocate($image, 11, 93, 69));
        imagepng($image, $tmp);

        return ['name' => 'banniere.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)];
    }

    private function logged(string $action): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM activity_logs WHERE action = ?', [$action]);
    }

    public function testAllAdminPagesRenderForSuperadmin(): void
    {
        $question = $this->askQuestion();
        Database::execute("INSERT INTO contact_messages (name, email, subject, message, locale, status) VALUES ('Ali', 'ali@example.ml', 'general', 'Salam', 'fr', 'new')");
        $this->login($this->superadmin);
        $pages = [
            '/admin', '/admin/stats/data?period=7', '/admin/questions', '/admin/questions?status=open&late=1&review=1&sort=late&q=Awa', '/admin/questions/new?member=Awa',
            '/admin/questions/' . $question['id'], '/admin/users', '/admin/users/new', '/admin/users/' . $this->member, '/admin/users/' . $this->member . '/edit',
            '/admin/scholars', '/admin/scholars/' . $this->scholar . '/edit', '/admin/rubriques', '/admin/rubriques/new', '/admin/rubriques/3/edit', '/admin/qa',
            '/admin/banners', '/admin/banners/new', '/admin/banners/1/edit', '/admin/home-sections', '/admin/daily-quotes', '/admin/daily-quotes/new', '/admin/daily-quotes/1/edit',
            '/admin/pages', '/admin/pages/new', '/admin/pages/1/edit', '/admin/contact-messages', '/admin/contact-messages/1', '/admin/newsletter',
            '/admin/settings', '/admin/settings?group=mail', '/admin/settings?group=whatsapp', '/admin/activity-log', '/admin/backups',
        ];
        foreach ($pages as $page) {
            $response = $this->get($page);
            self::assertSame(200, $response->status(), 'Page ' . $page);
            self::assertStringNotContainsString('Erreur 500', $response->body(), 'Page ' . $page);
        }
        // Fiche question : l'admin voit le nom complet, le téléphone et le pays du demandeur (§4.6.2)
        $sheet = $this->get('/admin/questions/' . $question['id'])->body();
        self::assertStringContainsString('Awa Diarra', $sheet);
        self::assertStringContainsString('+22376123499', $sheet);
        self::assertStringContainsString('Mali', $sheet);
        // Données JSON des graphiques
        $data = json_decode($this->get('/admin/stats/data?period=30')->body(), true);
        self::assertSame(1, $data['kpis']['total']);
        self::assertArrayHasKey('rubrique', $data['charts']);
        // Interface en arabe (RTL) selon le profil
        Database::execute("UPDATE users SET locale = 'ar' WHERE id = ?", [$this->superadmin]);
        Auth::reset();
        $this->login($this->superadmin);
        $ar = $this->get('/admin/questions')->body();
        self::assertStringContainsString('dir="rtl"', $ar);
        self::assertStringContainsString('الأسئلة', $ar);
    }

    public function testModeratorCannotAccessSettingsUsersOrBackups(): void
    {
        $this->login($this->moderator);
        self::assertSame(200, $this->get('/admin')->status());
        self::assertSame(200, $this->get('/admin/questions')->status());
        self::assertSame(200, $this->get('/admin/contact-messages')->status());
        foreach (['/admin/users', '/admin/settings', '/admin/backups', '/admin/rubriques', '/admin/scholars', '/admin/activity-log', '/admin/banners'] as $forbidden) {
            self::assertSame(403, $this->get($forbidden)->status(), $forbidden);
        }
        self::assertSame(403, $this->post('/admin/settings/general', ['site_name' => 'Pirate'])->status());
        self::assertSame(403, $this->get('/admin/questions/export?format=csv')->status(), 'Export réservé aux administrateurs');
        Session::reset();
        Auth::reset();
        // Un membre n'accède pas du tout à l'administration
        $this->login($this->member);
        self::assertSame(403, $this->get('/admin')->status());
    }

    public function testQuestionJourneyAssignReviewPublishAndExport(): void
    {
        $question = $this->askQuestion();
        $id = (int) $question['id'];
        $this->login($this->admin);

        // Assignation par l'administration
        $this->post('/admin/questions/' . $id . '/assign', ['scholar_id' => $this->scholar, 'comment' => 'Spécialiste du Coran']);
        self::assertSame('assigned', Database::value('SELECT status FROM questions WHERE id = ?', [$id]));
        self::assertSame($this->scholar, (int) Database::value('SELECT assigned_to FROM questions WHERE id = ?', [$id]));

        // Transition interdite depuis l'admin : refusée sans enregistrement
        $logs = (int) Database::value('SELECT COUNT(*) FROM question_status_logs WHERE question_id = ?', [$id]);
        $this->post('/admin/questions/' . $id . '/status', ['status' => 'published']);
        self::assertSame('assigned', Database::value('SELECT status FROM questions WHERE id = ?', [$id]));
        self::assertSame($logs, (int) Database::value('SELECT COUNT(*) FROM question_status_logs WHERE question_id = ?', [$id]));

        // Le professeur (relecture obligatoire) répond : la réponse attend la validation
        Session::reset();
        Auth::reset();
        $this->login($this->scholar);
        $this->get('/fr/professeur/questions/' . $question['reference']);
        $scholarUser = \App\Models\User::findWithRole($this->scholar);
        AnswerService::answer(Database::fetch('SELECT * FROM questions WHERE id = ?', [$id]), $scholarUser, '<p>Oui, la lecture sur écran est <strong>permise</strong>. Awa, qu\'Allah vous facilite.</p>', null, [], 'fr');
        self::assertSame('in_progress', Database::value('SELECT status FROM questions WHERE id = ?', [$id]));
        $messageId = (int) Database::value("SELECT id FROM question_messages WHERE question_id = ? AND kind = 'answer'", [$id]);
        Session::reset();
        Auth::reset();

        $this->login($this->admin);
        self::assertStringContainsString($question['reference'], $this->get('/admin/questions?review=1')->body());
        $sheet = $this->get('/admin/questions/' . $id)->body();
        self::assertStringContainsString('/review/' . $messageId, $sheet);

        // Refus sans motif : rien ne change
        $this->post('/admin/questions/' . $id . '/review/' . $messageId, ['decision' => 'reject', 'reason' => '']);
        self::assertSame('pending', Database::value('SELECT review_status FROM question_messages WHERE id = ?', [$messageId]));
        // Validation : question répondue, membre notifié
        $this->post('/admin/questions/' . $id . '/review/' . $messageId, ['decision' => 'approve']);
        self::assertSame('answered', Database::value('SELECT status FROM questions WHERE id = ?', [$id]));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?', [$this->member, 'question.answered']));
        self::assertNotNull(Database::value('SELECT first_response_minutes FROM questions WHERE id = ?', [$id]));

        // Publication anonymisée : la Q/R ne contient aucune donnée personnelle
        $response = $this->post('/admin/questions/' . $id . '/publish', [
            'title' => 'Lire le Coran sur un téléphone sans ablutions', 'question_text' => 'Peut-on lire le Coran sur un téléphone sans être en état d\'ablutions ?',
            'answer_html' => '<p>Oui, la lecture sur écran est <strong>permise</strong>.</p>', 'status' => 'published',
        ]);
        self::assertSame(302, $response->status());
        $publication = Database::fetch('SELECT p.*, t.title, t.slug, t.question_text, t.answer_html FROM qa_publications p JOIN qa_publication_translations t ON t.publication_id = p.id WHERE p.question_id = ?', [$id]);
        self::assertNotNull($publication);
        self::assertSame('published', $publication['status']);
        self::assertSame('lire-le-coran-sur-un-telephone-sans-ablutions', $publication['slug']);
        self::assertStringNotContainsString('Awa', $publication['question_text'] . $publication['answer_html'] . $publication['title']);
        self::assertNull($publication['audio_media_id'], 'Jamais la voix du demandeur');
        self::assertSame('published', Database::value('SELECT status FROM questions WHERE id = ?', [$id]));
        self::assertSame(200, $this->get('/admin/qa/' . $publication['id'] . '/edit')->status());

        // Export : respecte les filtres actifs, UTF-8 avec BOM, et l'arabe passe
        $csv = $this->get('/admin/questions/export?format=csv&status=published');
        self::assertSame(200, $csv->status());
        $content = (string) file_get_contents($this->exportedFile($csv));
        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString($question['reference'], $content);
        self::assertStringContainsString('Awa Diarra', $content);
        self::assertStringContainsString('Le Coran', $content);
        $filtered = (string) file_get_contents($this->exportedFile($this->get('/admin/questions/export?format=csv&status=new')));
        self::assertStringNotContainsString($question['reference'], $filtered);
        $xlsx = $this->get('/admin/questions/export?format=xlsx');
        self::assertSame(200, $xlsx->status());
        self::assertStringContainsString('spreadsheetml', (string) $xlsx->getHeader('Content-Type'));
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->exportedFile($xlsx));
        self::assertSame($question['reference'], $reader->getActiveSheet()->getCell('A2')->getValue());
        self::assertSame('Awa Diarra', $reader->getActiveSheet()->getCell('C2')->getValue());

        // Chaque action apparaît dans le journal (§ critères Phase 5)
        foreach (['question.status.assigned', 'question.answer_approved', 'admin.question.published', 'admin.question.exported'] as $action) {
            self::assertGreaterThan(0, $this->logged($action), $action);
        }
        self::assertStringContainsString('admin.question.exported', $this->get('/admin/activity-log?action=admin.question')->body());
    }

    private function exportedFile(\App\Core\Response $response): string
    {
        $disposition = (string) $response->getHeader('Content-Disposition');
        preg_match('/filename="?([^";]+)"?/', $disposition, $m);
        $path = storage_path('exports/' . ($m[1] ?? ''));
        self::assertFileExists($path);

        return $path;
    }

    public function testBulkActionsCreateFromWhatsAppAndAudioImport(): void
    {
        $q1 = $this->askQuestion('Première question');
        $q2 = $this->askQuestion('Deuxième question');
        $this->login($this->admin);
        $this->post('/admin/questions/bulk', ['action' => 'assign', 'ids' => [$q1['id'], $q2['id']], 'scholar_id' => $this->scholar]);
        self::assertSame(2, (int) Database::value("SELECT COUNT(*) FROM questions WHERE assigned_to = ? AND status = 'assigned'", [$this->scholar]));
        $this->post('/admin/questions/bulk', ['action' => 'priority', 'ids' => [$q1['id']], 'priority' => 'high']);
        self::assertSame('high', Database::value('SELECT priority FROM questions WHERE id = ?', [$q1['id']]));
        $this->post('/admin/questions/bulk', ['action' => 'delete', 'ids' => [$q2['id']]]);
        self::assertNotNull(Database::value('SELECT deleted_at FROM questions WHERE id = ?', [$q2['id']]));
        self::assertStringNotContainsString($q2['reference'], $this->get('/admin/questions')->body());
        self::assertGreaterThan(0, $this->logged('admin.question.bulk'));

        // Import d'un audio WhatsApp dans le fil de la première question
        $tmp = tempnam(sys_get_temp_dir(), 'df-audio-');
        copy(__DIR__ . '/../Fixtures/audio/question.mp3', $tmp);
        $this->postWithFile('/admin/questions/' . $q1['id'] . '/import-audio', ['kind' => 'question'], 'audio', ['name' => 'PTT-20261017.mp3', 'type' => 'audio/mpeg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)]);
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM question_messages WHERE question_id = ? AND media_id IS NOT NULL', [$q1['id']]));
        self::assertSame('mixed', Database::value('SELECT input_type FROM questions WHERE id = ?', [$q1['id']]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM whatsapp_messages WHERE question_id = ? AND direction = 'in'", [$q1['id']]));
        self::assertSame($this->member, (int) Database::value('SELECT owner_id FROM media_files ORDER BY id DESC LIMIT 1'), 'L\'audio appartient au membre');

        // Question enregistrée au nom d'un membre (reçue sur WhatsApp)
        $this->post('/admin/questions', ['user_id' => $this->member, 'rubrique_id' => 3, 'body' => 'Question dictée au téléphone', 'spoken_language' => 'bm', 'answer_language' => 'fr', 'answer_channel' => 'whatsapp', 'whatsapp_number' => '+22376123499', 'visibility' => 'private']);
        $created = Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');
        self::assertSame('whatsapp', $created['source']);
        self::assertSame($this->member, (int) $created['user_id']);
        self::assertSame('+22376123499', $created['whatsapp_number']);
    }

    public function testSettingsSecretsAreEncryptedAndNeverDisplayed(): void
    {
        $this->login($this->superadmin);
        $original = Setting::get('site_name');
        try {
            $this->post('/admin/settings/general', ['site_name' => 'Darul Fatwa Test', 'maintenance_mode' => '0', 'site_slogan_fr' => 'Slogan', 'prophet_honorific_fr' => 'ﷺ']);
            self::assertSame('Darul Fatwa Test', Setting::get('site_name'));
            self::assertGreaterThan(0, $this->logged('admin.settings.updated'));

            $this->post('/admin/settings/mail', ['mail_host' => 'smtp.example.com', 'mail_port' => '587', 'mail_encryption' => 'tls', 'mail_username' => 'contact@example.com', 'mail_password' => 'S3cret-Pass!']);
            $row = Database::fetch("SELECT * FROM settings WHERE setting_key = 'mail_password'");
            self::assertSame(1, (int) $row['is_secret']);
            self::assertNotSame('S3cret-Pass!', $row['setting_value']);
            self::assertStringNotContainsString('S3cret', (string) $row['setting_value']);
            self::assertSame('S3cret-Pass!', Setting::secret('mail_password'));
            self::assertArrayNotHasKey('mail_password', Setting::all(), 'Jamais dans le cache général');
            $page = $this->get('/admin/settings?group=mail')->body();
            self::assertStringNotContainsString('S3cret', $page);
            self::assertStringContainsString('smtp.example.com', $page);
            // Valeur vide : le secret est conservé ; case « effacer » : supprimé
            $this->post('/admin/settings/mail', ['mail_host' => 'smtp.example.com', 'mail_port' => '587', 'mail_encryption' => 'tls', 'mail_password' => '']);
            self::assertSame('S3cret-Pass!', Setting::secret('mail_password'));
            $this->post('/admin/settings/mail', ['mail_host' => 'smtp.example.com', 'mail_port' => '587', 'mail_encryption' => 'tls', 'clear_mail_password' => '1']);
            self::assertNull(Setting::secret('mail_password'));
            // Un administrateur simple ne voit pas les groupes sensibles
            Session::reset();
            Auth::reset();
            $this->login($this->admin);
            self::assertStringNotContainsString('group=mail', $this->get('/admin/settings')->body());
            self::assertSame(404, $this->post('/admin/settings/mail', ['mail_host' => 'x'])->status());
        } finally {
            Setting::set('site_name', (string) $original, 'general');
            foreach (['mail_host', 'mail_port', 'mail_username', 'mail_from_address', 'mail_from_name'] as $key) {
                Setting::set($key, '', 'mail');
            }
            Setting::set('mail_encryption', 'tls', 'mail');
            Setting::setSecret('mail_password', null, 'mail');
        }
    }

    public function testUserManagementAndScholarSheet(): void
    {
        $this->login($this->admin);
        // Création : e-mail de bienvenue avec lien de mot de passe
        $this->post('/admin/users', ['full_name' => 'Fatoumata Sow', 'email' => 'fatou@example.ml', 'role' => 'scholar', 'locale' => 'fr', 'country' => 'SN']);
        $created = Database::fetch("SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = 'fatou@example.ml'");
        self::assertNotNull($created);
        self::assertSame('scholar', $created['role']);
        self::assertNotNull(Database::fetch('SELECT 1 FROM scholar_profiles WHERE user_id = ?', [(int) $created['id']]));
        self::assertCount(1, MailService::$sent);
        self::assertSame('account_created', MailService::$sent[0]['template']);
        self::assertStringContainsString('/fr/reinitialiser/', MailService::$sent[0]['html']);
        // Un admin ne peut pas nommer un administrateur ; le super-admin oui
        self::assertSame(302, $this->post('/admin/users/' . $created['id'] . '/role', ['role' => 'admin'])->status());
        self::assertSame('scholar', Database::value('SELECT r.slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [(int) $created['id']]));
        // Suspension : le compte ne peut plus se connecter
        $this->post('/admin/users/' . $this->member . '/status', ['status' => 'suspended', 'reason' => 'Spam']);
        self::assertSame('suspended', Database::value('SELECT status FROM users WHERE id = ?', [$this->member]));
        self::assertGreaterThan(0, $this->logged('admin.user.status'));
        // Pas sur soi-même
        $this->post('/admin/users/' . $this->admin . '/status', ['status' => 'suspended']);
        self::assertSame('active', Database::value('SELECT status FROM users WHERE id = ?', [$this->admin]));
        // Fiche professeur : rubriques, langues, relecture
        $this->post('/admin/scholars/' . $this->scholar, ['rubriques' => [1, 3], 'languages' => ['fr', 'ar', 'bm'], 'max_open_questions' => 12, 'requires_review' => '1', 'is_public' => '1', 'is_available' => '1', 'sort_order' => 2, 'title' => ['fr' => 'Imam', 'ar' => 'إمام'], 'bio' => ['fr' => 'Diplômé de Médine.']]);
        $profile = Database::fetch('SELECT * FROM scholar_profiles WHERE user_id = ?', [$this->scholar]);
        self::assertSame(12, (int) $profile['max_open_questions']);
        self::assertSame('fr,ar,bm', $profile['languages']);
        self::assertSame(0, (int) $profile['can_self_assign']);
        self::assertSame([1, 3], array_map('intval', array_column(Database::fetchAll('SELECT rubrique_id FROM scholar_rubriques WHERE user_id = ? ORDER BY rubrique_id', [$this->scholar]), 'rubrique_id')));
        self::assertSame('إمام', Database::value("SELECT title FROM scholar_translations WHERE user_id = ? AND locale = 'ar'", [$this->scholar]));
        // Suppression (anonymisation) par l'admin
        $this->post('/admin/users/' . $this->member . '/delete');
        self::assertSame('Compte supprimé', Database::value('SELECT full_name FROM users WHERE id = ?', [$this->member]));
        self::assertNotNull(Database::value('SELECT deleted_at FROM users WHERE id = ?', [$this->member]));
    }

    public function testContentModulesRubriquesBannersPagesQuotesHome(): void
    {
        $this->askQuestion();
        $this->login($this->admin);
        // Rubrique : création traduite avec slugs, image, sous-rubrique
        $this->postWithFile('/admin/rubriques', ['code' => 'fiqh-famille', 'parent_id' => '2', 'color' => '#C9A24B', 'sort_order' => '7', 'is_active' => '1', 'icon' => '/assets/img/rubriques/islam.svg',
            'name' => ['fr' => 'Fiqh de la famille', 'ar' => 'فقه الأسرة', 'en' => 'Family fiqh'], 'short_description' => ['fr' => 'Mariage, divorce, héritage'], 'description' => ['fr' => '<p>Questions <b>familiales</b><script>x()</script></p>']], 'image', $this->pngFile());
        $rubrique = Database::fetch("SELECT * FROM rubriques WHERE code = 'fiqh-famille'");
        self::assertNotNull($rubrique);
        self::assertSame(2, (int) $rubrique['parent_id']);
        self::assertStringStartsWith('/uploads/rubriques/', (string) $rubrique['image_path']);
        self::assertSame('fiqh-de-la-famille', Database::value("SELECT slug FROM rubrique_translations WHERE rubrique_id = ? AND locale = 'fr'", [(int) $rubrique['id']]));
        self::assertSame('فقه-الأسرة', Database::value("SELECT slug FROM rubrique_translations WHERE rubrique_id = ? AND locale = 'ar'", [(int) $rubrique['id']]));
        self::assertStringNotContainsString('<script', (string) Database::value("SELECT description FROM rubrique_translations WHERE rubrique_id = ? AND locale = 'fr'", [(int) $rubrique['id']]));
        // Suppression refusée pour une rubrique utilisée, acceptée pour la nouvelle
        $this->post('/admin/rubriques/3/delete');
        self::assertNotNull(Database::fetch('SELECT 1 FROM rubriques WHERE id = 3'));
        $this->post('/admin/rubriques/' . $rubrique['id'] . '/delete');
        self::assertNull(Database::fetch('SELECT 1 FROM rubriques WHERE id = ?', [(int) $rubrique['id']]));
        \App\Services\ImageService::delete((string) $rubrique['image_path']);

        // Bannière : image obligatoire, puis création avec image
        $before = (int) Database::value('SELECT COUNT(*) FROM banners');
        $this->post('/admin/banners', ['title' => ['fr' => 'Sans image'], 'is_active' => '1']);
        self::assertSame($before, (int) Database::value('SELECT COUNT(*) FROM banners'));
        $this->postWithFile('/admin/banners', ['title' => ['fr' => 'Ramadan 1448', 'ar' => 'رمضان'], 'link_url' => 'route:questions.create', 'link2_url' => 'javascript:alert(1)', 'is_active' => '1', 'starts_at' => '2026-02-01T00:00', 'sort_order' => '0'], 'image_desktop', $this->pngFile());
        $banner = Database::fetch('SELECT * FROM banners ORDER BY id DESC LIMIT 1');
        self::assertSame($before + 1, (int) Database::value('SELECT COUNT(*) FROM banners'));
        self::assertStringStartsWith('/uploads/banners/', (string) $banner['image_desktop']);
        self::assertSame('route:questions.create', $banner['link_url']);
        self::assertNull($banner['link2_url'], 'Lien dangereux refusé');
        self::assertSame('رمضان', Database::value("SELECT title FROM banner_translations WHERE banner_id = ? AND locale = 'ar'", [(int) $banner['id']]));
        $this->post('/admin/banners/' . $banner['id'] . '/delete');
        self::assertNull(Database::fetch('SELECT 1 FROM banners WHERE id = ?', [(int) $banner['id']]));

        // Sections d'accueil : ordre et activation, chiffres manuels
        $this->post('/admin/home-sections', ['order' => ['hero', 'rubriques', 'voice_cta', 'how_it_works', 'latest_qa', 'daily_quote', 'latest_articles', 'scholars', 'stats', 'forum_ideas', 'cta', 'newsletter'], 'active' => ['hero', 'rubriques', 'voice_cta', 'stats'], 'stats_members' => '1200']);
        self::assertSame(2, (int) Database::value("SELECT sort_order FROM home_sections WHERE code = 'rubriques'"));
        self::assertSame(0, (int) Database::value("SELECT is_active FROM home_sections WHERE code = 'newsletter'"));
        self::assertSame('1200', Setting::get('stats_members'));
        $this->post('/admin/home-sections', ['order' => ['hero', 'voice_cta', 'how_it_works', 'rubriques', 'latest_qa', 'daily_quote', 'latest_articles', 'scholars', 'stats', 'forum_ideas', 'cta', 'newsletter'], 'active' => ['hero', 'voice_cta', 'how_it_works', 'rubriques', 'latest_qa', 'daily_quote', 'latest_articles', 'scholars', 'stats', 'forum_ideas', 'cta', 'newsletter'], 'stats_members' => '']);
        self::assertSame('', Setting::get('stats_members'));

        // Verset du jour planifié à une date : affiché ce jour-là
        $this->post('/admin/daily-quotes', ['type' => 'hadith', 'arabic_text' => 'إنما الأعمال بالنيات', 'reference' => 'Sahih al-Bukhari 1', 'display_date' => date('Y-m-d'), 'is_active' => '1', 'translation' => ['fr' => 'Les actes ne valent que par les intentions.', 'en' => 'Actions are judged by intentions.']]);
        $quote = Database::fetch("SELECT * FROM daily_quotes WHERE reference = 'Sahih al-Bukhari 1'");
        self::assertNotNull($quote);
        \App\Core\Cache::flush('');
        self::assertSame((int) $quote['id'], (int) \App\Models\DailyQuote::today('fr')['id']);
        $this->post('/admin/daily-quotes/' . $quote['id'] . '/delete');
        \App\Core\Cache::flush('');

        // Page : contenu riche purifié, slug conservé si le titre ne change pas
        $slugBefore = Database::value("SELECT slug FROM page_translations WHERE page_id = 1 AND locale = 'fr'");
        $this->post('/admin/pages/1', ['title' => ['fr' => 'À propos', 'ar' => 'من نحن', 'en' => 'About us'], 'content' => ['fr' => '<h2>Notre mission</h2><p>Répondre.</p><script>alert(1)</script>'], 'show_in_footer' => '1', 'is_active' => '1', 'sort_order' => '1']);
        $content = (string) Database::value("SELECT content FROM page_translations WHERE page_id = 1 AND locale = 'fr'");
        self::assertStringContainsString('<h2>Notre mission</h2>', $content);
        self::assertStringNotContainsString('<script', $content);
        self::assertSame($slugBefore, Database::value("SELECT slug FROM page_translations WHERE page_id = 1 AND locale = 'fr'"));
        foreach (['admin.rubrique.created', 'admin.rubrique.deleted', 'admin.banner.created', 'admin.home.updated', 'admin.quote.created', 'admin.page.updated'] as $action) {
            self::assertGreaterThan(0, $this->logged($action), $action);
        }
    }

    public function testContactReplyNewsletterExportAndBackups(): void
    {
        Database::execute("INSERT INTO contact_messages (name, email, subject, message, locale, status) VALUES ('Ali Traoré', 'ali@example.ml', 'partnership', 'Salam, je souhaite un partenariat.', 'en', 'new')");
        Database::execute("INSERT INTO newsletter_subscribers (email, locale, status, token_hash, confirmed_at) VALUES ('abonne@example.ml', 'fr', 'active', REPEAT('a', 64), UTC_TIMESTAMP()), ('attente@example.ml', 'ar', 'pending', REPEAT('b', 64), NULL)");
        $this->login($this->superadmin);
        $id = (int) Database::value('SELECT id FROM contact_messages LIMIT 1');
        $this->get('/admin/contact-messages/' . $id);
        self::assertSame('read', Database::value('SELECT status FROM contact_messages WHERE id = ?', [$id]));
        $this->post('/admin/contact-messages/' . $id . '/reply', ['body' => 'Wa alaykoum salam, merci pour votre proposition. Nous revenons vers vous.']);
        self::assertSame('replied', Database::value('SELECT status FROM contact_messages WHERE id = ?', [$id]));
        self::assertCount(1, MailService::$sent);
        self::assertSame('ali@example.ml', MailService::$sent[0]['to']);
        self::assertSame('en', MailService::$sent[0]['locale'], 'Réponse dans la langue du message');
        self::assertStringContainsString('Wa alaykoum salam', MailService::$sent[0]['html']);
        self::assertStringContainsString('je souhaite un partenariat', MailService::$sent[0]['html']);

        $csv = $this->get('/admin/newsletter/export?status=active&format=csv');
        $content = (string) file_get_contents($this->exportedFile($csv));
        self::assertStringContainsString('abonne@example.ml', $content);
        self::assertStringNotContainsString('attente@example.ml', $content);

        // Sauvegarde : création, liste, téléchargement, suppression (super-admin uniquement)
        $this->post('/admin/backups');
        $backups = BackupService::all();
        self::assertCount(1, $backups);
        $name = $backups[0]['name'];
        self::assertStringContainsString($name, $this->get('/admin/backups')->body());
        $download = $this->get('/admin/backups/' . $name . '/download');
        self::assertSame(200, $download->status());
        $sql = (string) gzdecode((string) file_get_contents((string) BackupService::path($name)));
        self::assertStringContainsString('CREATE TABLE `users`', $sql);
        self::assertStringContainsString('ali@example.ml', $sql);
        $this->post('/admin/backups/' . $name . '/delete');
        self::assertSame([], BackupService::all());
        self::assertSame(404, $this->get('/admin/backups/../../.env/download')->status());
        Session::reset();
        Auth::reset();
        $this->login($this->admin);
        self::assertSame(403, $this->post('/admin/backups')->status());
        foreach (['admin.contact.replied', 'admin.newsletter.exported', 'admin.backup.created', 'admin.backup.downloaded', 'admin.backup.deleted'] as $action) {
            self::assertGreaterThan(0, $this->logged($action), $action);
        }
    }
}
