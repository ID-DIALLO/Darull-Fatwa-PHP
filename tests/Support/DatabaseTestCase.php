<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Installer;
use App\Services\MailService;
use PHPUnit\Framework\TestCase;

/**
 * Tests de fonctionnement avec une vraie base MySQL/MariaDB (DB_DATABASE forcée à « darul_fatwa_test »
 * par phpunit.xml, identifiants lus dans .env). Le schéma est réinstallé une fois par exécution,
 * les tables de données sont vidées avant chaque test. Sans base disponible, les tests sont ignorés.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected App $app;

    private static bool $installed = false;

    /** Tables vidées avant chaque test (ordre sans importance : clés étrangères désactivées). */
    protected const TABLES = [
        'activity_logs', 'notifications', 'message_queue', 'user_tokens', 'login_attempts', 'rate_limits',
        'question_messages', 'question_status_logs', 'question_assignments', 'questions', 'media_files',
        'qa_publication_translations', 'qa_publications', 'forum_posts', 'forum_topics', 'forum_subscriptions',
        'idea_votes', 'idea_comments', 'ideas', 'comments', 'likes', 'favorites', 'reports', 'contact_messages',
        'newsletter_subscribers', 'whatsapp_messages', 'article_tag', 'article_translations', 'articles', 'tags',
        'scholar_rubriques', 'scholar_translations', 'scholar_profiles', 'users',
    ];

    protected function setUp(): void
    {
        $this->app = App::create(dirname(__DIR__, 2));
        try {
            $pdo = Database::pdo();
        } catch (\PDOException $e) {
            self::markTestSkipped('Base de test indisponible : ' . $e->getMessage());
        }
        if (!self::$installed) {
            $installer = new Installer(dirname(__DIR__, 2));
            $installer->dropAllTables($pdo);
            $installer->installSchema($pdo);
            self::$installed = true;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (static::TABLES as $table) {
            $pdo->exec('TRUNCATE TABLE `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        Session::reset();
        Auth::reset();
        MailService::fake();
    }

    protected function tearDown(): void
    {
        MailService::restore();
        restore_error_handler();
    }

    /** @param array<string, string> $server */
    protected function get(string $uri, array $server = [], array $cookies = []): Response
    {
        return $this->app->handle(Request::create($uri, 'GET', [], $server, $cookies));
    }

    /**
     * Requête POST avec jeton CSRF.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $server
     */
    protected function post(string $uri, array $data = [], array $server = [], array $cookies = []): Response
    {
        return $this->app->handle(Request::create($uri, 'POST', $data + ['_token' => Csrf::token()], $server, $cookies));
    }

    /**
     * Crée un utilisateur directement en base et retourne son identifiant.
     *
     * @param array<string, mixed> $overrides
     */
    protected function createUser(array $overrides = []): int
    {
        $data = $overrides + [
            'uuid'          => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'role_id'       => 1,
            'full_name'     => 'Awa Diarra',
            'email'         => 'awa@example.ml',
            'phone'         => null,
            'password_hash' => Auth::hashPassword('MotDePasse123!'),
            'locale'        => 'fr',
            'status'        => 'active',
        ];
        $columns = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', array_keys($data)));
        Database::execute('INSERT INTO users (' . $columns . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')', array_values($data));

        return Database::lastInsertId();
    }

    protected function login(int $userId): void
    {
        Auth::login($userId);
    }

    /** Messages flash en attente (après une redirection). @return list<string> */
    protected function flashMessages(): array
    {
        return array_map(static fn (array $f): string => $f['message'], \App\Core\Flash::all());
    }
}
