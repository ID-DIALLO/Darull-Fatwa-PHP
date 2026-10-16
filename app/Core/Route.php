<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Une route : méthodes, chemin (avec paramètres {id}, {slug}, {id:\d+}), action, nom, langue et middlewares.
 */
class Route
{
    public readonly string $regex;

    /** @var list<string> */
    public readonly array $paramNames;

    /** @var list<string> */
    private array $middleware = [];

    private ?string $name = null;

    private bool $csrf = true;

    /**
     * @param list<string> $methods
     * @param Closure|array{0: class-string, 1: string}|string $action
     */
    public function __construct(
        public readonly array $methods,
        public readonly string $path,
        public readonly Closure|array|string $action,
        public readonly ?string $locale = null,
        private readonly ?Router $router = null,
    ) {
        [$this->regex, $this->paramNames] = self::compile($path);
    }

    /**
     * Transforme « /fr/forum/sujet/{id:\d+}-{slug} » en expression régulière UTF-8.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function compile(string $path): array
    {
        $parts = preg_split('/(\{\w+(?::[^{}]+)?\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $regex = '';
        $names = [];
        foreach ($parts as $part) {
            if (preg_match('/^\{(\w+)(?::([^{}]+))?\}$/', $part, $m)) {
                $names[] = $m[1];
                $regex .= '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return ['#^' . ($regex === '' ? '/' : $regex) . '$#u', $names];
    }

    /** @return array<string, string>|null Paramètres capturés, ou null si le chemin ne correspond pas */
    public function matches(string $path): ?array
    {
        if (!preg_match($this->regex, $path, $m)) {
            return null;
        }
        $params = [];
        foreach ($this->paramNames as $name) {
            $params[$name] = $m[$name];
        }

        return $params;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        $this->router?->registerName($this);

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /** @param string|list<string> $middleware */
    public function middleware(string|array $middleware): self
    {
        foreach ((array) $middleware as $m) {
            $this->middleware[] = $m;
        }

        return $this;
    }

    /** @return list<string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /** Désactive la vérification CSRF (webhooks signés uniquement). */
    public function withoutCsrf(): self
    {
        $this->csrf = false;

        return $this;
    }

    public function requiresCsrf(): bool
    {
        return $this->csrf;
    }

    /**
     * Construit l'URL de la route à partir des paramètres ; les paramètres en trop vont dans la query string.
     *
     * @param array<string, scalar> $params
     */
    public function buildPath(array $params = []): string
    {
        $path = preg_replace_callback('/\{(\w+)(?::[^{}]+)?\}/', static function (array $m) use (&$params): string {
            if (!array_key_exists($m[1], $params)) {
                throw new \InvalidArgumentException('Paramètre de route manquant : ' . $m[1]);
            }
            $value = (string) $params[$m[1]];
            unset($params[$m[1]]);

            return rawurlencode($value);
        }, $this->path) ?? $this->path;

        $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');

        return $path . ($params !== [] ? '?' . http_build_query($params) : '');
    }
}
