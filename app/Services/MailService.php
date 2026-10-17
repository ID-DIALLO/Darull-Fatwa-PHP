<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;
use App\Core\Logger;
use App\Core\View;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envoi d'e-mails HTML (PHPMailer, SMTP) dans la langue du destinataire (§4.12).
 * Le gabarit app/Views/emails/{template}.php est rendu dans layouts/email ; l'objet vient de
 * lang/{locale}/emails.php (« {template}.subject »).
 *
 * Pilotes : smtp (production), log (écrit dans storage/logs/mail-*.log), array (tests).
 */
final class MailService
{
    /** @var list<array{to: string, subject: string, locale: string, template: string, html: string}> */
    public static array $sent = [];

    private static ?string $driverOverride = null;

    /** Force le pilote « array » : les e-mails sont conservés en mémoire (tests). */
    public static function fake(): void
    {
        self::$driverOverride = 'array';
        self::$sent = [];
    }

    public static function restore(): void
    {
        self::$driverOverride = null;
        self::$sent = [];
    }

    /**
     * @param array<string, mixed> $data Variables du gabarit
     */
    public static function send(string $to, string $template, array $data, string $locale, ?string $toName = null): bool
    {
        if (!Lang::isSupported($locale)) {
            $locale = Lang::getDefault();
        }
        $previous = Lang::getLocale();
        Lang::setLocale($locale);
        try {
            $subject = Lang::get('emails.' . $template . '.subject', self::scalarsOf($data));
            $html = View::make('emails/' . $template, $data + ['mailLocale' => $locale, 'subject' => $subject]);
        } finally {
            Lang::setLocale($previous);
        }

        $driver = self::$driverOverride ?? (string) config('mail.mailer', 'smtp');
        if ($driver === 'array') {
            self::$sent[] = ['to' => $to, 'subject' => $subject, 'locale' => $locale, 'template' => $template, 'html' => $html];

            return true;
        }
        if ($driver === 'log' || (string) config('mail.host', '') === '') {
            self::writeLog($to, $subject, $html);

            return true;
        }

        return self::smtp($to, $toName, $subject, $html);
    }

    private static function smtp(string $to, ?string $toName, string $subject, string $html): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Host = (string) config('mail.host');
            $mail->Port = (int) config('mail.port', 587);
            $encryption = (string) config('mail.encryption', 'tls');
            $mail->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($encryption === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
            $username = (string) config('mail.username', '');
            $mail->SMTPAuth = $username !== '';
            if ($mail->SMTPAuth) {
                $mail->Username = $username;
                $mail->Password = (string) config('mail.password', '');
            }
            $mail->Timeout = 15;
            $mail->setFrom((string) config('mail.from.address'), (string) config('mail.from.name'));
            $mail->addAddress($to, $toName ?? '');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = self::plainText($html);
            $mail->send();

            return true;
        } catch (MailException $e) {
            Logger::error('Échec d\'envoi d\'e-mail : ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);

            return false;
        }
    }

    private static function writeLog(string $to, string $subject, string $html): void
    {
        $dir = storage_path('logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = sprintf("==== %s | To: %s | Subject: %s ====\n%s\n\n", gmdate('Y-m-d H:i:s'), $to, $subject, self::plainText($html));
        @file_put_contents($dir . '/mail-' . gmdate('Y-m-d') . '.log', $entry, FILE_APPEND | LOCK_EX);
    }

    /** Version texte d'un e-mail HTML (liens conservés). */
    public static function plainText(string $html): string
    {
        $text = preg_replace('/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $html) ?? $html;
        $text = preg_replace('/<(br|\/p|\/h\d|\/tr|\/li)[^>]*>/i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, scalar|null>
     */
    private static function scalarsOf(array $data): array
    {
        return array_filter($data, static fn ($v): bool => $v === null || is_scalar($v));
    }
}
