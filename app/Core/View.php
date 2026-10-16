<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gabarits PHP avec layouts, sections et partials.
 *
 * Dans une vue :
 *   <?php $this->extend('layouts/front') ?>
 *   <?php $this->section('content') ?> ... <?php $this->endSection() ?>
 * Dans le layout :
 *   <?= $this->yield('content') ?>   <?= $this->partial('partials/header') ?>
 * Toute valeur affichée passe par e() (échappement HTML).
 */
class View
{
    /** @var array<string, mixed> Données partagées avec toutes les vues */
    private static array $shared = [];

    private static string $basePath = '';

    /** @var array<string, string> */
    private array $sections = [];

    /** @var list<string> */
    private array $sectionStack = [];

    private ?string $layout = null;

    /** @var array<string, mixed> */
    private array $data = [];

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(string $key, mixed $default = null): mixed
    {
        return self::$shared[$key] ?? $default;
    }

    /** @param array<string, mixed> $data */
    public static function make(string $template, array $data = []): string
    {
        return (new self())->render($template, $data);
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $this->data = array_merge(self::$shared, $data);
        $content = $this->renderFile($template, $this->data);

        // Un gabarit peut étendre un layout, qui peut lui-même en étendre un autre.
        while ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $this->sections['content'] ??= $content;
            $content = $this->renderFile($layout, $this->data);
        }

        return $content;
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name, ?string $content = null): void
    {
        if ($content !== null) {
            $this->sections[$name] = e($content);

            return;
        }
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new \LogicException('endSection() sans section() ouverte.');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    /** @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderFile($template, array_merge($this->data, $data));
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        if (!preg_match('#^[a-z0-9_\-/]+$#i', $template) || str_contains($template, '..')) {
            throw new \InvalidArgumentException('Nom de vue invalide : ' . $template);
        }
        $file = self::$basePath . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Vue introuvable : ' . $template);
        }

        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })->call($this, $file, $data);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
