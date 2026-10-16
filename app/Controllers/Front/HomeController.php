<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;

final class HomeController extends Controller
{
    /** « / » : redirection vers la langue du visiteur (§7.1). */
    public function root(Request $request): Response
    {
        $locale = Lang::detect($request, Auth::user()['locale'] ?? null);

        return $this->redirect(route('home', [], $locale))
            ->header('Vary', 'Accept-Language, Cookie')
            ->header('Cache-Control', 'no-store');
    }

    /** Accueil. Phase 0 : page de test (logo, dates grégorienne et hégirienne, RTL en arabe). */
    public function index(Request $request): Response
    {
        return $this->view('front/home', [
            'checks' => config('app.debug') ? $this->environmentChecks() : [],
        ]);
    }

    /**
     * Vérifications techniques affichées uniquement en mode debug.
     *
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    private function environmentChecks(): array
    {
        $checks = [[
            'label'  => 'PHP ≥ 8.2',
            'ok'     => PHP_VERSION_ID >= 80200,
            'detail' => PHP_VERSION,
        ]];
        foreach (['pdo_mysql', 'mbstring', 'intl', 'fileinfo', 'openssl', 'json', 'curl', 'zip', 'gd'] as $ext) {
            $checks[] = ['label' => 'ext-' . $ext, 'ok' => extension_loaded($ext), 'detail' => extension_loaded($ext) ? 'OK' : '—'];
        }
        try {
            $version = (string) Database::value('SELECT VERSION()');
            $tables = (int) Database::value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
            $checks[] = ['label' => __('common.test.database'), 'ok' => $tables > 0, 'detail' => $version . ' · ' . $tables . ' tables'];
        } catch (\Throwable $e) {
            $checks[] = ['label' => __('common.test.database'), 'ok' => false, 'detail' => __('common.test.database_missing')];
        }
        $checks[] = ['label' => 'storage/ ' . __('common.test.writable'), 'ok' => is_writable(storage_path('logs')), 'detail' => storage_path()];

        return $checks;
    }
}
