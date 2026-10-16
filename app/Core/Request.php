<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Requête HTTP : accès centralisé et sûr aux entrées ($_GET, $_POST, fichiers, en-têtes, cookies).
 */
class Request
{
    /** @var array<string, string> */
    private array $routeParams = [];

    /** @var array<string, mixed> Attributs posés par le routeur et les middlewares (route, locale…) */
    private array $attributes = [];

    /** @var array<string, mixed>|null */
    private ?array $json = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, string> $cookies
     * @param array<string, mixed> $files
     */
    public function __construct(
        private array $query = [],
        private array $post = [],
        private array $server = [],
        private array $cookies = [],
        private array $files = [],
        private ?string $rawBody = null,
    ) {
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES);
    }

    /**
     * Crée une requête « fabriquée » (tests, outils en ligne de commande).
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $server
     */
    public static function create(string $uri, string $method = 'GET', array $params = [], array $server = [], array $cookies = []): self
    {
        $parts = parse_url($uri);
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $method = strtoupper($method);
        if ($method === 'GET') {
            $query = array_merge($query, $params);
            $params = [];
        }

        return new self($query, $params, array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI'    => $uri,
            'REMOTE_ADDR'    => '127.0.0.1',
            'HTTP_HOST'      => 'localhost',
        ], $server), $cookies);
    }

    /** Méthode HTTP, avec prise en charge du champ caché _method (PUT, PATCH, DELETE). */
    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            $override = strtoupper((string) ($this->post['_method'] ?? $this->header('X-HTTP-Method-Override') ?? ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    /** Chemin décodé (UTF-8), sans barre finale : « /fr/rubriques/التوحيد ». */
    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?? '/'));
        $path = '/' . trim($path, '/');

        return $path;
    }

    public function uri(): string
    {
        return (string) ($this->server['REQUEST_URI'] ?? '/');
    }

    public function isSecure(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));

        return ($https !== '' && $https !== 'off')
            || (int) ($this->server['SERVER_PORT'] ?? 0) === 443
            || strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function host(): string
    {
        return (string) ($this->server['HTTP_HOST'] ?? 'localhost');
    }

    /** Valeur d'entrée : corps (POST/JSON) puis chaîne de requête. */
    public function input(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json() ?? []);
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();

        return array_intersect_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /** Corps JSON décodé (requêtes fetch() du front-end). */
    public function json(): ?array
    {
        if ($this->json !== null) {
            return $this->json;
        }
        if (!str_contains(strtolower((string) $this->header('Content-Type')), 'application/json')) {
            return null;
        }
        $raw = $this->rawBody ?? (string) file_get_contents('php://input');
        $data = json_decode($raw, true);

        return $this->json = is_array($data) ? $data : [];
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$key])) {
            return (string) $this->server[$key];
        }
        $plain = strtoupper(str_replace('-', '_', $name));
        if (in_array($plain, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) && isset($this->server[$plain])) {
            return (string) $this->server[$plain];
        }

        return null;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        $value = $this->cookies[$name] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    /** La réponse attendue est-elle du JSON (fetch, API) ? */
    public function wantsJson(): bool
    {
        $accept = strtolower((string) $this->header('Accept'));

        return str_contains($accept, 'application/json')
            || strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest'
            || str_starts_with($this->path(), '/api/');
    }

    /** @param array<string, string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $name, ?string $default = null): ?string
    {
        return $this->routeParams[$name] ?? $default;
    }

    /** @return array<string, string> */
    public function params(): array
    {
        return $this->routeParams;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function server(): array
    {
        return $this->server;
    }
}
