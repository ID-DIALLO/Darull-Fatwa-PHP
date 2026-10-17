<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\Admin\UserAdminService;

/**
 * Utilisateurs (§4.6.3) : liste, fiche membre, création, modification, rôle, statut, lien de réinitialisation, suppression.
 */
final class UsersController extends AdminController
{
    protected ?string $permission = 'users.manage';

    public function index(Request $request): Response
    {
        $filters = ['role' => (string) $request->query('role', ''), 'status' => (string) $request->query('status', ''), 'country' => (string) $request->query('country', ''), 'q' => (string) $request->query('q', ''), 'trashed' => (string) $request->query('trashed', '')];

        return $this->view('admin/users/index', ['filters' => $filters, 'result' => UserAdminService::list($filters, $this->page($request))]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/users/form', ['user' => null]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'full_name' => 'required|min:2|max:120', 'display_name' => 'max:60', 'email' => 'required_without:phone|email|unique:users,email', 'phone' => 'required_without:email|phone|unique:users,phone',
            'whatsapp' => 'phone', 'role' => 'required|in:' . implode(',', $this->assignableRoles()), 'locale' => 'required|in:fr,ar,en', 'country' => 'regex:/^[A-Z]{2}$/',
        ]);
        $id = UserAdminService::create(array_map(static fn ($v) => is_string($v) && trim($v) === '' ? null : $v, $data), (int) Auth::id());

        return $this->done('admin.users.show', ['id' => $id], __('admin.users.flash.created'));
    }

    public function show(Request $request, string $id): Response
    {
        $user = UserAdminService::sheet($this->id($id));
        if ($user === null) {
            throw new HttpException(404);
        }

        return $this->view('admin/users/show', ['user' => $user, 'roles' => $this->assignableRoles()]);
    }

    public function edit(Request $request, string $id): Response
    {
        $user = User::findWithRole($this->id($id));
        if ($user === null) {
            throw new HttpException(404);
        }

        return $this->view('admin/users/form', ['user' => $user]);
    }

    public function update(Request $request, string $id): Response
    {
        $userId = $this->id($id);
        $data = $this->validate($request, [
            'full_name' => 'required|min:2|max:120', 'display_name' => 'max:60', 'email' => 'required_without:phone|email|unique:users,email,' . $userId, 'phone' => 'required_without:email|phone|unique:users,phone,' . $userId,
            'whatsapp' => 'phone', 'locale' => 'required|in:fr,ar,en', 'country' => 'regex:/^[A-Z]{2}$/', 'city' => 'max:80',
        ]);
        UserAdminService::update($userId, $data, (int) Auth::id());

        return $this->done('admin.users.show', ['id' => $userId]);
    }

    public function role(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['role' => 'required|in:' . implode(',', User::ROLES)]);
        try {
            UserAdminService::setRole($this->id($id), (string) $data['role'], (int) Auth::id());
        } catch (\DomainException) {
            return $this->done('admin.users.show', ['id' => $id], __('admin.users.flash.superadmin_required'), 'error');
        }

        return $this->done('admin.users.show', ['id' => $id], __('admin.users.flash.role_changed'));
    }

    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['status' => 'required|in:active,suspended,banned', 'reason' => 'max:255']);
        try {
            UserAdminService::setStatus($this->id($id), (string) $data['status'], (int) Auth::id(), $data['reason'] ?: null);
        } catch (\DomainException $e) {
            return $this->done('admin.users.show', ['id' => $id], __($e->getMessage() === 'self' ? 'admin.users.flash.not_self' : 'admin.users.flash.superadmin_required'), 'error');
        }

        return $this->done('admin.users.show', ['id' => $id], __('admin.users.flash.status_changed'));
    }

    public function resetLink(Request $request, string $id): Response
    {
        $sent = UserAdminService::sendResetLink($this->id($id), (int) Auth::id());

        return $this->done('admin.users.show', ['id' => $id], __($sent ? 'admin.users.flash.reset_sent' : 'admin.users.flash.reset_failed'), $sent ? 'success' : 'error');
    }

    public function destroy(Request $request, string $id): Response
    {
        try {
            UserAdminService::delete($this->id($id), (int) Auth::id());
        } catch (\DomainException $e) {
            return $this->done('admin.users.show', ['id' => $id], __($e->getMessage() === 'self' ? 'admin.users.flash.not_self' : 'admin.users.flash.superadmin_required'), 'error');
        }

        return $this->done('admin.users', [], __('admin.users.flash.deleted'));
    }

    /** Rôles qu'un administrateur peut attribuer (admin/superadmin réservés au super-admin). @return list<string> */
    private function assignableRoles(): array
    {
        return Auth::hasRole('superadmin') ? array_values(User::ROLES) : ['member', 'scholar', 'moderator'];
    }
}
