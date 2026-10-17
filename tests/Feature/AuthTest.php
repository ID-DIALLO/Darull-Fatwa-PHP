<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Services\MailService;
use App\Services\TokenService;
use Tests\Support\DatabaseTestCase;

/**
 * Critères d'acceptation de la Phase 1 : inscription par téléphone seul, blocage après 6 échecs,
 * rôles (403), e-mails dans la langue de l'utilisateur, vérification, réinitialisation, suppression.
 */
final class AuthTest extends DatabaseTestCase
{
    public function testMemberCanRegisterWithPhoneOnlyAndLogIn(): void
    {
        $response = $this->post('/fr/inscription', [
            'full_name'             => 'Moussa Koné',
            'phone'                 => '76 12 34 56',
            'whatsapp_same'         => '1',
            'password'              => 'Secret1234!',
            'password_confirmation' => 'Secret1234!',
            'locale'                => 'fr',
            'country'               => 'ML',
            'terms'                 => '1',
        ]);

        self::assertSame(302, $response->status());
        self::assertSame('/fr/mon-espace', $response->getHeader('Location'));
        $user = Database::fetch('SELECT * FROM users WHERE phone = ?', ['+22376123456']);
        self::assertNotNull($user, 'Le numéro local est normalisé en +223…');
        self::assertNull($user['email']);
        self::assertSame('+22376123456', $user['whatsapp']);
        self::assertSame(1, (int) $user['role_id']);
        self::assertTrue(Auth::check(), 'Connecté automatiquement après l\'inscription');
        self::assertSame([], MailService::$sent, 'Pas d\'e-mail sans adresse');

        // Déconnexion puis reconnexion avec le seul numéro de téléphone
        $this->post('/fr/deconnexion');
        self::assertFalse(Auth::check());

        $login = $this->post('/fr/connexion', ['identifier' => '+223 76 12 34 56', 'password' => 'Secret1234!']);
        self::assertSame(302, $login->status());
        self::assertSame('/fr/mon-espace', $login->getHeader('Location'));
        self::assertTrue(Auth::check());
        self::assertSame(200, $this->get('/fr/mon-espace')->status());
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM activity_logs WHERE action = ?', ['user.login']));
    }

    public function testRegistrationRequiresPhoneOrEmail(): void
    {
        $response = $this->post('/en/register', [
            'full_name' => 'No Contact', 'password' => 'Secret1234!', 'password_confirmation' => 'Secret1234!', 'locale' => 'en', 'terms' => '1',
        ], ['HTTP_REFERER' => 'http://localhost/en/register']);

        self::assertSame(302, $response->status());
        $errors = (array) Session::getFlash('_errors');
        self::assertSame('Enter at least a phone number or an email address.', $errors['email'][0] ?? null);
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM users'));
    }

    public function testHoneypotRejectsRobots(): void
    {
        $response = $this->post('/fr/inscription', [
            'full_name' => 'Robot', 'email' => 'bot@example.com', 'password' => 'Secret1234!', 'password_confirmation' => 'Secret1234!',
            'locale' => 'fr', 'terms' => '1', 'website' => 'http://spam.example',
        ]);

        self::assertSame(302, $response->status());
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM users'));
    }

    public function testSixFailedAttemptsAreBlockedWith429(): void
    {
        $this->createUser(['email' => 'awa@example.ml']);

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('/fr/connexion', ['identifier' => 'awa@example.ml', 'password' => 'mauvais']);
            self::assertSame(302, $response->status(), "Tentative {$i} : retour au formulaire");
            self::assertSame('Identifiant ou mot de passe incorrect.', ((array) Session::getFlash('_errors'))['identifier'][0] ?? null);
        }

        $blocked = $this->post('/fr/connexion', ['identifier' => 'awa@example.ml', 'password' => 'MotDePasse123!']);
        self::assertSame(429, $blocked->status());
        self::assertStringContainsString('Trop de tentatives', $blocked->body());
        self::assertNotNull($blocked->getHeader('Retry-After'));
        self::assertFalse(Auth::check(), 'Même le bon mot de passe est refusé pendant le blocage');

