<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLog;
use App\Services\MailService;

/** Messages de contact (§4.11) : boîte de réception, statut, réponse par e-mail. */
final class ContactMessagesController extends AdminController
{
    protected ?string $permission = 'contact.manage';

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $where = ['1 = 1'];
        $bindings = [];
        if (in_array($status, ['new', 'read', 'replied', 'archived'], true)) {
            $where[] = 'm.status = ?';
            $bindings[] = $status;
        }
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $where[] = '(m.name LIKE ? OR m.email LIKE ? OR m.message LIKE ?)';
            array_push($bindings, $like, $like, $like);
        }
        $sql = ' FROM contact_messages m WHERE ' . implode(' AND ', $where);
        $page = $this->page($request);
        $total = (int) Database::value('SELECT COUNT(*)' . $sql, $bindings);
        $rows = Database::fetchAll('SELECT m.*' . $sql . ' ORDER BY m.created_at DESC LIMIT 25 OFFSET ' . (($page - 1) * 25), $bindings);

        return $this->view('admin/contact/index', [
            'filters' => ['status' => $status, 'q' => $search],
            'result'  => ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => 25, 'last_page' => max(1, (int) ceil($total / 25))],
            'counts'  => array_column(Database::fetchAll('SELECT status, COUNT(*) AS n FROM contact_messages GROUP BY status'), 'n', 'status'),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $message = $this->find($id);
        if ($message['status'] === 'new') {
            Database::execute('UPDATE contact_messages SET status = ? WHERE id = ?', ['read', (int) $message['id']]);
            $message['status'] = 'read';
        }

        return $this->view('admin/contact/show', ['message' => $message]);
    }

    public function status(Request $request, string $id): Response
    {
        $message = $this->find($id);
        $data = $this->validate($request, ['status' => 'required|in:new,read,replied,archived']);
        Database::execute('UPDATE contact_messages SET status = ? WHERE id = ?', [$data['status'], (int) $message['id']]);
        ActivityLog::record('admin.contact.status', 'contact_message', (int) $message['id'], ['status' => $data['status']]);

        return $this->done('admin.contact.show', ['id' => $id]);
    }

    public function reply(Request $request, string $id): Response
    {
        $message = $this->find($id);
        $data = $this->validate($request, ['body' => 'required|min:5|max:10000']);
        $sent = MailService::send((string) $message['email'], 'contact_reply', [
            'name'     => (string) $message['name'],
            'original' => (string) $message['message'],
            'reply'    => (string) $data['body'],
            'subject_label' => __('front.contact.subjects.' . $message['subject'], [], (string) $message['locale']),
        ], (string) $message['locale'], (string) $message['name']);
        if (!$sent) {
            return $this->done('admin.contact.show', ['id' => $id], __('admin.contact.flash.send_failed'), 'error');
        }
        Database::execute('UPDATE contact_messages SET status = ?, replied_by = ?, replied_at = UTC_TIMESTAMP() WHERE id = ?', ['replied', (int) Auth::id(), (int) $message['id']]);
        ActivityLog::record('admin.contact.replied', 'contact_message', (int) $message['id']);

        return $this->done('admin.contact.show', ['id' => $id], __('admin.contact.flash.replied'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $message = $this->find($id);
        Database::execute('DELETE FROM contact_messages WHERE id = ?', [(int) $message['id']]);
        ActivityLog::record('admin.contact.deleted', 'contact_message', (int) $message['id']);

        return $this->done('admin.contact', [], __('admin.common.deleted'));
    }

    /** @return array<string, mixed> */
    private function find(string $id): array
    {
        $row = Database::fetch('SELECT m.*, u.full_name AS replied_by_name FROM contact_messages m LEFT JOIN users u ON u.id = m.replied_by WHERE m.id = ?', [$this->id($id)]);
        if ($row === null) {
            throw new HttpException(404);
        }

        return $row;
    }
}
