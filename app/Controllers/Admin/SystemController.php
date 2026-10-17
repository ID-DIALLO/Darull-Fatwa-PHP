<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLog;
use App\Services\Installer;

/**
 * Système (super-administrateur) : état de l'installation et mises à jour de la base de données.
 * Indispensable sur un hébergement sans terminal : après un déploiement depuis GitHub, les migrations SQL
 * en attente s'appliquent ici d'un clic (équivalent de « php bin/migrate.php »).
 */
final class SystemController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!Auth::hasRole('superadmin')) {
            throw new HttpException(403);
        }
    }

    public function index(Request $request): Response
    {
        $installer = new Installer(base_path());
        $pdo = Database::pdo();

        return $this->view('admin/system', [
            'applied' => $installer->appliedMigrations($pdo),
            'pending' => array_map('basename', $installer->pendingMigrations($pdo)),
            'info'    => [
                'php'      => PHP_VERSION,
                'database' => $installer->serverVersion($pdo),
                'tables'   => count($installer->existingTables($pdo)),
                'env'      => (string) config('app.env'),
                'debug'    => (bool) config('app.debug'),
                'url'      => (string) config('app.url'),
                'assets'   => is_file(public_path('assets/dist/manifest.json')),
                'cron'     => is_file(storage_path('cache/cron.lock')) ? (int) filemtime(storage_path('cache/cron.lock')) : null,
                'writable' => array_filter(['logs', 'cache', 'audio', 'backups', 'exports'], static fn (string $d): bool => !is_writable(storage_path($d))),
            ],
        ]);
    }

    /** Applique les migrations en attente, dans l'ordre, et journalise le résultat. */
    public function migrate(Request $request): Response
    {
        $installer = new Installer(base_path());
        try {
            $done = $installer->migrate(Database::pdo());
        } catch (\Throwable $e) {
            \App\Core\Logger::exception($e);
            ActivityLog::record('admin.system.migration_failed', 'system', null, ['error' => mb_substr($e->getMessage(), 0, 500)], (int) Auth::id());

            return $this->done('admin.system', [], __('admin.system.flash.failed', ['error' => mb_substr($e->getMessage(), 0, 200)]), 'error');
        }
        \App\Core\Cache::flush('');
        ActivityLog::record('admin.system.migrated', 'system', null, ['migrations' => $done], (int) Auth::id());

        return $this->done('admin.system', [], $done === [] ? __('admin.system.flash.nothing') : __('admin.system.flash.applied', ['count' => count($done)]));
    }

    /** Nombre de migrations en attente (bandeau du tableau de bord) ; 0 en cas d'erreur pour ne jamais bloquer l'administration. */
    public static function pendingCount(): int
    {
        try {
            return count((new Installer(base_path()))->pendingMigrations(Database::pdo()));
        } catch (\Throwable) {
            return 0;
        }
    }
}
