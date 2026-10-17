<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Core\Session;
use App\Services\MailService;
use App\Services\SlugService;
use Tests\Support\DatabaseTestCase;

/**
 * Critères d'acceptation de la Phase 2 : accueil FR/AR/EN alimenté par la base, rubriques et pages
 * avec slugs traduits, changement de langue conservant la page, contact limité à 3 envois/heure, newsletter.
 */
final class PublicPagesTest extends DatabaseTestCase
{
    /** Les tables de contenu (rubriques, pages, bannières…) viennent des seeds et ne sont pas vidées. */
    protected const TABLES = ['activity_logs', 'message_queue', 'user_tokens', 'login_attempts', 'rate_limits', 'contact_messages', 'newsletter_subscribers', 'scholar_rubriques', 'scholar_translations', 'scholar_profiles', 'users', 'questions'];

    public function testHomeShowsEverySectionInThreeLanguages(): void
    {
        $fr = $this->get('/fr')->body();
        self::assertStringContainsString('Posez vos questions sur l&#039;Islam, en toute confiance', $fr);
        self::assertStringContainsString('Appuyez, parlez, envoyez', $fr);
        self::assertStringContainsString('Comment ça marche ?', $fr);
        self::assertStringContainsString('L&#039;Unicité de Dieu', $fr);
        self::assertStringContainsString('du jour', $fr);
        self::assertStringContainsString('data-counter', $fr);
        self::assertStringContainsString('Une question vous préoccupe ?', $fr);
        self::assertStringContainsString('newsletter_email', $fr);
        self::assertStringContainsString('data-hero-slider', $fr);
        self::assertStringContainsString('fetchpriority="high"', $fr);

        $ar = $this->get('/ar')->body();
        self::assertStringContainsString('<html lang="ar" dir="rtl">', $ar);
        self::assertStringContainsString('اطرح أسئلتك عن الإسلام بكل ثقة', $ar);
        self::assertStringContainsString('/ar/topics/' . rawurlencode('التوحيد'), $ar);
        self::assertStringContainsString('اضغط، تكلّم، أرسل', $ar);

        $en = $this->get('/en')->body();
        self::assertStringContainsString('Ask your questions about Islam with confidence', $en);
        self::assertStringContainsString('/en/topics/oneness-of-god', $en);
        self::assertStringContainsString('Tap, speak, send', $en);
    }

    public function testDisabledHomeSectionIsHidden(): void
    {
        Database::execute('UPDATE home_sections SET is_active = 0 WHERE code = ?', ['how_it_works']);
        try {
            self::assertStringNotContainsString('Comment ça marche ?', $this->get('/fr')->body());
        } finally {
            Database::execute('UPDATE home_sections SET is_active = 1 WHERE code = ?', ['how_it_works']);
        }
    }

    public function testRubriquePagesUseTranslatedSlugsAndKeepThePageWhenSwitchingLanguage(): void
    {
        $index = $this->get('/fr/rubriques');
        self::assertSame(200, $index->status());
        self::assertStringContainsString('/fr/rubriques/coran', $index->body());

        $fr = $this->get('/fr/rubriques/coran');
        self::assertSame(200, $fr->status());
        self::assertStringContainsString('Le Coran', $fr->body());
        // Le sélecteur de langue mène à la même rubrique dans l'autre langue (slug arabe encodé)
        self::assertStringContainsString('hreflang="ar" href="http://localhost/ar/topics/' . rawurlencode('القرآن-الكريم') . '"', $fr->body());
        self::assertStringContainsString('hreflang="en" href="http://localhost/en/topics/holy-quran"', $fr->body());

        $ar = $this->get('/ar/topics/' . rawurlencode('القرآن-الكريم'));
        self::assertSame(200, $ar->status());
        self::assertStringContainsString('القرآن الكريم', $ar->body());
        self::assertStringContainsString('dir="rtl"', $ar->body());

        self::assertSame(404, $this->get('/fr/rubriques/inconnue')->status());
        self::assertSame(404, $this->get('/en/topics/coran')->status(), 'Le slug français ne fonctionne pas sur la version anglaise');
    }

    public function testCmsPagesAndFooterLinks(): void
    {
        $page = $this->get('/fr/page/a-propos');
        self::assertSame(200, $page->status());
        self::assertStringContainsString('À propos', $page->body());
        self::assertStringContainsString('hreflang="en" href="http://localhost/en/page/about"', $page->body());
        self::assertStringContainsString('/fr/page/conditions-generales', $page->body(), 'Lien CGU dans le pied de page');
        self::assertSame(200, $this->get('/ar/page/' . rawurlencode('من-نحن'))->status());
        self::assertSame(404, $this->get('/fr/page/inexistante')->status());
    }

