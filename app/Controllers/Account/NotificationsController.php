<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;
use App\Services\NotificationService;

/**
 * Notifications du membre (§4.4, §4.12) : liste, tout marquer comme lu, API compteur/liste pour la cloche.
 */
final class NotificationsController extends Controller
{
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $rows = Notification::latest((int) Auth::id(), 20, ($page - 1) * 20);
        $items = array_map(static fn (array $row): array => $row + ['rendered' => NotificationService::render($row)], $rows);

        return $this->view('account/notifications', ['items' => $items, 'page' => $page, 'unread' => Notification::unreadCount((int) Auth::id())]);
    }

    public function readAll(Request $request): Response
    {
        Notification::markAllRead((int) Auth::id());
        if ($request->wantsJson()) {
            return $this->json(['count' => 0]);
        }

        return $this->redirectToRoute('account.notifications');
    }

    public function open(Request $request, string $id): Response
    {
        $userId = (int) Auth::id();
        $row = \App\Core\Database::fetch('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [(int) $id, $userId]);
        if ($row === null) {
            return $this->redirectToRoute('account.notifications');
        }
        Notification::markRead($userId, (int) $id);
        $url = (string) ($row['url'] ?? '');

        return $this->redirect($url !== '' && str_starts_with($url, '/') ? $url : route('account.notifications'));
    }

    public function count(Request $request): Response
    {
        return $this->json(['count' => Notification::unreadCount((int) Auth::id())]);
    }

    public function list(Request $request): Response
    {
        $rows = Notification::latest((int) Auth::id(), 10);

        return $this->json(['items' => array_map(static fn (array $row): array => [
            'id'      => (int) $row['id'],
            'read'    => $row['read_at'] !== null,
            'url'     => route('account.notifications.open', ['id' => $row['id']]),
            'created' => $row['created_at'],
        ] + NotificationService::render($row), $rows)]);
    }
}
