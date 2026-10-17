<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Models\Setting;
use Tests\Support\DatabaseTestCase;

/**
 * Phase 7 : forum communautaire (modération a priori des premiers messages, charte, abonnements et notifications,
 * J'aime, badge « validé par un professeur », signalements, mots interdits) et boîte à idées (vote unique, statuts,
 * réponse officielle notifiée, pages d'administration).
 */
final class ForumIdeasTest extends DatabaseTestCase
{
    private int $member;
    private int $other;
    private int $scholar;
    private int $moderator;
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createUser(['email' => 'awa@example.ml', 'full_name' => 'Awa Diarra', 'display_name' => 'Oum Fatima']);
        $this->other = $this->createUser(['email' => 'moussa@example.ml', 'full_name' => 'Moussa Traoré']);
        $this->scholar = $this->createUser(['email' => 'cheick@example.ml', 'full_name' => 'Cheick Moussa Koné', 'role_id' => 2]);
        $this->moderator = $this->createUser(['email' => 'modo@example.ml', 'full_name' => 'Fatou Modératrice', 'role_id' => 3]);
        $this->admin = $this->createUser(['email' => 'admin@example.ml', 'full_name' => 'Admin Principal', 'role_id' => 4]);
        Database::execute("INSERT INTO scholar_profiles (user_id, languages, can_self_assign, requires_review, is_public) VALUES (?, 'fr,ar', 1, 0, 1)", [$this->scholar]);
        Setting::set('forum_first_posts_moderated', '3', 'forum');
        Setting::set('forum_banned_words', '', 'forum');
        \App\Core\Cache::flush('');
    }

    private function switchTo(?int $userId): void
    {
        Session::reset();
        Auth::reset();
        if ($userId !== null) {
            $this->login($userId);
        }
    }

    /** Crée un sujet ouvert par un utilisateur non soumis à la modération (le professeur). */
    private function openTopic(string $title = 'Comment organiser un cercle de lecture du Coran ?'): int
    {
        $this->switchTo($this->scholar);
        $this->post('/fr/forum/nouveau-sujet', ['category_id' => 3, 'title' => $title, 'body' => 'Je souhaite organiser un cercle hebdomadaire et je cherche des conseils pratiques.', 'language' => 'fr', 'accept_rules' => '1']);
        $id = (int) Database::value('SELECT id FROM forum_topics ORDER BY id DESC LIMIT 1');
        self::assertSame('open', Database::value('SELECT status FROM forum_topics WHERE id = ?', [$id]));

        return $id;
    }

    /** URL canonique d'un sujet (la forme courte /forum/sujet/{id} redirige en 301 vers elle). */
    private function topicUrl(int $id): string
    {
        return '/fr/forum/sujet/' . $id . '-' . Database::value('SELECT slug FROM forum_topics WHERE id = ?', [$id]);
    }

    // --- Forum -------------------------------------------------------------------------------------------------

    public function testLesTroisPremiersMessagesDUnNouveauMembreSontEnAttenteDeModeration(): void
    {
        $topicId = $this->openTopic();
        $this->switchTo($this->member);

        // 1er message : un nouveau sujet, en attente
        $response = $this->post('/fr/forum/nouveau-sujet', ['category_id' => 7, 'title' => 'Présentation : nouvelle membre de Bamako', 'body' => 'Assalamu alaykum, je rejoins la communauté et je me présente brièvement.', 'language' => 'fr', 'accept_rules' => '1']);
        self::assertSame(302, $response->status());
        $mine = (int) Database::value('SELECT id FROM forum_topics WHERE user_id = ?', [$this->member]);
        self::assertSame('pending', Database::value('SELECT status FROM forum_topics WHERE id = ?', [$mine]));
        self::assertStringContainsString(__('forum.flash.topic_pending'), implode(' ', $this->flashMessages()));

        // L'auteur voit son sujet en attente (liste et page) ; les autres, non
        self::assertStringContainsString('Présentation : nouvelle membre', $this->get('/fr/forum')->body());
        self::assertSame(301, $this->get('/fr/forum/sujet/' . $mine)->status());
        self::assertSame(200, $this->get($this->topicUrl($mine))->status());
        $this->switchTo($this->other);
        self::assertStringNotContainsString('Présentation : nouvelle membre', $this->get('/fr/forum')->body());
        self::assertSame(404, $this->get($this->topicUrl($mine))->status());

        // Les modérateurs ont été prévenus
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'forum.pending'", [$this->moderator]));

        // 2e et 3e messages : des réponses, en attente aussi
        $this->switchTo($this->member);
        $this->post('/fr/forum/sujet/' . $topicId . '/repondre', ['body' => 'Première réponse de la nouvelle membre.']);
        $this->post('/fr/forum/sujet/' . $topicId . '/repondre', ['body' => 'Deuxième réponse de la nouvelle membre.']);
        self::assertSame(['pending', 'pending'], array_column(Database::fetchAll('SELECT status FROM forum_posts WHERE user_id = ? ORDER BY id', [$this->member]), 'status'));
        self::assertSame(0, (int) Database::value('SELECT replies_count FROM forum_topics WHERE id = ?', [$topicId]));

        // Le modérateur approuve les trois contributions : elles deviennent publiques et l'auteur est notifié
        $this->switchTo($this->moderator);
        $this->post('/admin/forum/topics/' . $mine, ['action' => 'approve']);
        foreach (Database::fetchAll('SELECT id FROM forum_posts WHERE user_id = ?', [$this->member]) as $post) {
            $this->post('/admin/forum/posts/' . $post['id'], ['action' => 'approve']);
        }
        self::assertSame('open', Database::value('SELECT status FROM forum_topics WHERE id = ?', [$mine]));
        self::assertSame(2, (int) Database::value('SELECT replies_count FROM forum_topics WHERE id = ?', [$topicId]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'forum.topic_approved'", [$this->member]));
        self::assertSame(2, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'forum.post_approved'", [$this->member]));

        // 4e message : publié directement
        $this->switchTo($this->member);
        $this->post('/fr/forum/sujet/' . $topicId . '/repondre', ['body' => 'Troisième réponse, cette fois publiée sans attente.']);
        self::assertSame('visible', Database::value('SELECT status FROM forum_posts WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$this->member]));
        self::assertStringContainsString('publiée sans attente', $this->get($this->topicUrl($topicId))->body());
    }

    public function testLaCharteDoitEtreAccepteeAvantLePremierMessage(): void
    {
        $this->switchTo($this->member);
        $page = $this->get('/fr/forum/nouveau-sujet');
        self::assertSame(200, $page->status());
        self::assertStringContainsString('name="accept_rules"', $page->body());

        $this->post('/fr/forum/nouveau-sujet', ['category_id' => 7, 'title' => 'Sujet sans charte acceptée', 'body' => 'Ce message ne doit pas être enregistré car la charte n\'est pas acceptée.', 'language' => 'fr']);
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM forum_topics'));
        self::assertNull(Database::value('SELECT forum_rules_accepted_at FROM users WHERE id = ?', [$this->member]));

        $this->post('/fr/forum/nouveau-sujet', ['category_id' => 7, 'title' => 'Sujet avec charte acceptée', 'body' => 'Ce message est enregistré car la charte a été acceptée.', 'language' => 'fr', 'accept_rules' => '1']);
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM forum_topics'));
        self::assertNotNull(Database::value('SELECT forum_rules_accepted_at FROM users WHERE id = ?', [$this->member]));

        // La charte n'est plus demandée ensuite
        $this->switchTo($this->member);
        self::assertStringNotContainsString('name="accept_rules"', $this->get('/fr/forum/nouveau-sujet')->body());
    }

    public function testLesAbonnesRecoiventUneNotificationLorsDUneNouvelleReponse(): void
    {
        $topicId = $this->openTopic();
        $slug = (string) Database::value('SELECT slug FROM forum_topics WHERE id = ?', [$topicId]);

        // Un membre s'abonne sans écrire
        $this->switchTo($this->member);
        $this->post('/fr/forum/sujet/' . $topicId . '/abonnement');
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM forum_subscriptions WHERE topic_id = ? AND user_id = ?', [$topicId, $this->member]));
        self::assertStringContainsString(__('forum.unsubscribe'), $this->get($this->topicUrl($topicId))->body());

        // L'administrateur répond : l'auteur du sujet (abonné automatiquement) et le membre abonné sont prévenus, pas l'auteur de la réponse
        $this->switchTo($this->admin);
        $response = $this->post('/fr/forum/sujet/' . $topicId . '/repondre', ['body' => 'Voici une réponse publiée immédiatement.', 'accept_rules' => '1']);
        self::assertSame(302, $response->status());
        $postId = (int) Database::value('SELECT id FROM forum_posts ORDER BY id DESC LIMIT 1');
        self::assertSame('visible', Database::value('SELECT status FROM forum_posts WHERE id = ?', [$postId]));
        $notified = array_map('intval', array_column(Database::fetchAll("SELECT user_id FROM notifications WHERE type = 'forum.reply' ORDER BY user_id"), 'user_id'));
        self::assertSame([$this->member, $this->scholar], $notified);
        $url = (string) Database::value("SELECT url FROM notifications WHERE type = 'forum.reply' AND user_id = ?", [$this->member]);
        self::assertStringContainsString('/forum/sujet/' . $topicId . '-' . $slug . '#post-' . $postId, $url);

        // Désabonnement : plus de notification
        $this->switchTo($this->member);
        $this->post('/fr/forum/sujet/' . $topicId . '/abonnement');
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM forum_subscriptions WHERE topic_id = ? AND user_id = ?', [$topicId, $this->member]));
        $this->switchTo($this->admin);
        $this->post('/fr/forum/sujet/' . $topicId . '/repondre', ['body' => 'Seconde réponse après désabonnement.']);
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE type = 'forum.reply' AND user_id = ?", [$this->member]));
    }

    public function testJAimeCitationEtBadgeValideParUnProfesseur(): void
    {
        $topicId = $this->openTopic();
        $this->switchTo($this->admin);
        $this->post('/fr/forum/sujet/' . $topicId . '/repondre', ['body' => 'Le cercle gagne à être court et régulier.', 'accept_rules' => '1']);
        $postId = (int) Database::value('SELECT id FROM forum_posts ORDER BY id DESC LIMIT 1');

        // Citation d'une réponse
        $this->switchTo($this->other);
        $this->post('/fr/forum/sujet/' . $topicId . '/repondre', ['body' => 'Je suis d\'accord avec cette idée de régularité.', 'quoted_post_id' => $postId, 'accept_rules' => '1']);
        self::assertSame($postId, (int) Database::value('SELECT quoted_post_id FROM forum_posts ORDER BY id DESC LIMIT 1'));

        // J'aime (bascule JSON, contrôle serveur)
        $this->switchTo($this->member);
        $json = $this->post('/fr/forum/jaime', ['type' => 'forum_post', 'id' => $postId], ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(200, $json->status());
        self::assertSame(['liked' => true, 'count' => 1], json_decode($json->body(), true));
        $json = $this->post('/fr/forum/jaime', ['type' => 'forum_post', 'id' => $postId], ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(['liked' => false, 'count' => 0], json_decode($json->body(), true));
        self::assertSame(0, (int) Database::value('SELECT likes_count FROM forum_posts WHERE id = ?', [$postId]));

        // Seul un professeur (ou un administrateur) peut valider une réponse
        self::assertSame(403, $this->post('/fr/forum/message/' . $postId . '/valider')->status());
        $this->switchTo($this->scholar);
        $this->post('/fr/forum/message/' . $postId . '/valider');
        self::assertSame(1, (int) Database::value('SELECT is_validated FROM forum_posts WHERE id = ?', [$postId]));
        $this->switchTo(null);
        $page = $this->get($this->topicUrl($topicId))->body();
        self::assertStringContainsString('df-post--validated', $page);
        self::assertStringContainsString(__('forum.validated_by', ['name' => 'Cheick Moussa Koné']), $page);
        // Le nom réel de la membre n'apparaît jamais : seul son pseudonyme
        self::assertStringNotContainsString('Awa Diarra', $page);
    }

    public function testMotsInterditsEtSignalements(): void
    {
        Setting::set('forum_banned_words', "casino\nescroquerie", 'forum');
        $topicId = $this->openTopic();

        // Un mot interdit met la réponse en attente, même pour un membre de confiance
        $this->switchTo($this->admin);
        $this->post('/fr/forum/sujet/' . $topicId . '/repondre', ['body' => 'Venez jouer au CASINO en ligne.', 'accept_rules' => '1']);
        self::assertSame('pending', Database::value('SELECT status FROM forum_posts ORDER BY id DESC LIMIT 1'));

        $this->post('/fr/forum/sujet/' . $topicId . '/repondre', ['body' => 'Une réponse tout à fait correcte.']);
        $postId = (int) Database::value('SELECT id FROM forum_posts ORDER BY id DESC LIMIT 1');

        // Signalement : une seule fois par membre et par contenu
        $this->switchTo($this->member);
        $this->post('/fr/forum/signaler', ['type' => 'forum_post', 'id' => $postId, 'reason' => 'off_topic', 'details' => 'Hors sujet.']);
        $this->post('/fr/forum/signaler', ['type' => 'forum_post', 'id' => $postId, 'reason' => 'spam']);
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM reports WHERE reporter_id = ?', [$this->member]));
        $reportId = (int) Database::value('SELECT id FROM reports ORDER BY id DESC LIMIT 1');

        // Le modérateur traite le signalement en masquant le contenu
        $this->switchTo($this->moderator);
        $list = $this->get('/admin/reports');
        self::assertSame(200, $list->status());
        self::assertStringContainsString('Hors sujet.', $list->body());
        $this->post('/admin/reports/' . $reportId, ['action' => 'hide']);
        self::assertSame('resolved', Database::value('SELECT status FROM reports WHERE id = ?', [$reportId]));
        self::assertSame('hidden', Database::value('SELECT status FROM forum_posts WHERE id = ?', [$postId]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM activity_logs WHERE action = 'admin.report.resolved'"));
    }

    public function testModerationDepuisLeSujetEtPagesDAdministrationDuForum(): void
    {
        $topicId = $this->openTopic();

        // Un membre ne peut pas modérer
        $this->switchTo($this->member);
        self::assertSame(403, $this->post('/fr/forum/sujet/' . $topicId . '/moderer', ['action' => 'lock'])->status());
        self::assertSame(403, $this->get('/admin/forum')->status());

        // Le modérateur verrouille : plus de réponse possible pour un membre
        $this->switchTo($this->moderator);
        $this->post('/fr/forum/sujet/' . $topicId . '/moderer', ['action' => 'lock']);
        self::assertSame('locked', Database::value('SELECT status FROM forum_topics WHERE id = ?', [$topicId]));
        $this->switchTo($this->member);
        $this->post('/fr/forum/sujet/' . $topicId . '/repondre', ['body' => 'Réponse sur un sujet verrouillé.', 'accept_rules' => '1']);
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM forum_posts'));
        self::assertStringContainsString(__('forum.flash.locked'), implode(' ', $this->flashMessages()));

        $this->switchTo($this->moderator);
        $this->post('/fr/forum/sujet/' . $topicId . '/moderer', ['action' => 'unlock']);
        $this->post('/fr/forum/sujet/' . $topicId . '/moderer', ['action' => 'pin']);
        $this->post('/fr/forum/sujet/' . $topicId . '/moderer', ['action' => 'move', 'category_id' => 7]);
        $topic = Database::fetch('SELECT status, is_pinned, category_id FROM forum_topics WHERE id = ?', [$topicId]);
        self::assertSame(['status' => 'open', 'is_pinned' => 1, 'category_id' => 7], array_map(static fn ($v) => is_numeric($v) ? (int) $v : $v, $topic));

        foreach (['/admin/forum', '/admin/forum?status=open', '/admin/forum/posts', '/admin/forum/categories', '/admin/reports', '/admin/ideas'] as $uri) {
            self::assertSame(200, $this->get($uri)->status(), $uri);
        }
        // Catégorie modifiée en trois langues
        $this->post('/admin/forum/categories/7', ['name' => ['fr' => 'Discussions libres', 'ar' => 'نقاشات عامة', 'en' => 'Open discussions'], 'description' => ['fr' => '', 'ar' => '', 'en' => ''], 'sort_order' => 7, 'is_active' => '1']);
        self::assertSame('Discussions libres', Database::value("SELECT name FROM forum_category_translations WHERE category_id = 7 AND locale = 'fr'"));

        // Pages publiques en trois langues
        $this->switchTo(null);
        self::assertStringContainsString('Discussions libres', $this->get('/fr/forum')->body());
        self::assertSame(200, $this->get('/ar/forum')->status());
        self::assertSame(200, $this->get('/en/forum')->status());
        self::assertSame(200, $this->get('/fr/forum/' . Database::value("SELECT slug FROM forum_category_translations WHERE category_id = 7 AND locale = 'fr'"))->status());
        self::assertSame(200, $this->get('/fr/forum/coran')->status());
        self::assertSame(404, $this->get('/fr/forum/categorie-inexistante')->status());
        self::assertSame(404, $this->get('/fr/forum/sujet/999999')->status());
    }

    // --- Boîte à idées -----------------------------------------------------------------------------------------

    public function testUneIdeeEstValideeParLAdministrationPuisLAuteurEstNotifieDeLaReponse(): void
    {
        $this->switchTo($this->member);
        $response = $this->post('/fr/idees/proposer', ['category' => 'platform', 'title' => 'Ajouter un mode sombre au site', 'description' => 'Un thème sombre faciliterait la lecture le soir et réduirait la fatigue visuelle.']);
        self::assertSame(302, $response->status());
        $ideaId = (int) Database::value('SELECT id FROM ideas ORDER BY id DESC LIMIT 1');
        self::assertSame('pending', Database::value('SELECT status FROM ideas WHERE id = ?', [$ideaId]));
        self::assertSame(200, $this->get('/fr/idees/' . $ideaId)->status());
        self::assertStringContainsString('mode sombre', $this->get('/fr/idees')->body());
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'idea.pending'", [$this->admin]));

        // Invisible pour les autres tant qu'elle est en attente
        $this->switchTo($this->other);
        self::assertSame(404, $this->get('/fr/idees/' . $ideaId)->status());
        self::assertStringNotContainsString('mode sombre', $this->get('/fr/idees')->body());

        // L'administration la publie, puis répond officiellement
        $this->switchTo($this->admin);
        self::assertStringContainsString('mode sombre', $this->get('/admin/ideas/' . $ideaId)->body());
        $this->post('/admin/ideas/' . $ideaId, ['status' => 'new', 'admin_response' => '']);
        self::assertSame('new', Database::value('SELECT status FROM ideas WHERE id = ?', [$ideaId]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'idea.status'", [$this->member]));
        $this->post('/admin/ideas/' . $ideaId, ['status' => 'accepted', 'admin_response' => 'Excellente idée : le mode sombre est prévu pour la prochaine version.']);
        $idea = Database::fetch('SELECT status, admin_response, responded_by FROM ideas WHERE id = ?', [$ideaId]);
        self::assertSame('accepted', $idea['status']);
        self::assertSame($this->admin, (int) $idea['responded_by']);
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'idea.response'", [$this->member]));

        // Page publique : idée, réponse officielle, aucun nom réel
        $this->switchTo(null);
        $page = $this->get('/fr/idees/' . $ideaId)->body();
        self::assertStringContainsString('mode sombre', $page);
        self::assertStringContainsString('prochaine version', $page);
        self::assertStringContainsString('Oum Fatima', $page);
        self::assertStringNotContainsString('Awa Diarra', $page);
        self::assertStringContainsString('mode sombre', $this->get('/fr/idees?status=accepted')->body());
        self::assertSame(200, $this->get('/ar/ideas')->status());
        self::assertSame(200, $this->get('/en/ideas')->status());

        // Mon espace : mes idées
        $this->switchTo($this->member);
        self::assertStringContainsString('mode sombre', $this->get('/fr/mon-espace/idees')->body());
    }

    public function testUnMembreNePeutVoterQuUneFoisParIdee(): void
    {
        $this->switchTo($this->member);
        $this->post('/fr/idees/proposer', ['category' => 'content', 'title' => 'Une série sur les 40 hadiths', 'description' => 'Publier chaque semaine un article détaillé sur l\'un des quarante hadiths de An-Nawawi.']);
        $ideaId = (int) Database::value('SELECT id FROM ideas ORDER BY id DESC LIMIT 1');

        // Une idée en attente ne peut pas être votée
        $this->post('/fr/idees/' . $ideaId . '/vote');
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM idea_votes'));
        self::assertStringContainsString(__('ideas.flash.not_votable'), implode(' ', $this->flashMessages()));

        $this->switchTo($this->admin);
        $this->post('/admin/ideas/' . $ideaId, ['status' => 'new', 'admin_response' => '']);

        // Vote unique, contrôlé côté serveur (clé primaire idea_id + user_id), annulable
        $this->switchTo($this->other);
        $json = $this->post('/fr/idees/' . $ideaId . '/vote', [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(['voted' => true, 'count' => 1], json_decode($json->body(), true));
        self::assertSame(1, (int) Database::value('SELECT votes_count FROM ideas WHERE id = ?', [$ideaId]));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM idea_votes WHERE idea_id = ? AND user_id = ?', [$ideaId, $this->other]));

        // Tentative de doublon directement en base : refusée par la clé primaire
        try {
            Database::execute('INSERT INTO idea_votes (idea_id, user_id) VALUES (?, ?)', [$ideaId, $this->other]);
            self::fail('Un second vote du même membre a été accepté.');
        } catch (\PDOException $e) {
            self::assertSame('23000', $e->getCode());
        }
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM idea_votes WHERE idea_id = ?', [$ideaId]));

        // Nouveau clic : le vote est retiré
        $json = $this->post('/fr/idees/' . $ideaId . '/vote', [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(['voted' => false, 'count' => 0], json_decode($json->body(), true));
        self::assertSame(0, (int) Database::value('SELECT votes_count FROM ideas WHERE id = ?', [$ideaId]));

        // Visiteur non connecté : redirigé vers la connexion
        $this->switchTo(null);
        self::assertSame(302, $this->post('/fr/idees/' . $ideaId . '/vote')->status());
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM idea_votes'));
    }

    public function testCommentairesDIdeesModeresEtNotificationDeLAuteur(): void
    {
        $this->switchTo($this->member);
        $this->post('/fr/idees/proposer', ['category' => 'platform', 'title' => 'Améliorer la recherche par mots-clés', 'description' => 'La recherche devrait proposer des suggestions pendant la frappe pour aller plus vite.']);
        $ideaId = (int) Database::value('SELECT id FROM ideas ORDER BY id DESC LIMIT 1');
        $this->switchTo($this->admin);
        $this->post('/admin/ideas/' . $ideaId, ['status' => 'under_review', 'admin_response' => '']);

        // Commentaire d'un nouveau membre : en attente ; commentaire du professeur : visible et notifié à l'auteur
        $this->switchTo($this->other);
        $this->post('/fr/idees/' . $ideaId . '/commentaire', ['body' => 'Bonne idée, je plussoie.']);
        self::assertSame('pending', Database::value('SELECT status FROM idea_comments ORDER BY id DESC LIMIT 1'));
        $this->switchTo($this->scholar);
        $this->post('/fr/idees/' . $ideaId . '/commentaire', ['body' => 'Cela aiderait aussi pour les fatwas en arabe.']);
        self::assertSame('visible', Database::value('SELECT status FROM idea_comments ORDER BY id DESC LIMIT 1'));
        self::assertSame(1, (int) Database::value('SELECT comments_count FROM ideas WHERE id = ?', [$ideaId]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'idea.comment'", [$this->member]));

        $this->switchTo(null);
        $page = $this->get('/fr/idees/' . $ideaId)->body();
        self::assertStringContainsString('fatwas en arabe', $page);
        self::assertStringNotContainsString('je plussoie', $page);

        // Le modérateur approuve le commentaire en attente
        $this->switchTo($this->moderator);
        $pendingId = (int) Database::value("SELECT id FROM idea_comments WHERE status = 'pending'");
        $this->post('/admin/ideas/comments/' . $pendingId, ['status' => 'visible']);
        self::assertSame(2, (int) Database::value('SELECT comments_count FROM ideas WHERE id = ?', [$ideaId]));
        $this->switchTo(null);
        self::assertStringContainsString('je plussoie', $this->get('/fr/idees/' . $ideaId)->body());
    }
}
