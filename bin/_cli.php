<?php

declare(strict_types=1);

/*
 * Outils communs aux scripts en ligne de commande (bin/*.php).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ligne de commande uniquement.');
}

$basePath = dirname(__DIR__);
if (!is_file($basePath . '/vendor/autoload.php')) {
    fwrite(STDERR, "Dépendances manquantes : lancez d'abord « composer install ».\n");
    exit(1);
}
require $basePath . '/vendor/autoload.php';

/** Options « --cle=valeur » et « --drapeau ». @return array<string, string|bool> */
function cli_options(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/i', $arg, $m)) {
            $options[$m[1]] = $m[2] ?? true;
        }
    }

    return $options;
}

function cli_color(string $text, string $color): string
{
    $codes = ['green' => '32', 'red' => '31', 'yellow' => '33', 'cyan' => '36', 'bold' => '1'];
    $tty = function_exists('posix_isatty') ? @posix_isatty(STDOUT) : true;

    return $tty ? "\033[" . ($codes[$color] ?? '0') . 'm' . $text . "\033[0m" : $text;
}

function cli_line(string $text = ''): void
{
    fwrite(STDOUT, $text . PHP_EOL);
}

function cli_title(string $text): void
{
    cli_line();
    cli_line(cli_color('== ' . $text . ' ==', 'bold'));
}

function cli_ok(string $text): void
{
    cli_line(cli_color('  ✔ ', 'green') . $text);
}

function cli_warn(string $text): void
{
    cli_line(cli_color('  ! ', 'yellow') . $text);
}

function cli_fail(string $text): void
{
    fwrite(STDERR, cli_color('  ✘ ', 'red') . $text . PHP_EOL);
}

function cli_ask(string $question, string $default = ''): string
{
    $suffix = $default !== '' ? ' [' . $default . ']' : '';
    fwrite(STDOUT, '  ' . $question . $suffix . ' : ');
    $answer = fgets(STDIN);
    $answer = $answer === false ? '' : trim($answer);

    return $answer !== '' ? $answer : $default;
}

/** Saisie masquée (mot de passe) lorsque le terminal le permet. */
function cli_secret(string $question): string
{
    fwrite(STDOUT, '  ' . $question . ' : ');
    $hidden = DIRECTORY_SEPARATOR === '/' && function_exists('posix_isatty') && @posix_isatty(STDIN);
    if ($hidden) {
        shell_exec('stty -echo');
    }
    $answer = fgets(STDIN);
    if ($hidden) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }

    return $answer === false ? '' : rtrim($answer, "\r\n");
}

function cli_confirm(string $question, bool $default = false): bool
{
    $answer = strtolower(cli_ask($question . ($default ? ' (O/n)' : ' (o/N)')));
    if ($answer === '') {
        return $default;
    }

    return in_array($answer, ['o', 'oui', 'y', 'yes'], true);
}

/**
 * Demande (ou lit dans les options) les informations du compte administrateur, jusqu'à ce qu'elles soient valides.
 *
 * @param array<string, string|bool> $options --admin-name, --admin-email, --admin-phone, --admin-password
 * @return array<string, string>
 */
function cli_admin_data(App\Services\Installer $installer, array $options): array
{
    $interactive = !isset($options['admin-password']);
    while (true) {
        $data = [
            'full_name' => (string) ($options['admin-name'] ?? ''),
            'email'     => (string) ($options['admin-email'] ?? ''),
            'phone'     => (string) ($options['admin-phone'] ?? ''),
            'password'  => (string) ($options['admin-password'] ?? ''),
            'locale'    => (string) ($options['admin-locale'] ?? 'fr'),
        ];
        if ($interactive) {
            $data['full_name'] = cli_ask('Nom complet', $data['full_name']);
            $data['email'] = cli_ask('Adresse e-mail (laisser vide si téléphone)', $data['email']);
            $data['phone'] = str_replace([' ', '.', '-'], '', cli_ask('Téléphone au format +223XXXXXXXX (facultatif)', $data['phone']));
            $data['password'] = cli_secret('Mot de passe (10 caractères min., lettres et chiffres)');
            if ($data['password'] !== cli_secret('Confirmez le mot de passe')) {
                cli_fail('Les deux mots de passe ne correspondent pas.');
                continue;
            }
        }
        $errors = $installer->validateAdmin($data);
        if ($errors === []) {
            return $data;
        }
        foreach ($errors as $messages) {
            cli_fail($messages[0]);
        }
        if (!$interactive) {
            exit(1);
        }
    }
}
