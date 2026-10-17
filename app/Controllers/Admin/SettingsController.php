<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use App\Services\ActivityLog;

/**
 * Paramètres du site (§4.6.3) par groupe ; les groupes sensibles (WhatsApp, SMTP, sécurité) sont réservés
 * au super-administrateur et leurs secrets sont chiffrés (jamais réaffichés). Vidage du cache.
 */
final class SettingsController extends AdminController
{
    protected ?string $permission = 'settings.manage';

    public function index(Request $request): Response
    {
        $groups = $this->groups();
        $current = (string) $request->query('group', 'general');
        if (!isset($groups[$current])) {
            $current = (string) array_key_first($groups);
        }
        $values = [];
        $secrets = [];
        foreach ($groups[$current]['fields'] as $key => $field) {
            if ($field['type'] === 'secret') {
                $secrets[$key] = Setting::hasSecret($key);
            } else {
                $values[$key] = Setting::get($key) ?? '';
            }
        }

        return $this->view('admin/settings', ['groups' => $groups, 'current' => $current, 'values' => $values, 'secrets' => $secrets, 'maintenanceFlag' => is_file(base_path('storage/maintenance.flag'))]);
    }

    public function update(Request $request, string $group): Response
    {
        $groups = $this->groups();
        if (!isset($groups[$group])) {
            throw new HttpException(404);
        }
        $rules = [];
        foreach ($groups[$group]['fields'] as $key => $field) {
            if (!empty($field['rules']) && !in_array($field['type'], ['bool', 'secret'], true)) {
                $rules[$key] = $field['rules'];
            }
        }
        $this->validate($request, $rules);
        $changed = [];
        foreach ($groups[$group]['fields'] as $key => $field) {
            $raw = $request->input($key);
            switch ($field['type']) {
                case 'secret':
                    if (!empty($request->input('clear_' . $key))) {
                        Setting::setSecret($key, null, $group);
                        $changed[] = $key;
                    } elseif (is_string($raw) && trim($raw) !== '') {
                        Setting::setSecret($key, trim($raw), $group);
                        $changed[] = $key;
                    }
                    break;
                case 'bool':
                    $value = $raw === '1' ? '1' : '0';
                    if (Setting::get($key) !== $value) {
                        Setting::set($key, $value, $group);
                        $changed[] = $key;
                    }
                    break;
                default:
                    $value = trim((string) (is_scalar($raw) ? $raw : ''));
                    if (str_starts_with($field['type'], 'select:') && !in_array($value, explode(',', substr($field['type'], 7)), true)) {
                        continue 2;
                    }
                    if ((Setting::get($key) ?? '') !== $value) {
                        Setting::set($key, $value, $group);
                        $changed[] = $key;
                    }
            }
        }
        if ($changed !== []) {
            ActivityLog::record('admin.settings.updated', 'settings', null, ['group' => $group, 'keys' => $changed]);
            Cache::flush('');
        }

        return $this->done('admin.settings', ['group' => $group]);
    }

    /** Vide le cache applicatif (rubriques, pages, bannières, statistiques…). */
    public function clearCache(Request $request): Response
    {
        Cache::flush('');
        Setting::flush();
        ActivityLog::record('admin.cache.cleared', 'settings', null);

        return $this->done('admin.settings', [], __('admin.settings.flash.cache_cleared'));
    }

    /** Groupes accessibles au rôle courant. @return array<string, array<string, mixed>> */
    private function groups(): array
    {
        $all = (array) config('settings', []);
        if (Auth::hasRole('superadmin')) {
            return $all;
        }

        return array_filter($all, static fn (array $g): bool => empty($g['superadmin']));
    }
}
