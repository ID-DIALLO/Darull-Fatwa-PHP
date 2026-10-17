<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Models\User;
use App\Services\Admin\PublicationService;
use App\Services\AnswerService;
use App\Services\ArticleService;
use App\Services\AssignmentService;
use App\Services\SearchService;
use Tests\Support\DatabaseTestCase;

/**
 * Phase 6 : blog multilingue (bascule de langue, soumission par un professeur, planification), Q/R publiques
 * anonymisées (QAPage), recherche plein texte dans les trois langues, favoris, partage, commentaires modérés.
 */
final class BlogQaSearchTest extends DatabaseTestCase
{
    private int $member;
    private int $scholar;
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createUser(['email' => 'awa@example.ml', 'full_name' => 'Awa Diarra', 'display_name' => 'Oum Fatima', 'phone' => '+22376123499', 'country' => 'ML']);
        $this->scholar = $this->createUser(['email' => 'cheick@example.ml', 'full_name' => 'Cheick Moussa Koné', 'role_id' => 2]);
        $this->admin = $this->createUser(['email' => 'admin@example.ml', 'full_name' => 'Admin Principal', 'role_id' => 4]);
        Database::execute("INSERT INTO scholar_profiles (user_id, languages, can_self_assign, requires_review, is_public) VALUES (?, 'fr,ar', 1, 0, 1)", [$this->scholar]);
        Database::execute('INSERT INTO scholar_rubriques (user_id, rubrique_id) VALUES (?, 3)', [$this->scholar]);
        \App\Core\Cache::flush('');
    }

    private function switchTo(int $userId): void
    {
        Session::reset();
        Auth::reset();
        $this->login($userId);
    }

    /** @param array<string, mixed> $overrides */
    private function createArticle(array $overrides = []): int
    {
        $this->switchTo($this->admin);
        $this->post('/admin/articles', $overrides + [
            'status' => 'published', 'rubrique_id' => 3, 'author_id' => $this->scholar, 'allow_comments' => '1', 'is_featured' => '1',
            'title' => ['fr' => 'Le tayammum, purification par la terre', 'ar' => 'التيمم طهارة بالتراب'],
            'excerpt' => ['fr' => 'Quand l\'eau manque, le tayammum remplace les ablutions.'],
            'content' => ['fr' => '<h2>Définition</h2><p>Le tayammum remplace les ablutions quand l\'eau manque ou nuit.</p><blockquote lang="ar">فَتَيَمَّمُوا صَعِيدًا طَيِّبًا</blockquote>', 'ar' => '<p>التيمم بديل عن الوضوء عند فقد الماء أو الضرر.</p>'],
            'tags' => ['fr' => 'purification, voyage', 'ar' => 'الطهارة'],
        ]);

        return (int) Database::value('SELECT id FROM articles ORDER BY id DESC LIMIT 1');
    }

    /** Question posée, assignée, répondue puis publiée (anonymisée). @return array{question: array<string, mixed>, publication_id: int} */
    private function publishQa(): array
    {
        $this->switchTo($this->member);
        $this->post('/fr/poser-une-question', ['rubrique_id' => 3, 'body' => 'Je m\'appelle Awa, à Bamako : puis-je lire le Coran sur mon téléphone sans ablutions ?', 'spoken_language' => 'fr', 'answer_language' => 'fr', 'answer_channel' => 'platform', 'visibility' => 'publishable']);
        $question = Database::fetch('SELECT * FROM questions ORDER BY id DESC LIMIT 1');
        $this->switchTo($this->admin);
        $question = AssignmentService::assign($question, $this->scholar, $this->admin);
        $this->switchTo($this->scholar);
        $this->get('/fr/professeur/questions/' . $question['reference']);
        AnswerService::answer(Database::fetch('SELECT * FROM questions WHERE id = ?', [(int) $question['id']]), User::findWithRole($this->scholar), '<p>Oui, la lecture sur écran est permise sans ablutions, car l\'écran n\'est pas un mushaf.</p>', null, [['type' => 'quran', 'surah' => 56, 'ayah_from' => 79]], 'fr');
        $this->switchTo($this->admin);
        $id = PublicationService::publishFromQuestion(\App\Models\Question::findDetailed((int) $question['id']), [
            'title' => 'Lire le Coran sur un téléphone sans ablutions', 'question_text' => 'Peut-on lire le Coran sur un téléphone sans être en état d\'ablutions ?',
            'answer_html' => '<p>Oui, la lecture sur écran est permise sans ablutions, car l\'écran n\'est pas un mushaf.</p>', 'status' => 'published',
        ], $this->admin);
        Session::reset();
        Auth::reset();

        return ['question' => $question, 'publication_id' => $id];
    }

    public function testArticlePublishedInFrAndArSwitchesLanguage(): void
    {
        $id = $this->createArticle();
        $article = Database::fetch('SELECT * FROM articles WHERE id = ?', [$id]);
        self::assertSame('published', $article['status']);
        self::assertSame(1, (int) $article['reading_minutes']);
        $slugs = ArticleService::slugs($id);
        self::assertSame('le-tayammum-purification-par-la-terre', $slugs['fr']);
        self::assertArrayHasKey('ar', $slugs);
        self::assertArrayNotHasKey('en', $slugs);
        self::assertSame(['purification', 'voyage'], array_column(ArticleService::tags($id, 'fr'), 'name'));
        Session::reset();
        Auth::reset();

        // Liste FR : article à la une ; page FR : bascule vers AR, et vers la liste EN avec message
        $list = $this->get('/fr/blog');
        self::assertSame(200, $list->status());
        self::assertStringContainsString('Le tayammum, purification par la terre', $list->body());
        self::assertStringContainsString('À la une', $list->body());
        $fr = $this->get('/fr/blog/' . $slugs['fr']);
        self::assertSame(200, $fr->status());
        self::assertStringContainsString('hreflang="ar" href="http://localhost/ar/blog/' . rawurlencode($slugs['ar']), $fr->body());
        self::assertStringContainsString('/en/blog?missing=' . $id, $fr->body());
        self::assertStringContainsString('"@type":"Article"', $fr->body());
        self::assertStringContainsString('Cheick Moussa Koné', $fr->body(), 'Auteur public');
        self::assertStringContainsString('wa.me/?text=', $fr->body(), 'Partage WhatsApp');
        self::assertStringContainsString('#purification', $fr->body());
        $ar = $this->get('/ar/blog/' . $slugs['ar']);
        self::assertSame(200, $ar->status());
        self::assertStringContainsString('dir="rtl"', $ar->body());
        self::assertStringContainsString('التيمم طهارة بالتراب', $ar->body());
        self::assertStringContainsString('hreflang="fr" href="http://localhost/fr/blog/' . $slugs['fr'], $ar->body());
        // En anglais : pas de traduction → liste vide avec message ; URL FR inconnue en EN → 404
        $en = $this->get('/en/blog?missing=' . $id);
        self::assertSame(200, $en->status());
        self::assertStringContainsString('not available in this language', $en->body());
        self::assertStringNotContainsString('Le tayammum', $en->body());
        self::assertSame(404, $this->get('/en/blog/' . $slugs['fr'])->status());
        // Filtre par tag et par rubrique, compteur de vues
        self::assertStringContainsString($slugs['fr'], $this->get('/fr/blog?tag=voyage')->body());
        self::assertStringContainsString('Aucun article ne correspond', $this->get('/fr/blog?tag=inexistant')->body());
        self::assertSame(2, (int) Database::value('SELECT views FROM articles WHERE id = ?', [$id]), 'Deux lectures (FR et AR)');
        // Accueil et page de rubrique : sections alimentées
        self::assertStringContainsString($slugs['fr'], $this->get('/fr')->body());
        self::assertStringContainsString($slugs['fr'], $this->get('/fr/rubriques/coran')->body());
    }

    public function testPublishedQaContainsNoPersonalData(): void
    {
        ['question' => $question, 'publication_id' => $id] = $this->publishQa();
        $slug = (string) Database::value("SELECT slug FROM qa_publication_translations WHERE publication_id = ? AND locale = 'fr'", [$id]);
        $page = $this->get('/fr/questions-reponses/' . $slug);
        self::assertSame(200, $page->status());
        $body = $page->body();
        foreach (['Awa', 'Diarra', 'Oum Fatima', '+22376123499', 'awa@example.ml', 'Je m\'appelle', $question['reference']] as $personal) {
            self::assertStringNotContainsString($personal, $body, 'Donnée personnelle : ' . $personal);
        }
        self::assertStringContainsString('"@type":"QAPage"', $body);
        self::assertStringContainsString('Cheick Moussa Koné', $body, 'Professeur public nommé');
        self::assertStringContainsString('la lecture sur écran est permise', $body);
        self::assertStringContainsString('sourate Al-Wâqiʿa (56), verset(s) 79', $body, 'Référence coranique formatée');
        self::assertStringContainsString('/fr/poser-une-question?rubrique=coran', $body, 'Poser une question similaire présélectionne la rubrique');
        self::assertStringContainsString('hreflang="ar" href="http://localhost/ar/questions-answers?missing=' . $id, $body);
        // Liste, tri par popularité, filtre rubrique
        self::assertStringContainsString($slug, $this->get('/fr/questions-reponses?sort=popular&rubrique=3')->body());
        self::assertStringNotContainsString($slug, $this->get('/fr/questions-reponses?rubrique=1')->body());
        self::assertSame(1, (int) Database::value('SELECT views FROM qa_publications WHERE id = ?', [$id]));
        // Une publication en brouillon ou archivée n'est pas visible
        Database::execute("UPDATE qa_publications SET status = 'archived' WHERE id = ?", [$id]);
        self::assertSame(404, $this->get('/fr/questions-reponses/' . $slug)->status());
    }

    public function testSearchFindsContentInThreeLanguages(): void
    {
        $this->createArticle(['title' => ['fr' => 'Le tayammum, purification par la terre', 'ar' => 'التيمم طهارة بالتراب', 'en' => 'Tayammum, purification with earth'], 'content' => ['fr' => '<p>Le tayammum remplace les ablutions quand l\'eau manque.</p>', 'ar' => '<p>التيمم بديل عن الوضوء عند فقد الماء.</p>', 'en' => '<p>Dry ablution with clean soil replaces wudu when water is unavailable.</p>']]);
        $this->publishQa();
        Session::reset();
        Auth::reset();

        self::assertSame(['tayammum'], SearchService::words('+tayammum* "'));
        $fr = $this->get('/fr/recherche?q=ablutions');
        self::assertSame(200, $fr->status());
        self::assertStringContainsString('le-tayammum-purification-par-la-terre', $fr->body(), 'Mot du contenu FR');
        self::assertStringContainsString('lire-le-coran-sur-un-telephone-sans-ablutions', $fr->body(), 'Q/R trouvée aussi');
        $ar = $this->get('/ar/search?q=' . rawurlencode('الوضوء'));
        self::assertStringContainsString(rawurlencode('التيمم-طهارة-بالتراب'), $ar->body(), 'Mot du contenu AR');
        $en = $this->get('/en/search?q=soil');
        self::assertStringContainsString('tayammum-purification-with-earth', $en->body(), 'Mot du contenu EN');
        self::assertStringNotContainsString('le-tayammum', $en->body(), 'Résultats dans la langue courante seulement');
        // Préfixe, mot court (repli LIKE) et absence de résultat
        self::assertStringContainsString('le-tayammum', $this->get('/fr/recherche?q=tayam')->body());
        self::assertStringContainsString('le-tayammum', $this->get('/fr/recherche?q=' . rawurlencode('ea'))->body());
        self::assertStringContainsString('Aucun résultat', $this->get('/fr/recherche?q=xyzinexistant')->body());
        // Recherche dans la liste des Q/R et du blog
        self::assertStringContainsString('lire-le-coran', $this->get('/fr/questions-reponses?q=mushaf')->body());
        self::assertStringContainsString('le-tayammum', $this->get('/fr/blog?q=terre')->body());
    }

    public function testFavoritesCommentsAndModeration(): void
    {
        $id = $this->createArticle();
        $slug = ArticleService::slugs($id)['fr'];
        ['publication_id' => $qaId] = $this->publishQa();
        $this->switchTo($this->member);
        // Favoris : ajout, liste, retrait, JSON
        $this->post('/fr/favoris', ['type' => 'article', 'id' => $id]);
        $this->post('/fr/favoris', ['type' => 'qa', 'id' => $qaId]);
        self::assertSame(2, (int) Database::value('SELECT COUNT(*) FROM favorites WHERE user_id = ?', [$this->member]));
        $favorites = $this->get('/fr/mon-espace/favoris')->body();
        self::assertStringContainsString($slug, $favorites);
        self::assertStringContainsString('Lire le Coran sur un téléphone', $favorites);
        self::assertStringContainsString('Retirer des favoris', $this->get('/fr/blog/' . $slug)->body());
        $json = $this->app->handle(\App\Core\Request::create('/fr/favoris', 'POST', ['type' => 'article', 'id' => $id, '_token' => \App\Core\Csrf::token()], ['HTTP_ACCEPT' => 'application/json']));
        self::assertSame(['favorite' => false, 'count' => 0], json_decode($json->body(), true));
        self::assertSame(404, $this->post('/fr/favoris', ['type' => 'article', 'id' => 9999])->status());
        // Commentaire : en attente, invisible publiquement, visible pour son auteur ; équipe notifiée
        $this->post('/fr/blog/' . $slug . '/commentaire', ['body' => 'Barak Allahu fikoum pour cet article très clair.']);
        $commentId = (int) Database::value('SELECT id FROM comments ORDER BY id DESC LIMIT 1');
        self::assertSame('pending', Database::value('SELECT status FROM comments WHERE id = ?', [$commentId]));
        self::assertStringContainsString('en attente de relecture', $this->get('/fr/blog/' . $slug)->body());
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'comment.pending'", [$this->admin]));
        Session::reset();
        Auth::reset();
        self::assertStringNotContainsString('Barak Allahu fikoum', $this->get('/fr/blog/' . $slug)->body());
        // Visiteur : invitation à se connecter, pas de dépôt possible
        self::assertStringContainsString('Connectez-vous pour commenter', $this->get('/fr/blog/' . $slug)->body());
        self::assertSame(302, $this->post('/fr/blog/' . $slug . '/commentaire', ['body' => 'Anonyme'])->status());
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM comments'));
        // Modération par l'administration → visible, puis réponse imbriquée
        $this->switchTo($this->admin);
        self::assertStringContainsString('Barak Allahu fikoum', $this->get('/admin/comments')->body());
        $this->post('/admin/comments/' . $commentId . '/moderate', ['status' => 'approved']);
        Session::reset();
        Auth::reset();
        $public = $this->get('/fr/blog/' . $slug)->body();
        self::assertStringContainsString('Barak Allahu fikoum', $public);
        self::assertStringContainsString('Oum Fatima', $public, 'Pseudonyme affiché');
        self::assertStringNotContainsString('Awa Diarra', $public);
        $this->switchTo($this->scholar);
        $this->post('/fr/blog/' . $slug . '/commentaire', ['body' => 'Wa fikoum barak Allah.', 'parent_id' => $commentId]);
        self::assertSame($commentId, (int) Database::value('SELECT parent_id FROM comments ORDER BY id DESC LIMIT 1'));
        // Commentaires désactivés sur l'article → 403 ; Q/R : commentaire possible
        Database::execute('UPDATE articles SET allow_comments = 0 WHERE id = ?', [$id]);
        self::assertSame(403, $this->post('/fr/blog/' . $slug . '/commentaire', ['body' => 'Encore un'])->status());
        $qaSlug = (string) Database::value("SELECT slug FROM qa_publication_translations WHERE publication_id = ? AND locale = 'fr'", [$qaId]);
        $this->post('/fr/questions-reponses/' . $qaSlug . '/commentaire', ['body' => 'Très utile, merci.']);
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM comments WHERE commentable_type = 'qa' AND commentable_id = ?", [$qaId]));
        self::assertGreaterThan(0, (int) Database::value("SELECT COUNT(*) FROM activity_logs WHERE action = 'admin.comment.approved'"));
    }

    public function testScholarSubmissionAdminApprovalAndScheduling(): void
    {
        // Le professeur rédige et soumet : jamais publié directement
        $this->switchTo($this->scholar);
        self::assertSame(200, $this->get('/fr/professeur/articles')->status());
        self::assertSame(200, $this->get('/fr/professeur/articles/nouveau')->status());
        $this->post('/fr/professeur/articles', ['title' => ['fr' => 'Les mérites du mois de Rajab'], 'content' => ['fr' => '<p>Rajab est un mois sacré.</p>'], 'rubrique_id' => 2, 'status' => 'published', 'submit' => 'submit']);
        $article = Database::fetch('SELECT * FROM articles ORDER BY id DESC LIMIT 1');
        self::assertSame('submitted', $article['status']);
        self::assertSame($this->scholar, (int) $article['author_id']);
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'article.submitted'", [$this->admin]));
        self::assertSame(404, $this->get('/fr/blog/les-merites-du-mois-de-rajab')->status(), 'Pas visible avant validation');
        self::assertStringContainsString('Les mérites du mois de Rajab', $this->get('/fr/professeur/articles')->body());
        // Un autre professeur ne voit pas cet article
        $other = $this->createUser(['email' => 'autre@example.ml', 'full_name' => 'Autre Prof', 'role_id' => 2]);
        $this->switchTo($other);
        self::assertSame(404, $this->get('/fr/professeur/articles/' . $article['id'])->status());
        // L'administration publie : auteur notifié, article visible, cache des stats rafraîchi
        $this->switchTo($this->admin);
        self::assertStringContainsString('Soumis', $this->get('/admin/articles?status=submitted')->body());
        $this->post('/admin/articles/' . $article['id'], ['title' => ['fr' => 'Les mérites du mois de Rajab'], 'content' => ['fr' => '<p>Rajab est un mois sacré.</p>'], 'rubrique_id' => 2, 'author_id' => $this->scholar, 'status' => 'published', 'allow_comments' => '1']);
        self::assertSame('published', Database::value('SELECT status FROM articles WHERE id = ?', [$article['id']]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'article.published'", [$this->scholar]));
        Session::reset();
        Auth::reset();
        self::assertSame(200, $this->get('/fr/blog/les-merites-du-mois-de-rajab')->status());
        // Le professeur ne peut plus modifier un article publié
        $this->switchTo($this->scholar);
        self::assertSame(409, $this->post('/fr/professeur/articles/' . $article['id'], ['title' => ['fr' => 'Modifié'], 'content' => ['fr' => '<p>x</p>']])->status());

        // Planification : date future → « planifié », invisible ; le cron publie quand l'heure est passée
        $this->switchTo($this->admin);
        $future = (new \DateTimeImmutable('+2 days', new \DateTimeZone((string) config('app.timezone'))))->format('Y-m-d\TH:i');
        $this->post('/admin/articles', ['title' => ['fr' => 'Préparer le Ramadan'], 'content' => ['fr' => '<p>Conseils pratiques.</p>'], 'status' => 'published', 'published_at' => $future, 'author_id' => $this->admin]);
        $scheduled = Database::fetch('SELECT * FROM articles ORDER BY id DESC LIMIT 1');
        self::assertSame('scheduled', $scheduled['status']);
        Session::reset();
        Auth::reset();
        self::assertSame(404, $this->get('/fr/blog/preparer-le-ramadan')->status());
        self::assertSame(0, ArticleService::publishScheduled());
        Database::execute('UPDATE articles SET published_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = ?', [$scheduled['id']]);
        self::assertSame(1, ArticleService::publishScheduled());
        self::assertSame(200, $this->get('/fr/blog/preparer-le-ramadan')->status());
        // Suppression logique et rubrique
        $this->switchTo($this->admin);
        $this->post('/admin/articles/' . $scheduled['id'] . '/delete');
        self::assertNotNull(Database::value('SELECT deleted_at FROM articles WHERE id = ?', [$scheduled['id']]));
        Session::reset();
        Auth::reset();
        self::assertSame(404, $this->get('/fr/blog/preparer-le-ramadan')->status());
    }
}
