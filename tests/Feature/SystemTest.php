<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use Tests\Support\DatabaseTestCase;

/**
 * Administration → Système : état de l'installation et application des migrations SQL en attente sans terminal
 * (hébergement mutualisé déployé depuis GitHub). Réservé au super-administrateur.
 */
final class SystemTest extends DatabaseTestCase
{
    private int $superadmin;
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = $this->createUser(['email' => 'super@example.ml', 'full_name' => 'Super Admin', 'role_id' => 5]);
        $this->admin = $this->createUser(['email' => 'admin@example.ml', 'full_name' => 'Admin', 'role_id' => 4]);
    }

    protected function tearDown(): void
    {
        // Remet la base de test dans l'état du schéma complet si un test a échoué à mi-chemin.
        if (!$this->hasRequestedColumn()) {
            Database::pdo()->exec('ALTER TABLE questions ADD COLUMN requested_scholar_id INT UNSIGNED NULL AFTER assigned_to, ADD KEY idx_questions_requested (requested_scholar_id), ADD CONSTRAINT fk_questions_requested FOREIGN KEY (requested_scholar_id) REFERENCES users (id) ON DELETE SET NULL');
        }
        Database::execute("INSERT IGNORE INTO schema_migrations (migration, batch) VALUES ('2026_10_18_requested_scholar.sql', 1)");
        parent::tearDown();
    }

    private function hasRequestedColumn(): bool
    {
        return (bool) Database::pdo()->query("SHOW COLUMNS FROM questions LIKE 'requested_scholar_id'")->fetch();
    }

    /** Simule une base de production déployée sans la dernière migration. */
    private function removeLastMigration(): void
    {
        Database::execute("DELETE FROM schema_migrations WHERE migration = '2026_10_18_requested_scholar.sql'");
        Database::pdo()->exec('ALTER TABLE questions DROP FOREIGN KEY fk_questions_requested, DROP KEY idx_questions_requested, DROP COLUMN requested_scholar_id');
    }

    public function testLaPageEstReserveeAuSuperAdministrateur(): void
    {
        $this->login($this->admin);
        self::assertSame(403, $this->get('/admin/system')->status());
        self::assertSame(403, $this->post('/admin/system/migrate')->status());
    }

    public function testBaseAJourSansMigrationEnAttente(): void
    {
        $this->login($this->superadmin);
        $page = $this->get('/admin/system');
        self::assertSame(200, $page->status());
        self::assertStringContainsString(e(__('admin.system.up_to_date')), $page->body());
        self::assertStringContainsString('2026_10_18_requested_scholar.sql', $page->body());
        self::assertStringNotContainsString(e(__('admin.system.dashboard_hint')), $this->get('/admin')->body());

        $this->post('/admin/system/migrate');
        self::assertContains(__('admin.system.flash.nothing'), $this->flashMessages());
    }

    public function testMigrationEnAttenteSignaleePuisAppliqueeDUnClic(): void
    {
        $this->removeLastMigration();
        self::assertFalse($this->hasRequestedColumn());

        $this->login($this->superadmin);
        self::assertStringContainsString(e(__('admin.system.dashboard_hint')), $this->get('/admin')->body());
        $page = $this->get('/admin/system')->body();
        self::assertStringContainsString(e(trans_choice('admin.system.pending_count', 1)), $page);
        self::assertStringContainsString(e(__('admin.system.apply')), $page);

        $this->post('/admin/system/migrate');
        self::assertTrue($this->hasRequestedColumn());
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM schema_migrations WHERE migration = '2026_10_18_requested_scholar.sql'"));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM activity_logs WHERE action = 'admin.system.migrated'"));
        self::assertContains(__('admin.system.flash.applied', ['count' => 1]), $this->flashMessages());
        self::assertStringContainsString(e(__('admin.system.up_to_date')), $this->get('/admin/system')->body());
    }
}
