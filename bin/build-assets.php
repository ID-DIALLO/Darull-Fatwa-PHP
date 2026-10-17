<?php

declare(strict_types=1);

/*
 * Construction des ressources pour la production (§13.2), sans Node :
 *   php bin/build-assets.php
 *
 * - CSS : concaténation + minification de fonts/variables/app (+ rtl) et de l'administration dans public/assets/dist/.
 * - JS  : copie allégée (commentaires de documentation et lignes vides retirés) des scripts maison ; les modules
 *         gardent leurs noms car ils s'importent entre eux.
 * - Image de partage par défaut 1200×630 (Open Graph / WhatsApp) générée à partir du logo.
 * - Manifeste public/assets/dist/manifest.json lu par asset() quand APP_ENV=production (ou ASSETS_BUILD=1).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$base = dirname(__DIR__);
$public = $base . '/public';
$dist = $public . '/assets/dist';
if (!is_dir($dist . '/js')) {
    mkdir($dist . '/js', 0775, true);
}

/** Minification CSS prudente : commentaires, espaces superflus, points-virgules finaux. Les chaînes et url() sont préservées. */
function minify_css(string $css): string
{
    $css = preg_replace('~/\*(?!!)[\s\S]*?\*/~', '', $css) ?? $css;          // commentaires (sauf /*! licence */)
    $css = preg_replace('/\s+/', ' ', $css) ?? $css;
    $css = preg_replace('/\s*([{};:,>+~])\s*/', '$1', $css) ?? $css;
    $css = str_replace(';}', '}', $css);
    $css = preg_replace('/\(\s*(["\']?)\s*/', '($1', $css) ?? $css;

    return trim($css);
}

/** Réécrit les url(../x) relatives au dossier d'origine pour le dossier dist. */
function rebase_css_urls(string $css, string $fromDir, string $toDir): string
{
    return preg_replace_callback('/url\(\s*(["\']?)([^"\')]+)\1\s*\)/', static function (array $m) use ($fromDir, $toDir): string {
        $url = trim($m[2]);
        if ($url === '' || $url[0] === '/' || str_starts_with($url, 'data:') || preg_match('~^[a-z]+:~i', $url) || $url[0] === '#') {
            return $m[0];
        }
        $absolute = realpath_like($fromDir . '/' . $url);
        $relative = relative_path($toDir, $absolute);

        return 'url(' . $m[1] . $relative . $m[1] . ')';
    }, $css) ?? $css;
}

function realpath_like(string $path): string
{
    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }

    return '/' . implode('/', $parts);
}

function relative_path(string $from, string $to): string
{
    $from = explode('/', trim($from, '/'));
    $to = explode('/', trim($to, '/'));
    while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
        array_shift($from);
        array_shift($to);
    }

    return str_repeat('../', count($from)) . implode('/', $to);
}

/** Allègement JS sûr : blocs /** … *\/ et lignes entières « // … » retirés, lignes vides supprimées. Le code reste intact. */
function lighten_js(string $js): string
{
    $js = preg_replace('~^\s*/\*\*[\s\S]*?\*/\s*$~m', '', $js) ?? $js;
    $lines = [];
    foreach (preg_split('/\R/', $js) ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '// ')) {
            continue;
        }
        $lines[] = rtrim($line);
    }

    return implode("\n", $lines) . "\n";
}

$manifest = [];
$bundles = [
    'assets/dist/app.css'     => ['assets/css/fonts.css', 'assets/css/variables.css', 'assets/css/app.css'],
    'assets/dist/app-rtl.css' => ['assets/css/fonts.css', 'assets/css/variables.css', 'assets/css/app.css', 'assets/css/rtl.css'],
    'assets/dist/admin.css'   => ['assets/css/admin.css'],
];
foreach ($bundles as $target => $sources) {
    $css = '';
    foreach ($sources as $source) {
        $file = $public . '/' . $source;
        if (!is_file($file)) {
            fwrite(STDERR, "Source absente : {$source}\n");
            exit(1);
        }
        $css .= rebase_css_urls((string) file_get_contents($file), dirname($file), $dist) . "\n";
    }
    $css = minify_css($css);
    file_put_contents($public . '/' . $target, $css);
    $manifest[$target] = $target;
    if (count($sources) === 1) {
        $manifest[$sources[0]] = $target; // fichier seul : la version minifiée remplace la source
    }
    printf("%-26s %6.1f ko\n", $target, strlen($css) / 1024);
}

foreach (glob($public . '/assets/js/*.js') ?: [] as $file) {
    $name = basename($file);
    $light = lighten_js((string) file_get_contents($file));
    file_put_contents($dist . '/js/' . $name, $light);
    $manifest['assets/js/' . $name] = 'assets/dist/js/' . $name;
    printf("%-26s %6.1f ko → %5.1f ko\n", 'assets/js/' . $name, filesize($file) / 1024, strlen($light) / 1024);
}

// Image de partage par défaut (1200×630) : fond crème, logo horizontal centré, bandeau vert
$og = $public . '/assets/img/og-default.png';
$logo = $public . '/assets/img/logo/darul-fatwa-logo.png';
if (function_exists('imagecreatetruecolor') && is_file($logo)) {
    $canvas = imagecreatetruecolor(1200, 630);
    $cream = imagecolorallocate($canvas, 0xF7, 0xF1, 0xE1);
    $green = imagecolorallocate($canvas, 0x0B, 0x5D, 0x45);
    $gold = imagecolorallocate($canvas, 0xC9, 0xA2, 0x27);
    imagefill($canvas, 0, 0, $cream);
    imagefilledrectangle($canvas, 0, 590, 1200, 630, $green);
    imagefilledrectangle($canvas, 0, 582, 1200, 590, $gold);
    $source = imagecreatefrompng($logo);
    if ($source !== false) {
        imagealphablending($canvas, true);
        $w = imagesx($source);
        $h = imagesy($source);
        $targetW = 900;
        $targetH = (int) round($h * $targetW / $w);
        imagecopyresampled($canvas, $source, (int) ((1200 - $targetW) / 2), (int) ((590 - $targetH) / 2), 0, 0, $targetW, $targetH, $w, $h);
        imagedestroy($source);
    }
    imagepng($canvas, $og, 6);
    imagedestroy($canvas);
    printf("%-26s %6.1f ko\n", 'assets/img/og-default.png', filesize($og) / 1024);
}

file_put_contents($dist . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "Manifeste : assets/dist/manifest.json (" . count($manifest) . " entrées)\n";
