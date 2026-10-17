<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use App\Services\ActivityLog;

/** Sections de la page d'accueil (§6) : activation, ordre, chiffres clés manuels. */
final class HomeSectionsController extends AdminController
{
    protected ?string $permission = 'home.manage';

    public function index(Request $request): Response
    {
        return $this->view('admin/home_sections', [
            'sections' => Database::fetchAll('SELECT * FROM home_sections ORDER BY sort_order, code'),
            'stats'    => ['stats_answered' => Setting::get('stats_answered'), 'stats_members' => Setting::get('stats_members'), 'stats_articles' => Setting::get('stats_articles'), 'stats_scholars' => Setting::get('stats_scholars')],
        ]);
    }

    public function update(Request $request): Response
    {
        $order = array_values(array_filter(array_map('strval', (array) $request->input('order', [])), static fn (string $c): bool => preg_match('/^[a-z_]{2,40}$/', $c) === 1));
        $active = array_map('strval', (array) $request->input('active', []));
        foreach ($order as $position => $code) {
            Database::execute('UPDATE home_sections SET sort_order = ?, is_active = ? WHERE code = ?', [$position + 1, in_array($code, $active, true) ? 1 : 0, $code]);
        }
        foreach (['stats_answered', 'stats_members', 'stats_articles', 'stats_scholars'] as $key) {
            $value = trim((string) $request->input($key, ''));
            Setting::set($key, $value === '' ? '' : (string) max(0, (int) $value), 'home');
        }
        Cache::flush('home.');
        Cache::flush('stats.');
        ActivityLog::record('admin.home.updated', 'home_sections', null, ['order' => $order, 'active' => $active]);

        return $this->done('admin.home_sections');
    }
}
