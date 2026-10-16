<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\Middleware;
use Closure;

/**
 * Routeur : routes GET/POST/PUT/PATCH/DELETE, paramètres, groupes (préfixe + middlewares),
 * routes nommées et routes traduites avec préfixe de langue (/fr/poser-une-question, /en/ask, /ar/ask).
 */
class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, array<string, Route>> nom => [locale|'*' => route] */
    private array $named = [];

    /** @var list<array{prefix: string, middleware: list<string>}> */
    private array $groupStack = [];

    private ?Route $current = null;

    /**
     * @param list<string> $locales Langues prises en charge (la première est la langue par défaut)
     * @param array<string, class-string<Middleware>> $aliases Alias de middlewares (auth, role, throttle…)
     */
    public function __construct(
        private readonly array $locales = ['fr', 'ar', 'en'],
        private readonly array $aliases = [],
    ) {
    }

    /** @return list<string> */
    public function locales(): array
    {
        return $this->locales;
    }

    public function get(string $path, Closure|array|string $action): Route
    {
        return $this->add(['GET', 'HEAD'], $path, $action);
    }

    public function post(string $path, Closure|array|string $action): Route
    {
        return $this->add(['POST'], $path, $action);
    }

    public function put(string $path, Closure|array|string $action): Route
    {
        return $this->add(['PUT'], $path, $action);
    }

    public function patch(string $path, Closure|array|string $action): Route
    {
        return $this->add(['PATCH'], $path, $action);
    }

    public function delete(string $path, Closure|array|string $action): Route
    {
        return $this->add(['DELETE'], $path, $action);
    }

    /** @param list<string> $methods */
    public function add(array $methods, string $path, Closure|array|string $action, ?string $locale = null): Route
    {
        $methods = array_map('strtoupper', $methods);
        if (in_array('GET', $methods, true) && !in_array('HEAD', $methods, true)) {
            $methods[] = 'HEAD';
        }

        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            $middleware = array_merge($middleware, $group['middleware']);
        }
        $full = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        $full = preg_replace('#/+#', '/', $full) ?? $full;

        $route = new Route($methods, $full, $action, $locale, $this);
        $route->middleware($middleware);
        $this->routes[] = $route;

        return $route;
    }

    /**
     * Déclare une route dans chaque langue. Les segments sont en français pour /fr/
     * et en anglais pour /en/ et /ar/ (§7.1, §11).
     *
     * @param string|list<string> $methods
     * @param string|array<string, string> $paths  ex. ['fr' => '/poser-une-question', 'en' => '/ask']
     * @param string|list<string> $middleware
     * @return list<Route>
     */
    public function localized(string|array $methods, string|array $paths, Closure|array|string $action, string $name, string|array $middleware = []): array
    {
        $created = [];
        foreach ($this->locales as $locale) {
            $path = is_string($paths) ? $paths : ($paths[$locale] ?? $paths['en'] ?? $paths['fr'] ?? '');
            // Le préfixe de langue remplace le préfixe de groupe : on l'ajoute hors pile de groupes.
            $stack = $this->groupStack;
            $this->groupStack = array_map(static fn (array $g): array => ['prefix' => '', 'middleware' => $g['middleware']], $stack);
            $route = $this->add((array) $methods, '/' . $locale . '/' . ltrim($path, '/'), $action, $locale);
            $this->groupStack = $stack;
            $route->middleware($middleware)->name($name);
            $created[] = $route;
        }

        return $created;
    }

    /** @param array{prefix?: string, middleware?: string|list<string>} $attributes */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix'     => isset($attributes['prefix']) ? '/' . trim($attributes['prefix'], '/') : '',
            'middleware' => (array) ($attributes['middleware'] ?? []),
        ];
        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
        }
    }

    /** @internal Appelé par Route::name() */
    public function registerName(Route $route): void
    {
        $name = $route->getName();
        if ($name !== null) {
            $this->named[$name][$route->locale ?? '*'] = $route;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->named[$name]);
    }

    /**
     * Chemin d'une route nommée dans la langue demandée (ou la langue courante).
     *
     * @param array<string, scalar> $params
     */
    public function url(string $name, array $params = [], ?string $locale = null): string
    {
        $variants = $this->named[$name] ?? throw new \InvalidArgumentException('Route inconnue : ' . $name);
        $locale ??= Lang::getLocale();
        $route = $variants[$locale] ?? $variants['*'] ?? reset($variants);

        return $route->buildPath($params);
    }

    /**
     * Trouve la route correspondant à la requête.
     *
     * @return array{0: Route, 1: array<string, string>}
     * @throws HttpException 404 si aucune route, 405 si la méthode n'est pas permise
     */
    public function match(Request $request): array
    {
        $path = $request->path();
        $method = $request->method();
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $route->matches($path);
            if ($params === null) {
                continue;
            }
            if (in_array($method, $route->methods, true)) {
                return [$route, $params];
            }
            $allowed = array_merge($allowed, $route->methods);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Method Not Allowed', ['Allow' => implode(', ', array_unique($allowed))]);
        }

        throw new HttpException(404);
    }

    /**
     * Exécute la requête : middlewares globaux, middlewares de la route, puis l'action.
     *
     * @param list<string> $globalMiddleware
     */
    public function dispatch(Request $request, array $globalMiddleware = []): Response
    {
        $notFound = null;
        try {
            [$route, $params] = $this->match($request);
            $this->current = $route;
            $request->setRouteParams($params);
            $request->setAttribute('route', $route);
            $request->setAttribute('locale', $route->locale);
            $stack = array_merge($globalMiddleware, $route->getMiddleware());
            $core = fn (Request $r): Response => $this->callAction($route->action, $r);
        } catch (HttpException $e) {
            // Les middlewares globaux (langue, en-têtes…) s'appliquent aussi aux erreurs 404/405.
            $notFound = $e;
            $stack = $globalMiddleware;
            $core = static fn (Request $r): Response => throw $notFound;
        }

        $pipeline = array_reduce(
            array_reverse($stack),
            fn (Closure $next, string $definition): Closure => fn (Request $r): Response => $this->resolveMiddleware($definition, $r, $next),
            $core,
        );

        return $pipeline($request);
    }

    public function current(): ?Route
    {
        return $this->current;
    }

    /**
     * URL de la page courante dans chaque langue (sélecteur de langue et balises hreflang).
     *
     * @return array<string, string> locale => chemin
     */
    public function alternates(Request $request): array
    {
        $route = $this->current;
        $result = [];
        foreach ($this->locales as $locale) {
            if ($route !== null && $route->getName() !== null && isset($this->named[$route->getName()][$locale])) {
                $query = $request->query('page') !== null ? ['page' => $request->query('page')] : [];
                $result[$locale] = $this->named[$route->getName()][$locale]->buildPath($request->params() + $query);
            } elseif ($this->has('home')) {
                $result[$locale] = $this->url('home', [], $locale);
            }
        }

        return $result;
    }

    private function resolveMiddleware(string $definition, Request $request, Closure $next): Response
    {
        [$alias, $args] = array_pad(explode(':', $definition, 2), 2, '');
        $class = $this->aliases[$alias] ?? $alias;
        if (!class_exists($class)) {
            throw new \RuntimeException('Middleware inconnu : ' . $definition);
        }
        /** @var Middleware $middleware */
        $middleware = new $class();
        $arguments = $args === '' ? [] : explode(',', $args);

        return $middleware->handle($request, $next, ...$arguments);
    }

    private function callAction(Closure|array|string $action, Request $request): Response
    {
        if (is_string($action) && str_contains($action, '@')) {
            $action = explode('@', $action, 2);
        }
        if (is_array($action)) {
            [$class, $method] = $action;
            $controller = new $class();
            $result = $controller->{$method}($request, ...array_values($request->params()));
        } elseif ($action instanceof Closure) {
            $result = $action($request, ...array_values($request->params()));
        } else {
            throw new \RuntimeException('Action de route invalide.');
        }

        return match (true) {
            $result instanceof Response => $result,
            is_array($result)           => Response::json($result),
            default                     => Response::html((string) $result),
        };
    }
}
