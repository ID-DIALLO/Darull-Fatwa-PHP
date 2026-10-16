<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Réponse HTTP : HTML, JSON, redirection, téléchargement et diffusion de fichiers avec HTTP Range
 * (indispensable pour lire et avancer dans un audio sur iPhone).
 */
class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<array{name: string, value: string, options: array<string, mixed>}> */
    private array $cookies = [];

    /** Corps différé (diffusion d'un fichier par blocs) */
    private ?Closure $streamer = null;

    /** @param array<string, string> $headers */
    public function __construct(private string $body = '', private int $status = 200, array $headers = [])
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return new self($body, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    /**
     * Diffuse un fichier en respectant l'en-tête Range (réponses 206 / 416).
     * $disposition : 'inline' (lecture) ou 'attachment' (téléchargement).
     */
    public static function file(string $path, string $mime, ?Request $request = null, string $disposition = 'inline', ?string $filename = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new HttpException(404);
        }

        $size = (int) filesize($path);
        $start = 0;
        $end = $size - 1;
        $status = 200;
        $response = new self('', 200, [
            'Content-Type'  => $mime,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Content-Disposition' => $disposition . '; filename="' . addcslashes($filename ?? basename($path), '"\\') . '"',
        ]);

        $range = $request?->header('Range');
        if ($range !== null && $size > 0) {
            $parsed = self::parseRange($range, $size);
            if ($parsed === null) {
                return new self('', 416, ['Content-Range' => 'bytes */' . $size]);
            }
            [$start, $end] = $parsed;
            $status = 206;
            $response->header('Content-Range', sprintf('bytes %d-%d/%d', $start, $end, $size));
        }

        $length = $size === 0 ? 0 : $end - $start + 1;
        $response->status = $status;
        $response->header('Content-Length', (string) $length);
        $response->streamer = static function () use ($path, $start, $length): void {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return;
            }
            fseek($handle, $start);
            $remaining = $length;
            while ($remaining > 0 && !feof($handle)) {
                $chunk = fread($handle, (int) min(65536, $remaining));
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
            }
            fclose($handle);
        };

        return $response;
    }

    /**
     * Analyse un en-tête « Range: bytes=début-fin » (une seule plage).
     *
     * @return array{0: int, 1: int}|null [début, fin] inclus, ou null si la plage est invalide
     */
    public static function parseRange(string $header, int $size): ?array
    {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m) || ($m[1] === '' && $m[2] === '')) {
            return null;
        }
        if ($m[1] === '') {
            // « bytes=-500 » : les 500 derniers octets
            $start = max(0, $size - (int) $m[2]);
            $end = $size - 1;
        } else {
            $start = (int) $m[1];
            $end = $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);
        }

        return ($start > $end || $start >= $size) ? null : [$start, $end];
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @param array<string, mixed> $options */
    public function cookie(string $name, string $value, array $options = []): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options + [
            'expires'  => 0,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]];

        return $this;
    }

    /** @return list<array{name: string, value: string, options: array<string, mixed>}> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function isStream(): bool
    {
        return $this->streamer !== null;
    }

    /** Ajoute un message flash (redirections après formulaire). */
    public function with(string $type, string $message): self
    {
        Flash::add($type, $message);

        return $this;
    }

    public function send(bool $headOnly = false): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
            foreach ($this->cookies as $cookie) {
                setcookie($cookie['name'], $cookie['value'], $cookie['options']);
            }
        }
        if ($headOnly) {
            return;
        }
        if ($this->streamer !== null) {
            // Diffusion directe (pas de mise en mémoire des gros fichiers audio)
            while (PHP_SAPI !== 'cli' && ob_get_level() > 0) {
                ob_end_clean();
            }
            ($this->streamer)();

            return;
        }
        echo $this->body;
    }
}
