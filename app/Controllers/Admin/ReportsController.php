<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLog;
use App\Services\ForumService;

/** Signalements (forum, commentaires, idées). */
final class ReportsController extends AdminController
{
    protected ?string $permission = 'reports.handle';

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'open');
        $where = in_array($status, ['open', 'resolved', 'dismissed'], true) ? ' WHERE r.status = ?' : '';
        $bindings = $where !== '' ? [$status] : [];
        $page = $this->page($request);
        $total = (int) Database::value('SELECT COUNT(*) FROM reports r' . $where, $bindings);
        $rows = Database::fetchAll(
            'SELECT r.*, u.full_name AS reporter_name, h.full_name AS handler_name,
                    CASE r.reportable_type
                        WHEN \'forum_topic\' THEN (SELECT ft.title FROM forum_topics ft WHERE ft.id = r.reportable_id)
                        WHEN \'forum_post\' THEN (SELECT LEFT(fp.body, 200) FROM forum_posts fp WHERE fp.id = r.reportable_id)
                        WHEN \'comment\' THEN (SELECT LEFT(c.body, 200) FROM comments c WHERE c.id = r.reportable_id)
                        WHEN \'idea\' THEN (SELECT i.title FROM ideas i WHERE i.id = r.reportable_id)
                        WHEN \'idea_comment\' THEN (SELECT LEFT(ic.body, 200) FROM idea_comments ic WHERE ic.id = r.reportable_id)
                    END AS target_excerpt,
                    CASE r.reportable_type
                        WHEN \'forum_post\' THEN (SELECT fp.topic_id FROM forum_posts fp WHERE fp.id = r.reportable_id)
                        WHEN \'idea_comment\' THEN (SELECT ic.idea_id FROM idea_comments ic WHERE ic.id = r.reportable_id)
                        ELSE r.reportable_id END AS parent_id,
                    CASE r.reportable_type WHEN \'forum_topic\' THEN (SELECT ft.slug FROM forum_topics ft WHERE ft.id = r.reportable_id) WHEN \'forum_post\' THEN (SELECT ft.slug FROM forum_posts fp JOIN forum_topics ft ON ft.id = fp.topic_id WHERE fp.id = r.reportable_id) END AS topic_slug
             FROM reports r JOIN users u ON u.id = r.reporter_id LEFT JOIN users h ON h.id = r.handled_by' . $where . ' ORDER BY FIELD(r.status, \'open\') DESC, r.created_at DESC LIMIT 25 OFFSET ' . (($page - 1) * 25),
            $bindings,
        );

        return $this->view('admin/reports', ['status' => $status, 'result' => ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => 25, 'last_page' => max(1, (int) ceil($total / 25))], 'counts' => array_column(Database::fetchAll('SELECT status, COUNT(*) AS n FROM reports GROUP BY status'), 'n', 'status')]);
    }

    /** Traitement : résolu (avec masquage facultatif de la cible) ou classé sans suite. */
    public function handle(Request $request, string $id): Response
    {
        $reportId = $this->id($id);
        $report = Database::fetch('SELECT * FROM reports WHERE id = ?', [$reportId]);
        if ($report === null) {
            throw new \App\Core\HttpException(404);
        }
        $data = $this->validate($request, ['action' => 'required|in:resolve,hide,dismiss']);
        $me = (int) Auth::id();
        if ($data['action'] === 'hide') {
            match ((string) $report['reportable_type']) {
                'forum_topic'  => ForumService::hide('topic', (int) $report['reportable_id'], $me),
                'forum_post'   => ForumService::hide('post', (int) $report['reportable_id'], $me),
                'comment'      => \App\Services\CommentService::moderate((int) $report['reportable_id'], 'rejected', $me),
                'idea'         => \App\Services\IdeaService::setStatus((int) $report['reportable_id'], 'declined', null, $me),
                'idea_comment' => \App\Services\IdeaService::moderateComment((int) $report['reportable_id'], 'hidden', $me),
                default        => null,
            };
        }
        Database::execute('UPDATE reports SET status = ?, handled_by = ?, handled_at = UTC_TIMESTAMP() WHERE id = ?', [$data['action'] === 'dismiss' ? 'dismissed' : 'resolved', $me, $reportId]);
        ActivityLog::record('admin.report.' . ($data['action'] === 'dismiss' ? 'dismissed' : 'resolved'), 'report', $reportId, ['target' => $report['reportable_type'] . '#' . $report['reportable_id'], 'hidden' => $data['action'] === 'hide'], $me);

        return $this->back($request, route('admin.reports'));
    }
}
