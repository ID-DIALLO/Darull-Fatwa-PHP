<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\SecurityHeadersMiddleware;
use Dotenv\Dotenv;

/**
 * Amorçage de l'application : .env, configuration, erreurs, session, langue, routeur.
 */
final class App
{
    private static ?self $instance = null;

    /** @var array<string, array<string, mixed>> */
    private array $config = [];

    private Router $router;

    private function __construct(private readonly string $basePath)
    {
    }

    /** Crée (une seule fois) et configure l'application. */
    public static function create(string $basePath): self
    {
        $app = new self(rtrim($basePath, '/'));
        self::$instance = $app;
        $app->boot();

        return $app;
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new \LogicException("L'application n'est pas initialisée.");
    }

    public static function hasInstance(): bool
    {
        return self::$instance !== null;
    }

    private function boot(): void
    {
        if (is_file($this->basePath . '/.env')) {
            Dotenv::createImmutable($this->basePath)->safeLoad();
        }

        foreach (glob($this->basePath . '/app/Config/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if ($name !== 'routes') {
                $this->config[$name] = require $file;
            }
        }

        date_default_timezone_set((string) $this->config('app.timezone', 'Africa/Bamako'));
        mb_internal_encoding('UTF-8');

        $debug = (bool) $this->config('app.debug', false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug && PHP_SAPI === 'cli' ? '1' : '0');
        ini_set('log_errors', '1');
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            // Les dépréciations (souvent dans les dépendances) sont journalisées sans interrompre la requête.
            if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                Logger::debug("Dépréciation : {$message} ({$file}:{$line})");

                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        Logger::setDirectory($this->basePath . '/storage/logs');

        /** @var list<string> $locales */
        $locales = $this->config('app.locales', ['fr', 'ar', 'en']);
        Lang::init(
            $this->basePath . '/lang',
            $locales,
            (string) $this->config('app.default_locale', 'fr'),
            (array) $this->config('app.rtl_locales', ['ar']),
            $debug ? static fn (string $key, string $locale) => Logger::debug("Clé de traduction manquante : {$key} ({$locale})") : null,
        );

        View::setBasePath($this->basePath . '/app/Views');
        Database::configure((array) $this->config['database']);
        Auth::setPermissions((array) ($this->config['permissions'] ?? []));

        $this->router = new Router($locales, (array) $this->config('app.middleware_aliases', []));
        $routes = require $this->basePath . '/app/Config/routes.php';
        $routes($this->router);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $value = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    /** Point d'entrée HTTP (public/index.php). */
    public function run(): void
    {
        $request = Request::capture();
        $response = $this->handle($request);
        $response->send($request->method() === 'HEAD');
    }

    public function handle(Request $request): Response
    {
        try {
            Session::start(
                (string) $this->config('app.session.name', 'df_session'),
                $request->isSecure(),
                (int) $this->config('app.session.idle_minutes', 120),
            );
            View::share('request', $request);

            return $this->router->dispatch($request, (array) $this->config('app.global_middleware', []));
        } catch (ValidationException $e) {
            return $this->validationResponse($request, $e);
        } catch (HttpException $e) {
            return $this->renderError($request, $e->getStatus(), $e);
        } catch (\Throwable $e) {
            Logger::exception($e);

            return $this->renderError($request, 500, $e);
        }
    }

    private function validationResponse(Request $request, ValidationException $e): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['message' => Lang::get('validation.failed'), 'errors' => $e->errors()], 422);
        }
        Session::flash('_errors', $e->errors());
        $old = $request->all();
        unset($old['_token'], $old['password'], $old['password_confirmation']);
        Session::flash('_old_input', $old);

        $referer = (string) $request->header('Referer');

        return Response::redirect(parse_url($referer, PHP_URL_HOST) === $request->host() ? $referer : $request->uri());
    }

    /** Page d'erreur traduite (403, 404, 405, 419, 429, 500, 503) ou JSON pour l'API. */
    public function renderError(Request $request, int $status, ?\Throwable $exception = null): Response
    {
        $locale = $request->attribute('locale');
        if (!Lang::isSupported(is_string($locale) ? $locale : null)) {
            $segment = explode('/', trim($request->path(), '/'))[0];
            $locale = Lang::isSupported($segment) ? $segment : Lang::detect($request);
        }
        Lang::setLocale((string) $locale);

        $known = [403, 404, 405, 419, 429, 500, 503];
        $code = in_array($status, $known, true) ? $status : ($status >= 500 ? 500 : 404);
        $debug = (bool) $this->config('app.debug', false);

        if ($request->wantsJson()) {
            $response = Response::json(['message' => Lang::get('common.errors.' . $code . '.title')], $status);
        } else {
            try {
                $html = View::make('errors/error', [
                    'status'    => $code,
                    'exception' => $debug && $code === 500 ? $exception : null,
                ]);
            } catch (\Throwable $viewError) {
                Logger::exception($viewError);
                $html = '<!doctype html><meta charset="utf-8"><title>Erreur ' . $code . '</title><h1>Erreur ' . $code . '</h1>';
            }
            $response = Response::html($html, $status);
        }

        if ($exception instanceof HttpException) {
            foreach ($exception->getHeaders() as $name => $value) {
                $response->header($name, $value);
            }
        }

        return SecurityHeadersMiddleware::apply($response, $request);
    }
}
