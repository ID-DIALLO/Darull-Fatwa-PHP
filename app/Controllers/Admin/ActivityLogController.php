<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/** Journal d'activité (§4.6.3) : toutes les actions sensibles, filtrables. */
final class ActivityLogController extends AdminController
{
    protected ?string $permission = 'activity_log.view';

    public function index(Request $request): Response
    {
        $filters = ['action' => trim((string) $request->query('action', '')), 'user' => trim((string) $request->query('user', '')), 'subject' => trim((string) $request->query('subject', '')), 'from' => (string) $request->query('from', ''), 'to' => (string) $request->query('to', '')];
        $where = ['1 = 1'];
        $bindings = [];
        if ($filters['action'] !== '') {
            $where[] = 'a.action LIKE ?';
            $bindings[] = str_replace(['%', '_'], ['\%', '\_'], $filters['action']) . '%';
        }
        if ($filters['subject'] !== '' && preg_match('/^[a-z_]{2,40}$/', $filters['subject'])) {
            $where[] = 'a.subject_type = ?';
            $bindings[] = $filters['subject'];
        }
        if ($filters['user'] !== '') {
            if (ctype_digit($filters['user'])) {
                $where[] = 'a.user_id = ?';
                $bindings[] = (int) $filters['user'];
            } else {
                $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['user']) . '%';
                array_push($bindings, $like, $like);
            }
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key])) {
                $where[] = 'a.created_at ' . $op . ' ?';
                $bindings[] = $filters[$key] . ($key === 'to' ? ' 23:59:59' : ' 00:00:00');
            }
        }
        $sql = ' FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id WHERE ' . implode(' AND ', $where);
        $page = $this->page($request);
        $total = (int) Database::value('SELECT COUNT(*)' . $sql, $bindings);
        $rows = Database::fetchAll('SELECT a.*, u.full_name AS user_name' . $sql . ' ORDER BY a.created_at DESC, a.id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50), $bindings);

        return $this->view('admin/activity_log', [
            'filters'  => $filters,
            'result'   => ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => 50, 'last_page' => max(1, (int) ceil($total / 50))],
            'actions'  => array_map(static fn (array $r): string => (string) $r['action'], Database::fetchAll('SELECT DISTINCT action FROM activity_logs ORDER BY action')),
            'subjects' => array_map(static fn (array $r): string => (string) $r['subject_type'], Database::fetchAll('SELECT DISTINCT subject_type FROM activity_logs WHERE subject_type IS NOT NULL ORDER BY subject_type')),
        ]);
    }
}
