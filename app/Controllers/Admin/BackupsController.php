<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Admin\BackupService;

/** Sauvegardes de la base (§4.6.3) : réservées au super-administrateur. */
final class BackupsController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!Auth::hasRole('superadmin')) {
            throw new HttpException(403);
        }
    }

    public function index(Request $request): Response
    {
        return $this->view('admin/backups', ['backups' => BackupService::all(), 'retention' => BackupService::RETENTION_DAYS]);
    }

    public function store(Request $request): Response
    {
        try {
            $name = BackupService::create((int) Auth::id());
        } catch (\Throwable $e) {
            \App\Core\Logger::exception($e);

            return $this->done('admin.backups', [], __('admin.backups.flash.failed'), 'error');
        }

        return $this->done('admin.backups', [], __('admin.backups.flash.created', ['name' => $name]));
    }

    public function download(Request $request, string $name): Response
    {
        $path = BackupService::path($name);
        if ($path === null) {
            throw new HttpException(404);
        }
        \App\Services\ActivityLog::record('admin.backup.downloaded', 'backup', null, ['file' => $name]);

        return Response::file($path, 'application/gzip', $request, 'attachment', $name);
    }

    public function destroy(Request $request, string $name): Response
    {
        if (!BackupService::delete($name, (int) Auth::id())) {
            throw new HttpException(404);
        }

        return $this->done('admin.backups', [], __('admin.common.deleted'));
    }
}
