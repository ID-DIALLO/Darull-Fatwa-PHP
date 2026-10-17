<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Services\MailService;
use App\Services\StaffInvitationService;
use Tests\Support\DatabaseTestCase;

/**
 * Comptes créés par l'administration (professeurs, modérateurs…) : invitation = lien d'activation + code à 6 chiffres,
 * la personne saisit son identifiant et le code, choisit son mot de passe et arrive dans son espace.
 */
final class StaffInvitationTest extends DatabaseTestCase
{
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createUser(['email' => 'admin@example.ml', 'full_name' => 'Admin Principal', 'role_id' => 4]);
    }

    /** @return array{id: int, url: string, code: string} */
    private function createStaff(array $fields, string $role): array
    {
        Session::reset();
        Auth::reset();
        $this->login($this->admin);
        $response = $this->post('/admin/users', $fields + ['role' => $role, 'locale' => 'fr']);
        self::assertSame(302, $response->status());
        $id = (int) Database::value('SELECT id FROM users ORDER BY id DESC LIMIT 1');
        $invitation = \App\Services\Admin\UserAdminService::$lastInvitation;
        self::assertNotNull($invitation);
        self::assertMatchesRegularExpression('/^\d{6}$/', $invitation['code']);
        self::assertStringContainsString('/fr/activation/', $invitation['url']);
        $page = $this->get('/admin/users/' . $id);
        self::assertStringContainsString($invitation['url'], $page->body(), 'le lien est affiché une fois à l\'administrateur');
        self::assertStringContainsString(substr($invitation['code'], 0, 3) . ' ' . substr($invitation['code'], 3), $page->body());
        // (en production, le message flash disparaît à la requête suivante : le code n'est affiché qu'une fois)
        Session::reset();
        Auth::reset();

        return ['id' => $id, 'url' => $invitation['url'], 'code' => $invitation['code']];
    }

    public function testUnProfesseurActiveSonCompteAvecSonTelephoneEtLeCode(): void
    {
        $staff = $this->createStaff(['full_name' => 'Cheick Moussa Koné', 'phone' => '+22376001199'], 'scholar');
        self::assertSame('pending', Database::value('SELECT status FROM users WHERE id = ?', [$staff['id']]));
        self::assertCount(0, MailService::$sent, 'pas d\'e-mail : le lien et le code sont transmis par l\'administrateur');
        $path = parse_url($staff['url'], PHP_URL_PATH);

        // Tant que le compte n'est pas activé, la connexion est refusée avec un message dédié
        $this->post('/fr/connexion', ['identifier' => '+22376001199', 'password' => 'Motdepasse123']);
        self::assertNull(Auth::id());

        self::assertSame(200, $this->get($path)->status());
        self::assertStringContainsString(__('auth.activate.title'), $this->get($path)->body());

        // Mauvais code : refusé, compte toujours en attente
        $this->post($path, ['identifier' => '76 00 11 99', 'code' => '000000', 'password' => 'Motdepasse123', 'password_confirmation' => 'Motdepasse123']);
        self::assertSame('pending', Database::value('SELECT status FROM users WHERE id = ?', [$staff['id']]));
        self::assertNull(Auth::id());

        // Bon identifiant (numéro local normalisé) + bon code : activé, connecté, redirigé vers l'espace professeur
        $response = $this->post($path, ['identifier' => '76 00 11 99', 'code' => substr($staff['code'], 0, 3) . ' ' . substr($staff['code'], 3), 'password' => 'Motdepasse123', 'password_confirmation' => 'Motdepasse123']);
        self::assertSame(302, $response->status());
        self::assertStringContainsString('/fr/professeur', (string) $response->getHeader('Location'));
        $user = Database::fetch('SELECT * FROM users WHERE id = ?', [$staff['id']]);
        self::assertSame('active', $user['status']);
        self::assertNotNull($user['phone_verified_at']);
        self::assertSame($staff['id'], Auth::id());
        self::assertSame(200, $this->get('/fr/professeur')->status());

        // Le lien est à usage unique
        Session::reset();
        Auth::reset();
        self::assertSame(302, $this->get($path)->status());
        self::assertStringContainsString(__('auth.activate.invalid_link'), implode(' ', $this->flashMessages()));

        // Connexion classique ensuite
        $this->post('/fr/connexion', ['identifier' => '+22376001199', 'password' => 'Motdepasse123']);
        self::assertSame($staff['id'], Auth::id());
    }

    public function testUnModerateurRecoitLInvitationParEmailEtArriveDansLAdministration(): void
    {
        $staff = $this->createStaff(['full_name' => 'Fatou Modératrice', 'email' => 'modo@example.ml'], 'moderator');
        self::assertCount(1, MailService::$sent);
        $mail = MailService::$sent[0];
        self::assertSame('staff_invitation', $mail['template']);
        self::assertSame('modo@example.ml', $mail['to']);
        self::assertStringContainsString($staff['url'], $mail['html']);
        self::assertStringContainsString(substr($staff['code'], 0, 3) . ' ' . substr($staff['code'], 3), $mail['html']);
        $path = parse_url($staff['url'], PHP_URL_PATH);

        // L'identifiant doit être celui du compte (e-mail insensible à la casse)
        $this->post($path, ['identifier' => 'autre@example.ml', 'code' => $staff['code'], 'password' => 'Motdepasse123', 'password_confirmation' => 'Motdepasse123']);
        self::assertNull(Auth::id());
        $response = $this->post($path, ['identifier' => 'Modo@Example.ml', 'code' => $staff['code'], 'password' => 'Motdepasse123', 'password_confirmation' => 'Motdepasse123']);
        self::assertStringContainsString('/admin', (string) $response->getHeader('Location'));
        self::assertSame('active', Database::value('SELECT status FROM users WHERE id = ?', [$staff['id']]));
        self::assertNotNull(Database::value('SELECT email_verified_at FROM users WHERE id = ?', [$staff['id']]));
        self::assertSame(200, $this->get('/admin')->status());
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM activity_logs WHERE action = 'user.invitation_accepted' AND subject_id = ?", [$staff['id']]));
    }

    public function testLAdministrateurPeutRegenererUneInvitationQuiInvalideLaPrecedente(): void
    {
        $staff = $this->createStaff(['full_name' => 'Oustaz Ibrahim', 'email' => 'ibrahim@example.ml'], 'scholar');
        $first = parse_url($staff['url'], PHP_URL_PATH);

        $this->login($this->admin);
        $this->post('/admin/users/' . $staff['id'] . '/invite');
        $second = \App\Services\Admin\UserAdminService::$lastInvitation; // inchangé : l'invitation vient du service
        $renewed = StaffInvitationService::pending($staff['id']);
        self::assertNotNull($renewed);
        self::assertCount(2, MailService::$sent);
        $newUrl = preg_match('#(https?://\S+/fr/activation/[a-f0-9]{64})#', MailService::$sent[1]['html'], $m) ? $m[1] : '';
        self::assertNotSame($staff['url'], $newUrl);
        Session::reset();
        Auth::reset();
        self::assertSame(302, $this->get($first)->status(), 'ancien lien invalide');
        self::assertSame(200, $this->get(parse_url($newUrl, PHP_URL_PATH))->status(), 'nouveau lien valide');
        unset($second);
    }

    public function testLesPagesDActivationExistentEnTroisLangues(): void
    {
        $staff = $this->createStaff(['full_name' => 'Membre Invité', 'email' => 'invite@example.ml'], 'member');
        $token = basename((string) parse_url($staff['url'], PHP_URL_PATH));
        foreach (['/fr/activation/' . $token, '/ar/activate/' . $token, '/en/activate/' . $token] as $uri) {
            $page = $this->get($uri);
            self::assertSame(200, $page->status(), $uri);
            self::assertStringContainsString('name="code"', $page->body());
        }
        self::assertStringContainsString('dir="rtl"', $this->get('/ar/activate/' . $token)->body());
        // Lien inconnu
        self::assertSame(302, $this->get('/fr/activation/' . str_repeat('a', 64))->status());
    }
}
