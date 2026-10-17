<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLog;
use App\Services\Installer;

/**
 * Première mise en service sans accès SSH (§16.2) : création du premier super-administrateur depuis le navigateur.
 * La page n'existe que si aucun administrateur n'est encore enregistré ET si SETUP_TOKEN est défini dans .env ;
 * le jeton doit être fourni dans le formulaire. Dès qu'un administrateur existe, la page répond 404.
 */
final class SetupController extends Controller
{
    public function show(Request $request): Response
    {
        $this->guard();

        return $this->view('auth/setup', ['token' => (string) $request->query('token', '')]);
    }

    public function store(Request $request): Response
    {
        $this->guard();
        $expected = (string) env('SETUP_TOKEN', '');
        if (!hash_equals($expected, (string) $request->input('token', ''))) {
            Flash::error(__('auth.setup.bad_token'));

            return $this->redirect(route('setup'));
        }
        $data = $this->validate($request, [
            'full_name'             => 'required|string|min:3|max:120',
            'email'                 => 'required|email|max:190|unique:users,email',
            'password'              => ['required', 'string', 'min:10', 'max:200', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', 'confirmed'],
            'password_confirmation' => 'required',
        ]);
        $installer = new Installer(base_path());
        $id = $installer->createAdmin(['full_name' => $data['full_name'], 'email' => $data['email'], 'phone' => '', 'password' => $data['password'], 'locale' => locale()], 'superadmin');
        ActivityLog::record('user.admin_created', 'user', $id, ['role' => 'superadmin', 'via' => 'setup'], $id);
        Auth::login($id);
        Flash::success(__('auth.setup.done'));

        return $this->redirect(route('admin.dashboard'));
    }

    /** 404 si la mise en service n'est plus possible (administrateur existant ou jeton absent du .env). */
    private function guard(): void
    {
        if ((string) env('SETUP_TOKEN', '') === '') {
            throw new HttpException(404);
        }
        $admins = (int) Database::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('admin','superadmin') AND u.deleted_at IS NULL");
        if ($admins > 0) {
            throw new HttpException(404);
        }
    }
}