    public function testScholarsListAndProfile(): void
    {
        self::assertStringContainsString('Les profils des professeurs seront publiés prochainement.', $this->get('/fr/professeurs')->body());

        $id = $this->createUser(['email' => 'cheick@example.ml', 'role_id' => 2, 'full_name' => 'Cheick Moussa Koné']);
        Database::execute("INSERT INTO scholar_profiles (user_id, languages, is_public) VALUES (?, 'fr,ar', 1)", [$id]);
        Database::execute("INSERT INTO scholar_translations (user_id, locale, title, bio) VALUES (?, 'fr', 'Cheick', 'Biographie en français.')", [$id]);
        Database::execute('INSERT INTO scholar_rubriques (user_id, rubrique_id) VALUES (?, 3)', [$id]);

        $list = $this->get('/fr/professeurs')->body();
        self::assertStringContainsString('Cheick Moussa Koné', $list);
        self::assertStringContainsString('/fr/professeurs/' . $id . '-cheick-moussa-kone', $list);

        $profile = $this->get('/en/scholars/' . $id . '-cheick-moussa-kone');
        self::assertSame(200, $profile->status());
        self::assertStringContainsString('Biographie en français.', $profile->body());
        self::assertStringContainsString('not yet available in English', $profile->body(), 'Bandeau de repli de langue');
        self::assertStringContainsString('/en/topics/holy-quran', $profile->body());
        self::assertSame(404, $this->get('/fr/professeurs/9999-inconnu')->status());

        // Le professeur apparaît sur la page de sa rubrique et sur l'accueil
        self::assertStringContainsString('Cheick Moussa Koné', $this->get('/fr/rubriques/coran')->body());
        self::assertStringContainsString('Cheick Moussa Koné', $this->get('/fr')->body());
    }

    public function testContactFormStoresMessageSendsEmailsAndIsThrottled(): void
    {
        self::assertSame(200, $this->get('/fr/contact')->status());

        $data = ['name' => 'Awa', 'email' => 'awa@example.ml', 'phone' => '', 'subject' => 'technical', 'message' => 'Le micro ne fonctionne pas sur mon téléphone.'];
        $response = $this->post('/fr/contact', $data);
        self::assertSame('/fr/contact', $response->getHeader('Location'));
        self::assertContains('Merci ! Votre message a bien été envoyé. Nous vous répondrons dès que possible, in shâ Allah.', $this->flashMessages());
        $row = Database::fetch('SELECT * FROM contact_messages');
        self::assertSame('technical', $row['subject']);
        self::assertSame('fr', $row['locale']);
        self::assertCount(2, MailService::$sent, 'E-mail à l\'administration + accusé de réception');
        self::assertSame('contact_received', MailService::$sent[0]['template']);
        self::assertSame('contact_ack', MailService::$sent[1]['template']);
        self::assertSame('awa@example.ml', MailService::$sent[1]['to']);

        $this->post('/fr/contact', $data);
        $this->post('/fr/contact', $data);
        $blocked = $this->post('/fr/contact', $data);
        self::assertSame(429, $blocked->status(), 'Bloqué après 3 envois par heure et par IP');
        self::assertSame(3, (int) Database::value('SELECT COUNT(*) FROM contact_messages'));
    }

    public function testContactRequiresEmailOrPhoneAndRejectsRobots(): void
    {
        $this->post('/en/contact', ['name' => 'Bot', 'subject' => 'other', 'message' => 'Hello hello hello', 'website' => 'spam']);
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM contact_messages'));

        $this->post('/en/contact', ['name' => 'John', 'subject' => 'other', 'message' => 'Hello hello hello'], ['HTTP_REFERER' => 'http://localhost/en/contact']);
        $errors = (array) Session::getFlash('_errors');
        self::assertStringContainsString('at least one of the two', $errors['email'][0] ?? '');
    }

    public function testNewsletterDoubleOptIn(): void
    {
        $response = $this->post('/newsletter', ['newsletter_email' => 'Lecteur@Example.ml', 'locale' => 'ar'], ['HTTP_REFERER' => 'http://localhost/ar']);
        self::assertSame('http://localhost/ar', $response->getHeader('Location'));
        self::assertSame('pending', Database::value('SELECT status FROM newsletter_subscribers WHERE email = ?', ['lecteur@example.ml']));
        self::assertSame('ar', MailService::$sent[0]['locale'] ?? null);
        preg_match('#/ar/newsletter/confirm/([a-f0-9]{64})#', MailService::$sent[0]['html'], $m);
        self::assertNotEmpty($m[1] ?? null);

        $this->get('/ar/newsletter/confirm/' . $m[1]);
        self::assertSame('active', Database::value('SELECT status FROM newsletter_subscribers WHERE email = ?', ['lecteur@example.ml']));

        Session::reset();
        $this->post('/newsletter', ['newsletter_email' => 'lecteur@example.ml']);
        self::assertContains('Cette adresse est déjà abonnée.', $this->flashMessages());

        $this->get('/fr/newsletter/desinscription/' . $m[1]);
        self::assertSame('unsubscribed', Database::value('SELECT status FROM newsletter_subscribers WHERE email = ?', ['lecteur@example.ml']));
        self::assertSame(302, $this->get('/fr/newsletter/confirmer/' . str_repeat('0', 64))->status());
    }

    public function testSlugsForLatinAndArabic(): void
    {
        self::assertSame('cheick-moussa-kone', SlugService::make('Cheick Moussa Koné'));
        self::assertSame('l-unicite-de-dieu', SlugService::make("L'Unicité de Dieu !"));
        self::assertSame('القرآن-الكريم', SlugService::make('القرآنُ الكريمُ'));
        self::assertSame('item', SlugService::make('!!!'));
        self::assertSame('coran-2', SlugService::unique('Coran', 'rubrique_translations', 'fr'));
        self::assertSame('coran', SlugService::unique('Coran', 'rubrique_translations', 'fr', ['rubrique_id' => 3]));
    }
}