        $arabic = $this->post('/ar/login', ['identifier' => 'awa@example.ml', 'password' => 'MotDePasse123!']);
        self::assertSame(429, $arabic->status());
        self::assertStringContainsString('محاولات كثيرة', $arabic->body());
    }

    public function testMemberCannotAccessAdminOrScholarAreas(): void
    {
        $id = $this->createUser();
        $this->login($id);

        self::assertSame(403, $this->get('/admin')->status());
        self::assertSame(403, $this->get('/fr/professeur')->status());
        self::assertSame(403, $this->get('/en/scholar')->status());
        self::assertSame(200, $this->get('/fr/mon-espace')->status());
    }

    public function testRolesOpenTheirAreas(): void
    {
        $scholar = $this->createUser(['email' => 'cheick@example.ml', 'role_id' => 2]);
        $this->login($scholar);
        self::assertSame(200, $this->get('/fr/professeur')->status());
        self::assertSame(403, $this->get('/admin')->status());

        Session::reset();
        Auth::reset();
        $admin = $this->createUser(['email' => 'admin@example.ml', 'role_id' => 4]);
        $this->login($admin);
        self::assertSame(200, $this->get('/admin')->status());
        self::assertSame(200, $this->get('/fr/professeur')->status());
        self::assertTrue(Auth::can('users.manage'));
    }

    public function testGuestIsRedirectedToLoginThenBackToIntendedPage(): void
    {
        $response = $this->get('/fr/mon-espace/profil');
        self::assertSame(302, $response->status());
        self::assertSame('/fr/connexion', $response->getHeader('Location'));

        $this->createUser(['email' => 'awa@example.ml']);
        $login = $this->post('/fr/connexion', ['identifier' => 'Awa@Example.ml', 'password' => 'MotDePasse123!']);
        self::assertSame('/fr/mon-espace/profil', $login->getHeader('Location'));
    }

    public function testExternalRedirectTargetsAreIgnored(): void
    {
        $this->createUser(['email' => 'awa@example.ml']);
        $login = $this->post('/fr/connexion', ['identifier' => 'awa@example.ml', 'password' => 'MotDePasse123!', 'redirect' => 'https://evil.example/']);
        self::assertSame('/fr/mon-espace', $login->getHeader('Location'));
    }

    public function testEmailsAreSentInTheUserLanguage(): void
    {
        $this->post('/ar/register', [
            'full_name' => 'فاطمة تراوري', 'email' => 'fatima@example.ml', 'password' => 'Secret1234!', 'password_confirmation' => 'Secret1234!',
            'locale' => 'ar', 'terms' => '1',
        ]);

        self::assertCount(1, MailService::$sent);
        $mail = MailService::$sent[0];
        self::assertSame('fatima@example.ml', $mail['to']);
        self::assertSame('ar', $mail['locale']);
        self::assertSame('تحقّق من بريدك الإلكتروني — دار الفتوى', $mail['subject']);
        self::assertStringContainsString('dir="rtl"', $mail['html']);
        self::assertStringContainsString('/ar/verify-email/', $mail['html']);

        // Un membre anglophone reçoit l'e-mail de réinitialisation en anglais
        Session::reset();
        Auth::reset();
        $this->createUser(['email' => 'john@example.com', 'locale' => 'en']);
        MailService::$sent = [];
        $this->post('/fr/mot-de-passe-oublie', ['email' => 'john@example.com']);
        self::assertSame('Reset your password — Darul Fatwa', MailService::$sent[0]['subject'] ?? null);
        self::assertStringContainsString('/en/reset/', MailService::$sent[0]['html']);
    }

    public function testEmailVerificationLinkWorksOnce(): void
    {
        $this->post('/fr/inscription', [
            'full_name' => 'Awa Diarra', 'email' => 'awa@example.ml', 'password' => 'Secret1234!', 'password_confirmation' => 'Secret1234!',
            'locale' => 'fr', 'terms' => '1',
        ]);
        preg_match('#/fr/verifier-email/([a-f0-9]{64})#', MailService::$sent[0]['html'], $m);
        self::assertNotEmpty($m[1] ?? null);

        $response = $this->get('/fr/verifier-email/' . $m[1]);
        self::assertSame(302, $response->status());
        self::assertNotNull(Database::value('SELECT email_verified_at FROM users WHERE email = ?', ['awa@example.ml']));
        self::assertContains('Votre adresse e-mail est vérifiée. Merci !', $this->flashMessages());

        Session::reset();
        $again = $this->get('/fr/verifier-email/' . $m[1]);
        self::assertContains('Ce lien de vérification est invalide ou a expiré.', $this->flashMessages());
        self::assertSame(302, $again->status());
    }

    public function testPasswordResetFlow(): void
    {
        $id = $this->createUser(['email' => 'awa@example.ml']);
        $this->post('/fr/mot-de-passe-oublie', ['email' => 'awa@example.ml']);
        preg_match('#/fr/reinitialiser/([a-f0-9]{64})#', MailService::$sent[0]['html'], $m);

        self::assertSame(200, $this->get('/fr/reinitialiser/' . $m[1])->status());
        $response = $this->post('/fr/reinitialiser/' . $m[1], ['password' => 'NouveauMdp2026!', 'password_confirmation' => 'NouveauMdp2026!']);
        self::assertSame('/fr/mon-espace', $response->getHeader('Location'));
        self::assertTrue(Auth::check());
        self::assertTrue(password_verify('NouveauMdp2026!', (string) Database::value('SELECT password_hash FROM users WHERE id = ?', [$id])));
        self::assertNull(TokenService::find($m[1], TokenService::PASSWORD_RESET), 'Jeton à usage unique');

        // Un lien inconnu renvoie vers le formulaire avec un message
        Session::reset();
        Auth::reset();
        self::assertSame('/fr/mot-de-passe-oublie', $this->get('/fr/reinitialiser/' . str_repeat('a', 64))->getHeader('Location'));
    }

    public function testUnknownEmailGetsTheSameAnswerWithoutMail(): void
    {
        $response = $this->post('/en/forgot-password', ['email' => 'nobody@example.com']);
        self::assertSame('/en/login', $response->getHeader('Location'));
        self::assertSame([], MailService::$sent);
    }

    public function testRememberMeCookieRestoresTheSession(): void
    {
        $this->createUser(['email' => 'awa@example.ml']);
        $login = $this->post('/fr/connexion', ['identifier' => 'awa@example.ml', 'password' => 'MotDePasse123!', 'remember' => '1']);
        $cookie = null;
        foreach ($login->cookies() as $c) {
            if ($c['name'] === Auth::REMEMBER_COOKIE) {
                $cookie = $c['value'];
                self::assertTrue($c['options']['httponly']);
                self::assertGreaterThan(time() + 29 * 86400, $c['options']['expires']);
            }
        }
        self::assertNotNull($cookie);

        // Nouvelle « session » : seul le cookie subsiste
        Session::reset();
        Auth::reset();
        $response = $this->get('/fr/mon-espace', [], [Auth::REMEMBER_COOKIE => $cookie]);
        self::assertSame(200, $response->status());
        self::assertTrue(Auth::check());
        // Le jeton a été renouvelé : l'ancien ne fonctionne plus
        self::assertNull(TokenService::find($cookie, TokenService::REMEMBER));
    }

    public function testChangingPasswordLogsOutOtherDevices(): void
    {
        $id = $this->createUser(['email' => 'awa@example.ml']);
        $this->login($id);
        $otherDeviceHash = Session::get('auth_hash');

        $response = $this->post('/fr/mon-espace/securite/mot-de-passe', [
            'current_password' => 'MotDePasse123!', 'password' => 'NouveauMdp2026!', 'password_confirmation' => 'NouveauMdp2026!',
        ]);
        self::assertSame('/fr/mon-espace/securite', $response->getHeader('Location'));
        self::assertTrue(Auth::check(), 'L\'appareil courant reste connecté');
        self::assertSame('password_changed', MailService::$sent[0]['template'] ?? null);

        // Un autre appareil, avec l'ancienne empreinte de session, est déconnecté
        Session::reset();
        Auth::reset();
        Session::set('user_id', $id);
        Session::set('auth_hash', $otherDeviceHash);
        self::assertFalse(Auth::check());
    }

    public function testProfileUpdateAndEmailChangeTriggersVerification(): void
    {
        $id = $this->createUser(['email' => 'awa@example.ml', 'email_verified_at' => '2026-01-01 00:00:00']);
        $this->login($id);

        $response = $this->post('/fr/mon-espace/profil', [
            'full_name' => 'Awa Diarra Traoré', 'display_name' => 'Awa', 'email' => 'nouvelle@example.ml', 'phone' => '',
            'whatsapp_same' => '1', 'locale' => 'en', 'country' => 'BF', 'city' => 'Ouagadougou', 'gender' => 'f',
            'preferred_channel' => 'both', 'notify_email' => '1',
        ]);
        self::assertSame('/en/my-account/profile', $response->getHeader('Location'));
        $user = Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
        self::assertSame('nouvelle@example.ml', $user['email']);
        self::assertNull($user['email_verified_at']);
        self::assertSame('en', $user['locale']);
        self::assertSame('BF', $user['country']);
        self::assertSame(0, (int) $user['notify_whatsapp']);
        self::assertSame('verify_email', MailService::$sent[0]['template'] ?? null);
        self::assertSame('en', MailService::$sent[0]['locale']);
    }

    public function testAccountExportAndDeletion(): void
    {
        $id = $this->createUser(['email' => 'awa@example.ml']);
        Database::execute("INSERT INTO questions (user_id, rubrique_id, input_type, status) VALUES (?, 1, 'text', 'new')", [$id]);
        $this->login($id);

        $export = $this->get('/fr/mon-espace/export');
        self::assertSame(200, $export->status());
        self::assertStringContainsString('attachment', (string) $export->getHeader('Content-Disposition'));
        $json = json_decode($export->body(), true);
        self::assertSame('awa@example.ml', $json['profile']['email']);
        self::assertArrayNotHasKey('password_hash', $json['profile']);
        self::assertCount(1, $json['questions']);

        $delete = $this->post('/fr/mon-espace/supprimer', ['password' => 'MotDePasse123!', 'confirm' => '1']);
        self::assertSame('/fr', $delete->getHeader('Location'));
        self::assertFalse(Auth::check());
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM questions WHERE user_id = ?', [$id]));
        $user = Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
        self::assertNotNull($user['deleted_at']);
        self::assertSame('Compte supprimé', $user['full_name']);
        self::assertStringNotContainsString('awa@', (string) $user['email']);
        self::assertSame('account_deleted', MailService::$sent[0]['template'] ?? null);

        // Impossible de se reconnecter
        self::assertSame(302, $this->post('/fr/connexion', ['identifier' => 'awa@example.ml', 'password' => 'MotDePasse123!'])->status());
        self::assertFalse(Auth::check());
    }

    public function testAuthPagesRenderInThreeLanguages(): void
    {
        self::assertStringContainsString('Créer un compte', $this->get('/fr/inscription')->body());
        self::assertStringContainsString('dir="rtl"', $this->get('/ar/register')->body());
        self::assertStringContainsString('إنشاء حساب', $this->get('/ar/register')->body());
        self::assertStringContainsString('Log in', $this->get('/en/login')->body());
        self::assertStringContainsString('Forgot password', $this->get('/en/forgot-password')->body());
    }
}
