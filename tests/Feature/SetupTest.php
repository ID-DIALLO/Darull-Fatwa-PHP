<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use Tests\Support\DatabaseTestCase;

/**
 * Phase 9 : page de mise en service /setup (premier super-administrateur sans SSH), protégée par SETUP_TOKEN
 * et désactivée dès qu'un administrateur existe.
 */
final class SetupTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_ENV['SETUP_TOKEN'], $_SERVER['SETUP_TOKEN']);
        putenv('SETUP_TOKEN');
    }

    protected function tearDown(): void
    {
        unset($_ENV['SETUP_TOKEN'], $_SERVER['SETUP_TOKEN']);
        putenv('SETUP_TOKEN');
        parent::tearDown();
    }

    public function testLaPageEstIntrouvableSansJetonConfigure(): void
    {
        self::assertSame(404, $this->get('/setup')->status());
        self::assertSame(404, $this->post('/setup', ['token' => 'x'])->status());
    }

    public function testCreationDuPremierSuperAdministrateurPuisFermeture(): void
    {
        $_ENV['SETUP_TOKEN'] = 'mot-secret-de-mise-en-service';
        $page = $this->get('/setup');
        self::assertSame(200, $page->status());
        self::assertStringContainsString(__('auth.setup.title'), $page->body());
        self::assertStringContainsString('name="token"', $page->body());

        // Mauvais jeton : rien n'est créé
        $this->post('/setup', ['token' => 'faux', 'full_name' => 'Admin Principal', 'email' => 'admin@darulfatwa.test', 'password' => 'Secret1234!', 'password_confirmation' => 'Secret1234!']);
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM users'));
        self::assertStringContainsString(__('auth.setup.bad_token'), implode(' ', $this->flashMessages()));

        // Mot de passe non confirmé : refusé
        $this->post('/setup', ['token' => 'mot-secret-de-mise-en-service', 'full_name' => 'Admin Principal', 'email' => 'admin@darulfatwa.test', 'password' => 'Secret1234!', 'password_confirmation' => 'autre']);
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM users'));

        // Bon jeton : super-administrateur créé, connecté, redirigé vers l'administration
        $response = $this->post('/setup', ['token' => 'mot-secret-de-mise-en-service', 'full_name' => 'Admin Principal', 'email' => 'admin@darulfatwa.test', 'password' => 'Secret1234!', 'password_confirmation' => 'Secret1234!']);
        self::assertSame(302, $response->status());
        self::assertStringContainsString('/admin', (string) $response->getHeader('Location'));
        $user = Database::fetch('SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?', ['admin@darulfatwa.test']);
        self::assertSame('superadmin', $user['role']);
        self::assertSame('active', $user['status']);
        self::assertNotNull($user['email_verified_at']);
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM activity_logs WHERE action = 'user.admin_created' AND details LIKE '%setup%'"));
        self::assertSame(200, $this->get('/admin')->status());

        // Un administrateur existe : la page n'existe plus, même avec le jeton
        \App\Core\Session::reset();
        \App\Core\Auth::reset();
        self::assertSame(404, $this->get('/setup')->status());
        self::assertSame(404, $this->post('/setup', ['token' => 'mot-secret-de-mise-en-service', 'full_name' => 'Intrus', 'email' => 'intrus@example.ml', 'password' => 'Secret1234!', 'password_confirmation' => 'Secret1234!'])->status());
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM users'));
    }

    public function testLaPageEstTraduiteEnArabe(): void
    {
        $_ENV['SETUP_TOKEN'] = 'jeton';
        $page = $this->get('/setup', [], ['lang' => 'ar']);
        self::assertSame(200, $page->status());
        self::assertStringContainsString('dir="rtl"', $page->body());
        self::assertStringContainsString(\App\Core\Lang::get('auth.setup.title', [], 'ar'), $page->body());
    }
}
