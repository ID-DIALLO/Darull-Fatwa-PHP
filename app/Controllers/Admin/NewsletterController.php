<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLog;
use App\Services\Admin\ExportService;

/** Abonnés à la newsletter (§4.11) : liste, export CSV, suppression. */
final class NewsletterController extends AdminController
{
    protected ?string $permission = 'newsletter.manage';

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        [$whereSql, $bindings] = $this->where($status);
        $page = $this->page($request);
        $total = (int) Database::value('SELECT COUNT(*) FROM newsletter_subscribers' . $whereSql, $bindings);

        return $this->view('admin/newsletter', [
            'status' => $status,
            'result' => ['data' => Database::fetchAll('SELECT * FROM newsletter_subscribers' . $whereSql . ' ORDER BY created_at DESC LIMIT 50 OFFSET ' . (($page - 1) * 50), $bindings), 'total' => $total, 'page' => $page, 'per_page' => 50, 'last_page' => max(1, (int) ceil($total / 50))],
            'counts' => array_column(Database::fetchAll('SELECT status, COUNT(*) AS n FROM newsletter_subscribers GROUP BY status'), 'n', 'status'),
        ]);
    }

    public function export(Request $request): Response
    {
        [$whereSql, $bindings] = $this->where((string) $request->query('status', 'active'));
        $rows = Database::fetchAll('SELECT email, locale, status, confirmed_at, created_at FROM newsletter_subscribers' . $whereSql . ' ORDER BY created_at', $bindings);
        ActivityLog::record('admin.newsletter.exported', 'newsletter', null, ['count' => count($rows)]);
        $headers = [__('admin.newsletter.email'), __('admin.newsletter.locale'), __('admin.newsletter.status'), __('admin.newsletter.confirmed_at'), __('admin.newsletter.created_at')];

        return ExportService::download($request->query('format') === 'xlsx' ? 'xlsx' : 'csv', 'newsletter', $headers, array_map(static fn (array $r): array => array_values($r), $rows));
    }

    public function destroy(Request $request, string $id): Response
    {
        Database::execute('DELETE FROM newsletter_subscribers WHERE id = ?', [$this->id($id)]);
        ActivityLog::record('admin.newsletter.deleted', 'newsletter', $this->id($id));

        return $this->done('admin.newsletter', [], __('admin.common.deleted'));
    }

    /** @return array{0: string, 1: list<string>} */
    private function where(string $status): array
    {
        return in_array($status, ['pending', 'active', 'unsubscribed'], true) ? [' WHERE status = ?', [$status]] : ['', []];
    }
}
